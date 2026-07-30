<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ProductTag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'sort_order', 'is_active'];
    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (self $tag): void {
            $tag->slug ??= Str::slug($tag->name);
        });
        static::saved(fn () => Cache::forget('catalogue:filter-options'));
        static::deleted(fn () => Cache::forget('catalogue:filter-options'));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
    public function scopeOrdered(Builder $query): Builder { return $query->orderBy('sort_order')->orderBy('name'); }
}
