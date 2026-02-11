<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final readonly class GeoLocationService
{
    private const int EARTH_RADIUS_KM = 6371;

    public function findNearbyStores(
        float $latitude,
        float $longitude,
        float $radiusKm,
        int $perPage = 10,
        bool $openNow = false,
    ): LengthAwarePaginator {
        $query = Store::query()
            ->active()
            ->selectRaw('
                *,
                (
                    ? * acos(
                        cos(radians(?))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(latitude))
                    )
                ) AS distance_km
            ', [self::EARTH_RADIUS_KM, $latitude, $longitude, $latitude])
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');

        if ($openNow) {
            $query->where(function ($q): void {
                $this->filterOpenStores($q);
            });
        }

        return $query->paginate($perPage);
    }

    private function filterOpenStores($query): void
    {
        $now = Carbon::now();
        $dayOfWeek = mb_strtolower($now->englishDayOfWeek);
        $currentTime = $now->format('H:i');

        $query->whereRaw("
            JSON_EXTRACT(opening_hours, '$.{$dayOfWeek}.open') IS NOT NULL
            AND JSON_EXTRACT(opening_hours, '$.{$dayOfWeek}.open') <= ?
            AND JSON_EXTRACT(opening_hours, '$.{$dayOfWeek}.close') >= ?
        ", [$currentTime, $currentTime]);
    }
}
