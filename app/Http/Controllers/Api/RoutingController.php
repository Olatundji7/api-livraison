<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * Itinéraires routiers calculés côté serveur (OSRM) pour la carte admin.
 * Évite les appels directs du navigateur au service public (blocages, limites).
 */
class RoutingController extends Controller
{
    private const BASE = 'https://router.project-osrm.org';

    /** Chemin routier entre deux points : renvoie [lat, lng] dans l'ordre. */
    public function route(Request $request)
    {
        $d = Validator::make($request->all(), [
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();

        $url = sprintf(self::BASE . '/route/v1/driving/%F,%F;%F,%F', $d['from_lng'], $d['from_lat'], $d['to_lng'], $d['to_lat']);

        $response = Http::withUserAgent('MA-Livraison/1.0')->timeout(10)->retry(1, 200)->get($url, [
            'overview' => 'full', 'geometries' => 'geojson', 'alternatives' => 'false', 'steps' => 'false',
        ]);

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            return response()->json(['message' => 'Itinéraire indisponible.'], 502);
        }

        $route = $response->json('routes.0');

        return response()->json([
            'distance_km' => round(((float) $route['distance']) / 1000, 2),
            'duration_min' => round(((float) $route['duration']) / 60, 1),
            'coordinates' => array_map(fn ($c) => [$c[1], $c[0]], $route['geometry']['coordinates'] ?? []),
        ]);
    }

    /** Recale des positions GPS sur le réseau routier (parcours d'un livreur). */
    public function match(Request $request)
    {
        $d = Validator::make($request->all(), [
            'points' => ['required', 'array', 'min:2', 'max:100'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();

        $coords = collect($d['points'])
            ->map(fn ($p) => sprintf('%F,%F', $p['lng'], $p['lat']))
            ->implode(';');

        $response = Http::withUserAgent('MA-Livraison/1.0')->timeout(15)->get(self::BASE . '/match/v1/driving/' . $coords, [
            'overview' => 'full', 'geometries' => 'geojson', 'gaps' => 'ignore', 'tidy' => 'true',
        ]);

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            return response()->json(['message' => 'Recalage indisponible.'], 502);
        }

        $lines = collect($response->json('matchings') ?? [])
            ->map(fn ($m) => array_map(fn ($c) => [$c[1], $c[0]], $m['geometry']['coordinates'] ?? []))
            ->filter(fn ($line) => count($line) >= 2)
            ->values();

        return response()->json(['lines' => $lines]);
    }
}
