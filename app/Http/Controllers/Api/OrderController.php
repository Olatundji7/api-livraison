<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use App\Models\Order;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    public function store(Request $request)
    {
        $type = (string) $request->input('type');

        $validator = Validator::make($request->all(), [
            'driver_id' => ['prohibited'],
            'type' => ['required', Rule::in(['livraison', 'course_personnelle'])],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'destination_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['required', 'numeric', 'between:-180,180'],
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
                        'destination_latitude.required' => 'Votre position de livraison est requise.',
            'destination_longitude.required' => 'Votre position de livraison est requise.',
            'note.required' => 'L’instruction est obligatoire : indiquez ce que vous souhaitez commander et toute précision utile.',
            'driver_id.prohibited' => 'Le choix du livreur est géré automatiquement par MA Livraison.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Veuillez vérifier les informations de la commande.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $user = $request->user();

        $order = DB::transaction(function () use ($data, $user) {
            $activeExists = Order::where('client_id', $user->id)
                ->whereNotIn('status', Order::STATUTS_FINAUX)
                ->lockForUpdate()
                ->exists();

            if ($activeExists) {
                return null;
            }

            // Le point de départ n’est plus demandé au client. La distance et le prix
            // définitifs sont calculés à partir des positions GPS réellement transmises
            // par le livreur pendant la course.
            $grossFee = 0;
            $discount = 0;
            $fee = 0;

            return Order::create([
                'client_id' => $user->id,
                'driver_id' => null,
                'type' => $data['type'],
                'status' => 'en_attente',
                'pickup_address' => null,
                'pickup_latitude' => null,
                'pickup_longitude' => null,
                'destination_address' => $data['destination_address'] ?? null,
                'destination_latitude' => $data['destination_latitude'] ?? null,
                'destination_longitude' => $data['destination_longitude'] ?? null,
                'note' => $data['note'],
                'subtotal' => 0,
                'delivery_fee' => $fee,
                'gross_delivery_fee' => $grossFee,
                'discount_amount' => $discount,
                'service_fee' => PricingService::FRAIS_SERVICE,
                'fedapay_fee' => 0,
                'net_service_revenue' => PricingService::FRAIS_SERVICE,
                'distance_travelled_km' => 0,
                'total' => $fee + PricingService::FRAIS_SERVICE,
                'payment_status' => 'non_paye',
            ]);
        });

        if (! $order) {
            return response()->json([
                'message' => 'Vous avez déjà une commande en cours. Terminez-la avant d’en créer une autre.',
            ], 409);
        }

        return response()->json([
            'order' => $order->load('driver:id,name,phone'),
            'message' => 'Commande enregistrée. Votre demande sera proposée automatiquement aux livreurs disponibles.',
        ], 201);
    }

    public function index(Request $request)
    {
        return response()->json(
            Order::where('client_id', $request->user()->id)->latest()->paginate(20)
        );
    }

    public function active(Request $request)
    {
        $order = Order::where('client_id', $request->user()->id)
            ->whereNotIn('status', Order::STATUTS_FINAUX)
            ->latest()
            ->first();

        return response()->json(['order' => $order?->load('driver:id,name,phone')]);
    }

    public function show(Request $request, Order $order)
    {
        $user = $request->user();
        if (! $user->isAdmin() && $order->client_id !== $user->id && $order->driver_id !== $user->id) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        return response()->json([
            'order' => $order->load(['client:id,name,phone', 'driver:id,name,phone', 'items.product']),
        ]);
    }

    public function cancel(Request $request, Order $order)
    {
        if ($order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Cette commande ne vous appartient pas.'], 403);
        }
        if ($order->estTermine()) {
            return response()->json(['message' => 'Cette commande est déjà terminée.'], 409);
        }
        if ($order->started_at || in_array($order->status, ['en_cours', 'arrivee_retrait', 'colis_recupere', 'en_livraison'], true)) {
            return response()->json(['message' => 'Une commande en cours de traitement ne peut plus être annulée.'], 409);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'annulee', 'cancelled_at' => now()]);
            if ($order->driver_id) {
                Deliverer::where('user_id', $order->driver_id)->update([
                    'status' => 'disponible',
                    'reserved_by' => null,
                    'reserved_until' => null,
                ]);
            }
        });

        return response()->json(['order' => $order->fresh()]);
    }

    public function tracking(Request $request, Order $order)
    {
        $user = $request->user();
        if (! $user->isAdmin() && $order->client_id !== $user->id && $order->driver_id !== $user->id) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $driver = $order->driver_id ? Deliverer::where('user_id', $order->driver_id)->first() : null;

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'distance_travelled_km' => (float) $order->distance_travelled_km,
            'net_course_amount' => (int) $order->delivery_fee,
            'driver' => $order->driver?->only(['id', 'name', 'phone']),
            'driver_location' => $driver ? [
                'latitude' => $driver->latitude,
                'longitude' => $driver->longitude,
                'last_location_at' => $driver->last_location_at?->toISOString(),
            ] : null,
        ]);
    }
}
