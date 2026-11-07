<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id', 'title', 'description', 'image',
        'is_sponsored', 'is_active', 'starts_at', 'ends_at',
    ];

    protected $with = ['partner'];
    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sponsored' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function partner()
    {
        return $this->belongsTo(AdvertisementPartner::class, 'partner_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? url('api/media/' . ltrim($this->image, '/')) : null;
    }

    public function scopeCurrentlyVisible($query)
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
