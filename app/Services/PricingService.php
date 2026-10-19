<?php

namespace App\Services;

use App\Models\DriverLocation;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

class PricingService
{
    /** Seuils tarifaires de livraison en F CFA. */
    public const FRAIS_SERVICE = 0;
    public const SEUIL_REDUCTION_KM = 4.0;
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

    /**
     * Tarif avant réduction selon les tranches convenues.
     * 0–1,5 km : 600 F ; >1,5–2 km : 700 F ; >2–3 km : 1 000 F.
     * Au-delà de 3 km : 1 000 F + 300 F par kilomètre entamé supplémentaire.
     */
    public function grossDeliveryFee(float $distanceKm): int
    {
        $distanceKm = max(0, $distanceKm);

        if ($distanceKm <= 1.5) {
            return 600;
        }
        if ($distanceKm <= 2.0) {
            return 700;
        }
        if ($distanceKm <= 3.0) {
            return 1000;
        }

        return 1000 + (300 * (int) ceil($distanceKm - 3.0));
    }

    /** Montant de la réduction (0 si distance < 4 km ; 20 % dès 4 km). */
    public function discountAmount(float $distanceKm): int
    {
        if ($distanceKm < self::SEUIL_REDUCTION_KM) {
            return 0;
        }

        return (int) round($this->grossDeliveryFee($distanceKm) * self::TAUX_REDUCTION);
    }

    /** Prix net de la course après réduction. Aucun frais de service fixe n’est ajouté. */
    public function deliveryFee(float $distanceKm): int
    {
        return max(0, $this->grossDeliveryFee($distanceKm) - $this->discountAmount($distanceKm));
    }

    /**
     * Distance routière estimée depuis MA Livraison jusqu’au client via OSRM.
     * Échoue explicitement si le service de routage ne répond pas : ne pas facturer
     * sur une distance à vol d’oiseau présentée à tort comme une distance routière.
     */
    public function roadDistanceKm(float $destLat, float $destLng): float
    {
        $originLat = 9.3512777053658;
        $originLng = 2.6290550914044717;
        $url = sprintf(
            'https://router.project-osrm.org/route/v1/driving/%F,%F;%F,%F',
            $originLng, $originLat, $destLng, $destLat
        );
        $response = Http::timeout(8)->retry(1, 200)->get($url, [
            'overview' => 'false', 'alternatives' => 'false', 'steps' => 'false',
        ]);
        if (! $response->successful() || $response->json('code') !== 'Ok') {
            throw new \RuntimeException('Le calcul de l’itinéraire routier est indisponible. Réessayez avant de valider la commande.');
        }
        $meters = $response->json('routes.0.distance');
        if (! is_numeric($meters) || (float) $meters <= 0) {
            throw new \RuntimeException('Aucun itinéraire routier valide trouvé pour cette destination.');
        }
        return round(((float) $meters) / 1000, 2);
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
