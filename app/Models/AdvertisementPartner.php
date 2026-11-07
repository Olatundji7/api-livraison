<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementPartner extends Model
{
    protected $fillable = [
        'name', 'business_category', 'description', 'logo',
        'contact_phone', 'contact_whatsapp', 'contact_email', 'website_url',
    ];

    protected $appends = ['logo_url'];

    public function advertisements()
    {
        return $this->hasMany(Advertisement::class, 'partner_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? url('api/media/' . ltrim($this->logo, '/')) : null;
    }
}
