<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    public function orders(Request $request)
    {
        $query = Order::with(['client:id,name,phone', 'driver:id,name,phone'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $page = $query->paginate(30);
        $page->getCollection()->transform(function (Order $order) {
            return $order->makeHidden([
                'subtotal',
                'total',
                'gross_delivery_fee',
                'discount_amount',
                'service_fee',
                'fedapay_fee',
                'net_service_revenue',
            ]);
        });

        return response()->json($page);
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
}
