<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['client_id' => $request->user()->id]);
    }

    private function payload(Cart $cart): array
    {
        $cart->load('items.product');
        $items = $cart->items->map(function (CartItem $item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'product' => $item->product,
                'line_total' => (int) $item->product->price * $item->quantity,
            ];
        })->values()->all();

        $subtotal = array_sum(array_map(fn ($item) => $item['line_total'], $items));
        return ['cart' => ['id' => $cart->id, 'items' => $items, 'subtotal' => $subtotal]];
    }

    public function index(Request $request)
    {
        return response()->json($this->payload($this->cartFor($request)));
    }

    public function addItem(Request $request)
    {
        $data = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $product = Product::active()->findOrFail($data['product_id']);
        $cart = $this->cartFor($request);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $newQuantity = (int) ($item->exists ? $item->quantity : 0) + (int) $data['quantity'];
        if ($newQuantity > $product->stock) {
            return response()->json(['message' => 'Stock insuffisant pour ce produit.'], 422);
        }
        $item->quantity = $newQuantity;
        $item->save();
        return response()->json($this->payload($cart));
    }

    public function updateItem(Request $request, CartItem $item)
    {
        $cart = $this->cartFor($request);
        if ($item->cart_id !== $cart->id) {
            return response()->json(['message' => 'Article de panier non autorisé.'], 403);
        }
        $data = Validator::make($request->all(), ['quantity' => ['required', 'integer', 'min:1', 'max:100']])->validate();
        $product = $item->product;
        if ((int) $data['quantity'] > $product->stock) {
            return response()->json(['message' => 'Stock insuffisant pour ce produit.'], 422);
        }
        $item->update(['quantity' => $data['quantity']]);
        return response()->json($this->payload($cart));
    }

    public function removeItem(Request $request, CartItem $item)
    {
        $cart = $this->cartFor($request);
        if ($item->cart_id !== $cart->id) {
            return response()->json(['message' => 'Article de panier non autorisé.'], 403);
        }
        $item->delete();
        return response()->json($this->payload($cart));
    }

    public function clear(Request $request)
    {
        $cart = $this->cartFor($request);
        $cart->items()->delete();
        return response()->json($this->payload($cart));
    }

    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['sometimes', Rule::in(['livraison'])],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'destination_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['required', 'numeric', 'between:-180,180'],
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Vérifiez les informations de livraison.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $user = $request->user();
        $cart = $this->cartFor($request)->load('items.product');

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Votre panier est vide.'], 409);
        }

        $order = DB::transaction(function () use ($data, $user, $cart) {
            $activeExists = Order::where('client_id', $user->id)
                ->whereNotIn('status', Order::STATUTS_FINAUX)
                ->lockForUpdate()->exists();
            if ($activeExists) return null;

            $subtotal = 0;
            $lines = [];
            foreach ($cart->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                if (! $product->is_active || $product->stock < $item->quantity) {
                    throw new \RuntimeException("Stock insuffisant pour {$product->name}.");
                }
                $lineTotal = (int) $product->price * (int) $item->quantity;
                $subtotal += $lineTotal;
                $lines[] = ['product' => $product, 'quantity' => (int) $item->quantity, 'line_total' => $lineTotal];
            }

            // Le point de départ n’est plus saisi dans le panier. Le prix de course
            // sera calculé à la fin à partir de la distance GPS réellement parcourue.
            $gross = 0;
            $discount = 0;
            $deliveryFee = 0;
            $serviceFee = PricingService::FRAIS_SERVICE;

            $order = Order::create([
                'client_id' => $user->id,
                'driver_id' => null,
                'type' => 'livraison',
                'status' => 'en_attente',
                'pickup_address' => null,
                'pickup_latitude' => null,
                'pickup_longitude' => null,
                'destination_address' => $data['destination_address'],
                'destination_latitude' => $data['destination_latitude'],
                'destination_longitude' => $data['destination_longitude'],
                'note' => $data['note'],
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'gross_delivery_fee' => $gross,
                'discount_amount' => $discount,
                'service_fee' => $serviceFee,
                'fedapay_fee' => 0,
                'net_service_revenue' => $serviceFee,
                'distance_travelled_km' => 0,
                'total' => $subtotal + $deliveryFee + $serviceFee,
                'payment_status' => 'non_paye',
            ]);

            foreach ($lines as $line) {
                $line['product']->decrement('stock', $line['quantity']);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['product']->price,
                    'line_total' => $line['line_total'],
                ]);
            }

            $cart->items()->delete();
            return $order->load('items.product');
        });

        if (! $order) return response()->json(['message' => 'Vous avez déjà une commande en cours.'], 409);

        return response()->json([
            'order' => $order,
            'message' => 'Commande panier créée. Le paiement sera demandé lorsque le livreur marquera la livraison comme terminée.',
        ], 201);
    }
}
