<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'deliverer_id',
        'type',
        'pickup_lat',
        'pickup_lng',
        'pickup_adresse',
        'dest_lat',
        'dest_lng',
        'dest_adresse',
        'note',
        'statut',
        'distance_km',
        'prix_estime',
        'prix_final',
        'cree_le',
    ];

    protected function casts(): array
    {
        return [
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dest_lat' => 'float',
            'dest_lng' => 'float',
            'distance_km' => 'float',
            'cree_le' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function deliverer()
    {
        return $this->belongsTo(User::class, 'deliverer_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function recordStatus(string $statut): void
    {
        $this->statusHistory()->create([
            'statut' => $statut,
            'change_le' => now(),
        ]);
    }
}
