<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final readonly class GeoLocationService
{
    public const string CACHE_VERSION_KEY = 'nearby_stores_version';

    private const int EARTH_RADIUS_KM = 6371;

    private const int STANDARD_TTL_SECONDS = 1800;

    private const int OPEN_NOW_TTL_SECONDS = 300;

    public function findNearbyStores(
        float $latitude,
        float $longitude,
        float $radiusKm,
        int $perPage = 10,
        bool $openNow = false,
    ): LengthAwarePaginator {
        $cacheKey = $this->buildCacheKey($latitude, $longitude, $radiusKm, $perPage, $openNow);
        $ttl = $openNow ? self::OPEN_NOW_TTL_SECONDS : self::STANDARD_TTL_SECONDS;

        return Cache::remember($cacheKey, $ttl, function () use ($latitude, $longitude, $radiusKm, $perPage, $openNow): LengthAwarePaginator {
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
        });
    }

    private function buildCacheKey(
        float $latitude,
        float $longitude,
        float $radiusKm,
        int $perPage,
        bool $openNow,
    ): string {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 0);
        $lat = round($latitude, 4);
        $lon = round($longitude, 4);
        $page = request()->integer('page', 1);
        $openFlag = $openNow ? '1' : '0';

        return "nearby_stores:v{$version}:{$lat}:{$lon}:{$radiusKm}:{$perPage}:{$page}:{$openFlag}";
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
