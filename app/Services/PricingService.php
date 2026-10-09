<?php

namespace App\Services;

use App\Models\DriverLocation;
use App\Models\Order;
use Carbon\CarbonInterface;

class PricingService
{
    /** Tarif fixe de départ en F CFA. */
    public const FRAIS_BASE = 500;

    /** Tarif par kilomètre en F CFA. */
    public const FRAIS_PAR_KM = 300;

    /** Au-delà de 3 km, réduction de 20 % sur le prix de la course. */
    public const SEUIL_REDUCTION_KM = 3.0;
    public const TAUX_REDUCTION = 0.20;

    /** Frais MA Livraison facturés au client, utilisés aussi pour absorber FedaPay. */
    public const FRAIS_SERVICE = 100;

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

    /** Prix de course avant réduction. */
    public function grossDeliveryFee(float $distanceKm): int
    {
        return (int) round(self::FRAIS_BASE + (self::FRAIS_PAR_KM * max(0, $distanceKm)));
    }

    /** Montant de la réduction (0 si distance <= 3 km). */
    public function discountAmount(float $distanceKm): int
    {
        if ($distanceKm <= self::SEUIL_REDUCTION_KM) {
            return 0;
        }

        return (int) round($this->grossDeliveryFee($distanceKm) * self::TAUX_REDUCTION);
    }

    /** Prix net de la course après réduction. Les 100 F de service sont séparés. */
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
