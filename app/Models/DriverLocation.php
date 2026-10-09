<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'driver_id', 'latitude', 'longitude', 'accuracy', 'speed', 'heading', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'speed' => 'float',
            'heading' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
