<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use App\Models\Order;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverOrderController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    public function index(Request $request)
    {
        return response()->json(
            Order::where('driver_id', $request->user()->id)
                ->with(['client:id,name,phone', 'items.product'])
                ->latest()
                ->paginate(30)
        );
    }

    /** Toutes les commandes non prises sont proposées à chaque livreur disponible. */
    public function available(Request $request)
    {
        $driverId = $request->user()->id;
        $driverProfile = Deliverer::where('user_id', $driverId)->first();

        if (! $driverProfile || $driverProfile->status !== 'disponible') {
            return response()->json(['orders' => []]);
        }

        $orders = Order::query()
            ->where(function ($query) use ($driverId) {
                $query->where(function ($q) {
                    $q->whereNull('driver_id')->where('status', 'en_attente');
                })->orWhere(function ($q) use ($driverId) {
                    $q->where('driver_id', $driverId)->where('status', 'livreur_reserve');
                });
            })
            ->latest()
            ->get();

        return response()->json(['orders' => $orders]);
    }

    private function owns(Request $request, Order $order): bool
    {
        return $order->driver_id === $request->user()->id;
    }

    public function accept(Request $request, Order $order)
    {
        $result = DB::transaction(function () use ($request, $order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked) return ['ok' => false, 'status' => 404, 'message' => 'Commande introuvable.'];

            $driver = Deliverer::where('user_id', $request->user()->id)->lockForUpdate()->first();
            if (! $driver) return ['ok' => false, 'status' => 404, 'message' => 'Profil livreur introuvable.'];
            if ($driver->status !== 'disponible') return ['ok' => false, 'status' => 409, 'message' => 'Vous devez être disponible pour accepter une commande.'];

            if ($locked->status === 'livreur_reserve') {
                if ($locked->driver_id !== $request->user()->id) {
                    return ['ok' => false, 'status' => 403, 'message' => 'Cette commande est déjà attribuée à un autre livreur.'];
                }
            } elseif ($locked->status === 'en_attente' && $locked->driver_id === null) {
                // Acceptation directe par le livreur, sans attribution préalable de l’admin.
                $locked->driver_id = $request->user()->id;
            } else {
                return ['ok' => false, 'status' => 409, 'message' => 'Cette commande ne peut plus être acceptée.'];
            }

            $hasActive = Order::where('driver_id', $request->user()->id)
                ->whereNotIn('status', Order::STATUTS_FINAUX)
                ->where($locked->getKeyName(), '<>', $locked->id)
                ->lockForUpdate()
                ->exists();
            if ($hasActive) return ['ok' => false, 'status' => 409, 'message' => 'Vous avez déjà une course en cours.'];

            $locked->update([
                'driver_id' => $request->user()->id,
                'status' => 'livreur_accepte',
                'accepted_at' => $locked->accepted_at ?? now(),
            ]);

            $driver->update([
                'status' => 'occupe',
                'reserved_by' => null,
                'reserved_until' => null,
            ]);

            return ['ok' => true, 'order' => $locked->fresh()->load(['client:id,name,phone', 'items.product'])];
        });

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'order' => $result['order'],
            'message' => 'Commande acceptée. Vous pouvez maintenant suivre la position du client et démarrer la course.',
        ]);
    }

    public function reject(Request $request, Order $order)
    {
        if (! $this->owns($request, $order)) {
            return response()->json(['message' => 'Cette commande ne vous est pas attribuée.'], 403);
        }
        if ($order->status !== 'livreur_reserve') {
            return response()->json(['message' => 'La commande ne peut plus être refusée.'], 409);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'en_attente', 'driver_id' => null]);
        });

        return response()->json(['order' => $order->fresh()]);
    }

    public function start(Request $request, Order $order)
    {
        if (! $this->owns($request, $order)) {
            return response()->json(['message' => 'Cette commande ne vous est pas attribuée.'], 403);
        }
        if ($order->status !== 'livreur_accepte') {
            return response()->json(['message' => 'La commande doit être acceptée avant de démarrer.'], 409);
        }
        $order->update(['status' => 'en_cours', 'started_at' => now()]);
        return response()->json(['order' => $order->fresh()]);
    }

    public function pickup(Request $request, Order $order)
    {
        if (! $this->owns($request, $order)) {
            return response()->json(['message' => 'Cette commande ne vous est pas attribuée.'], 403);
        }
        if (! in_array($order->status, ['en_cours', 'arrivee_retrait'], true)) {
            return response()->json(['message' => 'Le retrait n’est pas possible dans cet état.'], 409);
        }
        $order->update(['status' => 'colis_recupere']);
        return response()->json(['order' => $order->fresh()]);
    }

    public function deliver(Request $request, Order $order)
    {
        if (! $this->owns($request, $order)) {
            return response()->json(['message' => 'Cette commande ne vous est pas attribuée.'], 403);
        }
        if (! in_array($order->status, ['colis_recupere', 'en_livraison', 'en_cours'], true)) {
            return response()->json(['message' => 'La livraison ne peut pas être clôturée dans cet état.'], 409);
        }

        $completedAt = now();
        $distance = $this->pricing->travelledDistanceKm($order, $completedAt);
        if ($distance <= 0) {
            return response()->json([
                'message' => 'Distance réelle indisponible. Le livreur doit transmettre au moins deux positions GPS pendant la course avant de clôturer la livraison.',
            ], 409);
        }
        $gross = $this->pricing->grossDeliveryFee($distance);
        $discount = $this->pricing->discountAmount($distance);
        $courseFee = $this->pricing->deliveryFee($distance);
        $serviceFee = (int) ($order->service_fee ?: PricingService::FRAIS_SERVICE);
        $total = (int) $order->subtotal + $courseFee + $serviceFee;

        DB::transaction(function () use ($request, $order, $completedAt, $distance, $gross, $discount, $courseFee, $serviceFee, $total) {
            $order->update([
                'status' => 'livree',
                'completed_at' => $completedAt,
                'distance_travelled_km' => $distance,
                'gross_delivery_fee' => $gross,
                'discount_amount' => $discount,
                'delivery_fee' => $courseFee,
                'service_fee' => $serviceFee,
                'total' => $total,
            ]);
            Deliverer::where('user_id', $request->user()->id)->update([
                'status' => 'disponible',
                'reserved_by' => null,
                'reserved_until' => null,
            ]);
        });

        return response()->json([
            'order' => $order->fresh(),
            'message' => 'Commande marquée comme livrée. Le client peut maintenant procéder au paiement final.',
            'payment_required' => $order->payment_status !== 'paye',
        ]);
    }

    public function cancel(Request $request, Order $order)
    {
        if (! $this->owns($request, $order)) {
            return response()->json(['message' => 'Cette commande ne vous est pas attribuée.'], 403);
        }
        if ($order->estTermine()) {
            return response()->json(['message' => 'Cette commande est déjà terminée.'], 409);
        }

        DB::transaction(function () use ($request, $order) {
            $order->update(['status' => 'annulee', 'cancelled_at' => now()]);
            Deliverer::where('user_id', $request->user()->id)->update([
                'status' => 'disponible',
                'reserved_by' => null,
                'reserved_until' => null,
            ]);
        });

        return response()->json(['order' => $order->fresh()]);
    }
}
