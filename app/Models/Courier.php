<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    protected $fillable = ['name', 'code', 'tracking_url_template', 'website_url', 'logo_path', 'is_active', 'supports_api', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'supports_api' => 'boolean', 'sort_order' => 'integer'];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function trackingUrl(?string $trackingNumber): ?string
    {
        if (blank($this->tracking_url_template) || blank($trackingNumber)) {
            return null;
        }

        $url = str_replace('{tracking_number}', rawurlencode(trim($trackingNumber)), $this->tracking_url_template);

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
