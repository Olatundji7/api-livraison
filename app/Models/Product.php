<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'description', 'price', 'stock', 'image', 'is_active'];
    protected $appends = ['image_url'];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function casts(): array
    {
        return ['price' => 'integer', 'stock' => 'integer', 'is_active' => 'boolean'];
    }

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? (request()->getSchemeAndHttpHost() . '/storage/' . ltrim($this->image, '/')) : null;
    }
}
