<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statut' => $this->statut,
            'type' => $this->type,
            'pickup_adresse' => $this->pickup_adresse,
            'dest_adresse' => $this->dest_adresse,
            'distance_km' => $this->distance_km,
            'prix_estime' => $this->prix_estime,
            'prix_final' => $this->prix_final,
            'cree_le' => $this->cree_le?->toISOString(),
            'deliverer' => $this->when($this->deliverer_id !== null, function () {
                return [
                    'id' => $this->deliverer->id,
                    'nom' => $this->deliverer->name,
                    'telephone' => $this->deliverer->telephone,
                    'position_lat' => $this->deliverer->deliverer?->position_lat,
                    'position_lng' => $this->deliverer->deliverer?->position_lng,
                ];
            }),
        ];
    }
}
