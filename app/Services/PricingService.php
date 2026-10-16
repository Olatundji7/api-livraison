<?php

namespace App\Services;

use App\Models\DriverLocation;
use App\Models\Order;
use Carbon\CarbonInterface;

class PricingService
{
    /** Frais de service supprimés : le client ne paie aucun forfait supplémentaire. */
    public const FRAIS_SERVICE = 0;

    /** Réduction appliquée uniquement au-delà de 3 km. */
    public const SEUIL_REDUCTION_KM = 3.0;
    public const TAUX_REDUCTION = 0.20;

    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadiusKm * $c, 2);
    }

    /** Tarif brut selon les tranches convenues, avant la réduction au-delà de 3 km. */
    public function grossDeliveryFee(float $distanceKm): int
    {
        $distanceKm = max(0, $distanceKm);
        if ($distanceKm <= 1.5) return 600;
        if ($distanceKm <= 2.0) return 700;
        if ($distanceKm <= 3.0) return 1000;

        return 1000 + (300 * (int) ceil($distanceKm - 3.0));
    }

    /** Réduction de 20 % du tarif brut uniquement au-delà de 3 km. */
    public function discountAmount(float $distanceKm): int
    {
        if ($distanceKm <= self::SEUIL_REDUCTION_KM) return 0;
        return (int) round($this->grossDeliveryFee($distanceKm) * self::TAUX_REDUCTION);
    }

    /** Montant final de la course, sans frais de service. */
    public function deliveryFee(float $distanceKm): int
    {
        return max(0, $this->grossDeliveryFee($distanceKm) - $this->discountAmount($distanceKm));
    }

    /**
     * Somme réelle des segments GPS parcourus par le livreur pour la commande.
     * On ne calcule jamais la distance finale par une simple ligne droite.
     */
    public function travelledDistanceKm(Order $order, ?CarbonInterface $endAt = null): float
    {
        if (! $order->driver_id || ! $order->accepted_at) {
            return 0.0;
        }

        $end = $endAt ?? ($order->completed_at ?? now());

        $locations = DriverLocation::query()
            ->where('driver_id', $order->driver_id)
            ->where('recorded_at', '>=', $order->accepted_at)
            ->where('recorded_at', '<=', $end)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'recorded_at']);

        if ($locations->count() < 2) {
            // Pas assez de points GPS : impossible de prétendre connaître
            // la distance réellement parcourue. Le contrôleur demandera
            // au livreur de poursuivre le partage GPS avant de clôturer.
            return 0.0;
        }

        $total = 0.0;
        $previous = null;
        foreach ($locations as $location) {
            if ($previous !== null) {
                $total += $this->distanceKm(
                    (float) $previous->latitude,
                    (float) $previous->longitude,
                    (float) $location->latitude,
                    (float) $location->longitude,
                );
            }
            $previous = $location;
        }

        return round($total, 2);
    }
}
