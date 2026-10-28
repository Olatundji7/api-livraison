<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'stats' => [
                'clients' => User::where('role', 'client')->count(),
                'drivers' => User::where('role', 'driver')->count(),
                'drivers_available' => Deliverer::where('status', 'disponible')->count(),
                'drivers_busy' => Deliverer::where('status', 'occupe')->count(),
                'orders_total' => Order::count(),
                'orders_active' => Order::whereNotIn('status', Order::STATUTS_FINAUX)->count(),
                'orders_completed' => Order::where('status', 'livree')->count(),
                'revenue' => (int) Order::where('status', 'livree')->where('payment_status', 'paye')->sum('net_service_revenue'),
                'gross_collected' => (int) Order::where('status', 'livree')->where('payment_status', 'paye')->sum('total'),
            ],
        ]);
    }

    public function drivers()
    {
        return response()->json([
            'drivers' => Deliverer::with('user:id,name,phone,email,status')->latest()->get(),
        ]);
    }

    public function storeDriver(Request $request)
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'vehicule_type' => ['nullable', 'string', 'max:100'],
            'immatriculation' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ])->validate();

        $driver = DB::transaction(function () use ($request, $data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => 'driver',
                'status' => 'actif',
            ]);

            $photo = $request->file('photo')?->store('drivers', 'public');

            return Deliverer::create([
                'user_id' => $user->id,
                'photo' => $photo,
                'telephone' => $data['telephone'] ?? $data['phone'],
                'vehicule_type' => $data['vehicule_type'] ?? 'moto',
                'immatriculation' => $data['immatriculation'] ?? null,
                'status' => 'hors_ligne',
            ])->load('user:id,name,phone,email,status');
        });

        return response()->json(['driver' => $driver], 201);
    }

    public function updateDriver(Request $request, Deliverer $driver)
    {
        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30', 'unique:users,phone,' . $driver->user_id],
            'email' => ['nullable', 'email', 'unique:users,email,' . $driver->user_id],
            'status' => ['sometimes', 'in:actif,suspendu'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'vehicule_type' => ['nullable', 'string', 'max:100'],
            'immatriculation' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ])->validate();

        $user = $driver->user;
        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => array_key_exists('email', $data) ? $data['email'] : null,
            'status' => $data['status'] ?? null,
        ], static fn ($value) => $value !== null));

        $driverData = array_filter([
            'telephone' => $data['telephone'] ?? null,
            'vehicule_type' => $data['vehicule_type'] ?? null,
            'immatriculation' => $data['immatriculation'] ?? null,
        ], static fn ($value) => $value !== null);

        if ($request->hasFile('photo')) {
            $driverData['photo'] = $request->file('photo')->store('drivers', 'public');
        }

        $driver->update($driverData);

        return response()->json(['driver' => $driver->fresh()->load('user:id,name,phone,email,status')]);
    }

    /**
     * Réinitialise le mot de passe d'un livreur (oubli).
     * Sans mot de passe fourni, un mot de passe temporaire est généré et
     * renvoyé UNE seule fois à l'administrateur.
     */
    public function resetDriverPassword(Request $request, Deliverer $driver)
    {
        $data = Validator::make($request->all(), [
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ])->validate();

        $generated = empty($data['password']);
        $password = $generated ? $this->temporaryPassword() : $data['password'];

        $user = $driver->user;
        $user->password = $password; // haché automatiquement (cast « hashed »)
        $user->save();

        // Déconnecte le livreur de tous ses appareils.
        $user->tokens()->delete();

        Log::info('Mot de passe livreur réinitialisé', [
            'admin_id' => $request->user()->id,
            'driver_user_id' => $user->id,
        ]);

        return response()->json(array_filter([
            'message' => 'Mot de passe réinitialisé.',
            'generated' => $generated,
            'password' => $generated ? $password : null,
        ], static fn ($value) => $value !== null));
    }

    private function temporaryPassword(int $length = 8): string
    {
        // Sans caractères ambigus (0/O, 1/l/I) pour être dicté facilement.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    public function orders(Request $request)
    {
        $query = Order::with(['client:id,name,phone', 'driver:id,name,phone', 'items.product:id,name,price'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $page = $query->paginate(30);
        $page->getCollection()->transform(function (Order $order) {
            return $order;
        });

        return response()->json($page);
    }


    /**
     * Aperçu des frais de livraison AVANT validation : l'administrateur voit les
     * frais (calculés comme à la validation) séparément du prix de la commande.
     */
    public function pricingPreview(Order $order, \App\Services\PricingService $pricing)
    {
        if ($order->destination_latitude === null || $order->destination_longitude === null) {
            return response()->json(['message' => 'La destination GPS du client est manquante : impossible de calculer les frais de livraison.'], 422);
        }

        try {
            $distance = $pricing->roadDistanceKm((float) $order->destination_latitude, (float) $order->destination_longitude);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $gross = $pricing->grossDeliveryFee($distance);
        $discount = $pricing->discountAmount($distance);

        return response()->json(['pricing' => [
            'route_distance_km' => $distance,
            'gross_delivery_fee' => $gross,
            'discount_amount' => $discount,
            'delivery_fee' => max(0, $gross - $discount),
            'product_price' => (int) ($order->subtotal ?? 0),
        ]]);
    }

    /** Validation admin : prix produit + livraison routière ; le gain livreur est cumulé ici. */
    public function approveOrder(Request $request, Order $order, \App\Services\PricingService $pricing)
    {
        $data = Validator::make($request->all(), [
            'product_price' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'price' => ['nullable', 'integer', 'min:1', 'max:100000000'], // compatibilité ancienne app
        ])->validate();
        $productPrice = (int) ($data['product_price'] ?? $data['price'] ?? 0);
        if ($productPrice < 1) {
            return response()->json(['message' => 'Le prix du produit est obligatoire.'], 422);
        }
        if ($order->destination_latitude === null || $order->destination_longitude === null) {
            return response()->json(['message' => 'La destination GPS du client est obligatoire pour calculer les frais.'], 422);
        }
        try {
            $routeDistance = $pricing->roadDistanceKm((float) $order->destination_latitude, (float) $order->destination_longitude);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
        $gross = $pricing->grossDeliveryFee($routeDistance);
        $discount = $pricing->discountAmount($routeDistance);
        $netDelivery = max(0, $gross - $discount);
        $result = DB::transaction(function () use ($order, $productPrice, $routeDistance, $gross, $discount, $netDelivery) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked) return ['ok' => false, 'status' => 404, 'message' => 'Commande introuvable.'];
            if ($locked->estTermine()) return ['ok' => false, 'status' => 409, 'message' => 'Cette commande est déjà terminée.'];
            if ($locked->payment_status === 'paye') return ['ok' => false, 'status' => 409, 'message' => 'Cette commande est déjà payée.'];
            if ($locked->status !== 'en_attente' || $locked->driver_id !== null) return ['ok' => false, 'status' => 409, 'message' => 'Seule une commande en attente, non attribuée, peut être validée.'];

            $locked->update([
                'pickup_address' => 'MA Livraison',
                'pickup_latitude' => 9.3512777053658,
                'pickup_longitude' => 2.6290550914044717,
                'route_distance_km' => $routeDistance,
                'subtotal' => $productPrice,
                'gross_delivery_fee' => $gross,
                'discount_amount' => $discount,
                'delivery_fee' => $netDelivery,
                'service_fee' => 0,
                'fedapay_fee' => 0,
                'net_service_revenue' => 0,
                'driver_earnings' => $gross,
                'total' => $productPrice + $netDelivery,
                'payment_status' => 'non_paye',
                'status' => 'en_attente',
            ]);
            return ['ok' => true, 'order' => $locked->fresh()->load('client:id,name,phone')];
        });
        if (! $result['ok']) return response()->json(['message' => $result['message']], $result['status']);
        return response()->json([
            'message' => 'Commande validée. Prix produit, frais nets client et gain brut livreur enregistrés.',
            'order' => $result['order'],
            'pricing' => [
                'route_distance_km' => $routeDistance,
                'product_price' => $productPrice,
                'gross_delivery_fee' => $gross,
                'discount_amount' => $discount,
                'delivery_fee' => $netDelivery,
                'driver_earnings' => $gross,
                'total' => $productPrice + $netDelivery,
            ],
        ]);
    }

    public function assignOrder(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', 'integer', 'exists:deliverers,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Livreur invalide.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();
            $driver = Deliverer::whereKey($request->integer('driver_id'))->lockForUpdate()->first();

            if (! $lockedOrder || ! $driver) {
                return ['ok' => false, 'status' => 404, 'message' => 'Commande ou livreur introuvable.'];
            }

            if ($lockedOrder->estTermine()) {
                return ['ok' => false, 'status' => 409, 'message' => 'Cette commande est déjà terminée.'];
            }

            if ($driver->status !== 'disponible') {
                return ['ok' => false, 'status' => 409, 'message' => 'Ce livreur n’est pas disponible.'];
            }

            $hasPendingAssignment = Order::where('driver_id', $driver->user_id)
                ->whereIn('status', ['livreur_reserve', 'livreur_accepte', 'en_cours', 'arrivee_retrait', 'colis_recupere', 'en_livraison'])
                ->exists();

            if ($hasPendingAssignment) {
                return ['ok' => false, 'status' => 409, 'message' => 'Ce livreur a déjà une commande attribuée ou en cours.'];
            }

            $lockedOrder->update([
                'driver_id' => $driver->user_id,
                'status' => 'livreur_reserve',
            ]);

            return ['ok' => true, 'order' => $lockedOrder->fresh()->load('driver:id,name,phone')];
        });

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Commande attribuée au livreur. Elle reste en attente de son acceptation.',
            'order' => $result['order'],
        ]);
    }

    public function driverLocations(Request $request, Deliverer $driver)
    {
        $limit = min(max((int) $request->input('limit', 200), 1), 1000);

        return response()->json([
            'driver' => $driver->load('user:id,name'),
            'locations' => DriverLocation::where('driver_id', $driver->user_id)
                ->latest('recorded_at')
                ->limit($limit)
                ->get(),
        ]);
    }

    public function notifications(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $user->notifications()->latest()->limit(50)->get(['id', 'type', 'data', 'read_at', 'created_at']),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markNotificationRead(Request $request, string $notification)
    {
        $request->user()->notifications()->where('id', $notification)->firstOrFail()->markAsRead();

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }
}
