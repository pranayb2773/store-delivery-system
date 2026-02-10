<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
