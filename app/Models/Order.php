<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'driver_id', 'type', 'status',
        'pickup_address', 'pickup_latitude', 'pickup_longitude',
        'destination_address', 'destination_latitude', 'destination_longitude',
        'note', 'subtotal', 'delivery_fee', 'gross_delivery_fee', 'discount_amount',
        'service_fee', 'fedapay_fee', 'net_service_revenue', 'distance_travelled_km',
        'total', 'payment_status',
        'accepted_at', 'started_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'distance_travelled_km' => 'float',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected $appends = ['net_course_amount'];

    public function getNetCourseAmountAttribute(): int
    {
        return (int) $this->delivery_fee;
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public const STATUTS_FINAUX = ['livree', 'annulee', 'refusee', 'echec'];

    public function estTermine(): bool
    {
        return in_array($this->status, self::STATUTS_FINAUX, true);
    }
}
