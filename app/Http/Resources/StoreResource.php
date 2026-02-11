<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Store */
final class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address_line1' => $this->address_line1,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'delivery_radius_km' => $this->delivery_radius_km,
            'is_active' => $this->is_active,
            'opening_hours' => $this->opening_hours,
            'distance_km' => $this->when($this->resource->getAttribute('distance_km') !== null, fn () => round((float) $this->resource->getAttribute('distance_km'), 2)),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
