<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'telephone',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function deliverer()
    {
        return $this->hasOne(Deliverer::class);
    }

    public function ordersAsClient()
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function ordersAsDeliverer()
    {
        return $this->hasMany(Order::class, 'deliverer_id');
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isLivreur(): bool
    {
        return $this->role === 'livreur';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
