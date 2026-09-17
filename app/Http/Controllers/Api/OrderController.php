<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderAvailable;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Deliverer;
use App\Models\Order;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    /** POST /orders — Espace Client */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'max:50'],
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'pickup_adresse' => ['required', 'string', 'max:255'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_adresse' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $distanceKm = $this->pricing->distanceKm(
            $data['pickup_lat'], $data['pickup_lng'],
            $data['dest_lat'], $data['dest_lng'],
        );

        $order = Order::create([
            ...$data,
            'client_id' => $request->user()->id,
            'statut' => 'en_attente',
            'distance_km' => $distanceKm,
            'prix_estime' => $this->pricing->prix($distanceKm),
            'cree_le' => now(),
        ]);

        $order->recordStatus('en_attente');

        // Notifie les livreurs disponibles à proximité (rayon de 5 km)
        Deliverer::disponible()->get()->each(function (Deliverer $deliverer) use ($order) {
            if ($deliverer->position_lat === null) {
                return;
            }
            $dist = $this->pricing->distanceKm(
                $deliverer->position_lat, $deliverer->position_lng,
                $order->pickup_lat, $order->pickup_lng,
            );
            if ($dist <= 5) {
                event(new NewOrderAvailable($order, $deliverer->user_id));
            }
        });

        return response()->json([
            'order' => [
                'id' => $order->id,
                'statut' => $order->statut,
                'distance_km' => $order->distance_km,
                'prix_estime' => $order->prix_estime,
                'cree_le' => $order->cree_le->toISOString(),
            ],
        ], 201);
    }

    /** GET /orders/{id} */
    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        $autorise = $user->isAdmin()
            || $order->client_id === $user->id
            || $order->deliverer_id === $user->id;

        if (! $autorise) {
            return response()->json(['message' => 'Accès non autorisé à cette commande.'], 403);
        }

        return response()->json([
            'order' => new OrderResource($order->load('deliverer.deliverer')),
        ]);
    }

    /** GET /deliverers/nearby?lat=&lng=&rayon_km= */
    public function nearbyDeliverers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'rayon_km' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $rayon = (float) $request->input('rayon_km', 5);

        $deliverers = Deliverer::disponible()
            ->whereNotNull('position_lat')
            ->with('user:id,name')
            ->get()
            ->filter(fn (Deliverer $d) => $this->pricing->distanceKm($lat, $lng, $d->position_lat, $d->position_lng) <= $rayon)
            ->map(fn (Deliverer $d) => [
                'id' => $d->user_id,
                'nom' => $d->user->name,
                'lat' => $d->position_lat,
                'lng' => $d->position_lng,
            ])
            ->values();

        return response()->json(['deliverers' => $deliverers]);
    }
}
