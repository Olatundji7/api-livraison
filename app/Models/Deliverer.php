<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deliverer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'photo', 'telephone', 'vehicule_type', 'immatriculation',
        'status', 'latitude', 'longitude', 'last_location_at',
        'reserved_by', 'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'last_location_at' => 'datetime',
            'reserved_until' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservedByUser()
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'disponible');
    }

    /** Une réservation est active tant que reserved_until n'est pas dépassé. */
    public function hasActiveReservation(): bool
    {
        return $this->status === 'reserve'
            && $this->reserved_until !== null
            && $this->reserved_until->isFuture();
    }
}
