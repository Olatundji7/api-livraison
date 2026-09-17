<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deliverer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'statut_validation',
        'disponible',
        'position_lat',
        'position_lng',
        'piece_identite_url',
    ];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
            'position_lat' => 'float',
            'position_lng' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDisponible($query)
    {
        return $query->where('disponible', true)
                      ->where('statut_validation', 'valide');
    }
}
