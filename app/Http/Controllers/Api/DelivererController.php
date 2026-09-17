<?php

namespace App\Http\Controllers\Api;

use App\Events\LocationUpdated;
use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use App\Models\Order;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class DelivererController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    private function delivererOrFail(Request $request): Deliverer
    {
        return $request->user()->deliverer()->firstOrFail();
    }

    /** PATCH /deliverer/availability */
    public function availability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disponible' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        $deliverer = $this->delivererOrFail($request);
        $deliverer->update(['disponible' => $request->boolean('disponible')]);

        return response()->json(['disponible' => $deliverer->disponible]);
    }

    /** GET /deliverer/orders/available */
    public function availableOrders(Request $request)
    {
        $deliverer = $this->delivererOrFail($request);

        $query = Order::where('statut', 'en_attente');

        $orders = $query->get()->filter(function (Order $order) use ($deliverer) {
            if ($deliverer->position_lat === null) {
                return true;
            }
            return $this->pricing->distanceKm(
                $deliverer->position_lat, $deliverer->position_lng,
                $order->pickup_lat, $order->pickup_lng,
            ) <= 5;
        })->map(fn (Order $o) => [
            'id' => $o->id,
            'pickup_adresse' => $o->pickup_adresse,
            'dest_adresse' => $o->dest_adresse,
            'distance_km' => $o->distance_km,
            'prix_estime' => $o->prix_estime,
        ])->values();

        return response()->json(['orders' => $orders]);
    }

    /** POST /deliverer/orders/{id}/accept */
    public function accept(Request $request, Order $order)
    {
        if ($order->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette commande a déjà été prise en charge.'], 409);
        }

        $order->update([
            'deliverer_id' => $request->user()->id,
            'statut' => 'traitee',
        ]);
        $order->recordStatus('traitee');

        event(new OrderStatusChanged($order));

        return response()->json(['order_id' => $order->id, 'statut' => $order->statut]);
    }

    /** PATCH /deliverer/orders/{id}/status */
    public function updateStatus(Request $request, Order $order)
    {
        if ($order->deliverer_id !== $request->user()->id) {
            return response()->json(['message' => 'Cette commande ne vous est pas assignée.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'statut' => ['required', Rule::in(['traitee', 'en_cours', 'livree'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        $nouveauStatut = $request->input('statut');

        $order->update(['statut' => $nouveauStatut]);
        $order->recordStatus($nouveauStatut);

        if ($nouveauStatut === 'livree' && $order->prix_final === null) {
            $order->update(['prix_final' => $this->pricing->prix($order->distance_km)]);
        }

        event(new OrderStatusChanged($order));

        return response()->json(['order_id' => $order->id, 'statut' => $order->statut]);
    }

    /** POST /deliverer/location */
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        $deliverer = $this->delivererOrFail($request);
        $deliverer->update([
            'position_lat' => $request->input('lat'),
            'position_lng' => $request->input('lng'),
        ]);

        // Diffuse la position sur la course active du livreur, s'il y en a une.
        $activeOrder = Order::where('deliverer_id', $request->user()->id)
            ->where('statut', 'en_cours')
            ->first();

        if ($activeOrder) {
            event(new LocationUpdated($activeOrder, (float) $request->input('lat'), (float) $request->input('lng')));
        }

        return response()->json(['message' => 'Position mise à jour.']);
    }

    /** GET /deliverer/orders/{id}/invoice */
    public function invoice(Request $request, Order $order)
    {
        if ($order->deliverer_id !== $request->user()->id) {
            return response()->json(['message' => 'Cette commande ne vous est pas assignée.'], 403);
        }

        $prixFinal = $order->prix_final ?? $this->pricing->prix($order->distance_km);

        return response()->json([
            'order_id' => $order->id,
            'distance_reelle_km' => $order->distance_km,
            'tarif_base' => PricingService::TARIF_BASE,
            'tarif_par_km' => PricingService::TARIF_PAR_KM,
            'prix_final' => $prixFinal,
        ]);
    }
}
