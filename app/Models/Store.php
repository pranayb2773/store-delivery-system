<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\GeoLocationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address_line1',
        'city',
        'postcode',
        'latitude',
        'longitude',
        'delivery_radius_km',
        'is_active',
        'opening_hours',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        $invalidateCache = function (): void {
            $current = (int) Cache::get(GeoLocationService::CACHE_VERSION_KEY, 0);
            Cache::forever(GeoLocationService::CACHE_VERSION_KEY, $current + 1);
        };

        self::created($invalidateCache);
        self::updated($invalidateCache);
        self::deleted($invalidateCache);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_hours' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'delivery_radius_km' => 'decimal:2',
        ];
    }
}
