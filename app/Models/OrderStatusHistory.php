<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'statut', 'change_le'];

    protected function casts(): array
    {
        return ['change_le' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
