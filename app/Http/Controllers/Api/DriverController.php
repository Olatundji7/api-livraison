<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DriverController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    private function clearExpiredReservations(): void
    {
        Deliverer::where('status', 'reserve')->whereNotNull('reserved_until')->where('reserved_until', '<=', now())->update([
            'status' => 'disponible', 'reserved_by' => null, 'reserved_until' => null,
        ]);
    }

    public function available()
    {
        $this->clearExpiredReservations();
        return response()->json(['drivers' => Deliverer::available()->with('user:id,name,phone')->latest()->get()]);
    }

    public function nearby(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);

        $this->clearExpiredReservations();
        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');
        $radius = (float) $request->input('radius', 10);
        $drivers = Deliverer::available()->whereNotNull('latitude')->whereNotNull('longitude')->with('user:id,name,phone')->get()->filter(
            fn (Deliverer $d) => $this->pricing->distanceKm($lat, $lng, $d->latitude, $d->longitude) <= $radius
        )->values();
        return response()->json(['drivers' => $drivers]);
    }

    public function show(Deliverer $driver) { return response()->json(['driver' => $driver->load('user:id,name,phone')]); }

    public function reserve(Request $request, Deliverer $driver)
    {
        $result = DB::transaction(function () use ($request, $driver) {
            $locked = Deliverer::whereKey($driver->id)->lockForUpdate()->first();
            if (! $locked) return null;
            if ($locked->status === 'reserve' && $locked->reserved_until?->isPast()) {
                $locked->update(['status' => 'disponible', 'reserved_by' => null, 'reserved_until' => null]);
            }
            if ($locked->status !== 'disponible') return null;
            $locked->update(['status' => 'reserve', 'reserved_by' => $request->user()->id, 'reserved_until' => now()->addMinutes(5)]);
            return $locked->fresh()->load('user:id,name,phone');
        });
        if (! $result) return response()->json(['message' => 'Ce livreur n’est plus disponible.'], 409);
        return response()->json(['driver' => $result]);
    }

    public function releaseReservation(Request $request, Deliverer $driver)
    {
        if ($driver->reserved_by !== $request->user()->id && ! $request->user()->isAdmin()) return response()->json(['message' => 'Cette réservation ne vous appartient pas.'], 403);
        if ($driver->status !== 'reserve') return response()->json(['message' => 'Aucune réservation active.']);
        $driver->update(['status' => 'disponible', 'reserved_by' => null, 'reserved_until' => null]);
        return response()->json(['message' => 'Réservation libérée.']);
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), ['status' => ['required', 'in:disponible,hors_ligne']]);
        if ($validator->fails()) return response()->json(['message' => 'Statut invalide.', 'errors' => $validator->errors()], 422);
        $driver = $request->user()->deliverer()->firstOrFail();
        if (in_array($driver->status, ['reserve', 'occupe'], true)) return response()->json(['message' => 'Terminez la réservation ou la course en cours avant de changer de statut.'], 409);

        if ($request->input('status') === 'hors_ligne') {
            $pendingAssignment = Order::where('driver_id', $driver->user_id)
                ->where('status', 'livreur_reserve')
                ->exists();
            if ($pendingAssignment) {
                return response()->json(['message' => 'Une commande vous a été attribuée par MA Livraison. Acceptez-la ou contactez l’administration avant de passer hors ligne.'], 409);
            }
        }

        $driver->update(['status' => $request->input('status')]);
        return response()->json(['driver' => $driver->fresh()]);
    }

    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Position GPS invalide.', 'errors' => $validator->errors()], 422);

        $driver = $request->user()->deliverer()->firstOrFail();
        $now = now();
        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');
        $driver->update(['latitude' => $lat, 'longitude' => $lng, 'last_location_at' => $now]);
        DriverLocation::create([
            'driver_id' => $driver->user_id,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $request->input('accuracy'),
            'speed' => $request->input('speed'),
            'heading' => $request->input('heading'),
            'recorded_at' => $now,
        ]);
        return response()->json(['message' => 'Position mise à jour.', 'location' => ['latitude' => $lat, 'longitude' => $lng, 'recorded_at' => $now->toISOString()]]);
    }

    public function locations(Request $request, Deliverer $driver)
    {
        $limit = min(max((int) $request->input('limit', 100), 1), 1000);
        return response()->json(['locations' => DriverLocation::where('driver_id', $driver->user_id)->latest('recorded_at')->limit($limit)->get(['latitude', 'longitude', 'accuracy', 'speed', 'heading', 'recorded_at'])]);
    }

    public function location(Deliverer $driver)
    {
        return response()->json(['latitude' => $driver->latitude, 'longitude' => $driver->longitude, 'last_location_at' => $driver->last_location_at?->toISOString()]);
    }
}
