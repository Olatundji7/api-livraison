<?php

namespace App\Services;

class PricingService
{
    /** Tarif de base fixe, en Francs CFA. */
    public const TARIF_BASE = 500;

    /** Tarif par kilomètre, en Francs CFA. */
    public const TARIF_PAR_KM = 300;

    /**
     * Distance à vol d'oiseau entre deux points GPS, en kilomètres.
     * Formule de Haversine.
     */
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }

    /**
     * Prix en F CFA pour une distance donnée : tarif de base + tarif/km.
     */
    public function prix(float $distanceKm): int
    {
        return (int) round(self::TARIF_BASE + (self::TARIF_PAR_KM * $distanceKm));
    }
}
