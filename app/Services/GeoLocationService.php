<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        int $page = 1,
    ): LengthAwarePaginator {
        $cacheKey = $this->buildCacheKey($latitude, $longitude, $radiusKm, $perPage, $openNow, $page);
        $ttl = $openNow ? self::OPEN_NOW_TTL_SECONDS : self::STANDARD_TTL_SECONDS;

        return Cache::remember($cacheKey, $ttl, function () use ($latitude, $longitude, $radiusKm, $perPage, $openNow): LengthAwarePaginator {
            $query = $this->applyDistanceSelect(
                Store::query()->active(),
                $latitude,
                $longitude,
            )
                ->having('distance_km', '<=', $radiusKm)
                ->orderBy('distance_km');

            if ($openNow) {
                $query->where(function (Builder $q): void {
                    $this->filterOpenStores($q);
                });
            }

            return $query->paginate($perPage);
        });
    }

    /**
     * @return Collection<int, Store>
     */
    public function findDeliveringStores(float $latitude, float $longitude, ?int $storeId = null): Collection
    {
        $cacheKey = $this->buildDeliveryCacheKey($latitude, $longitude, $storeId);

        return Cache::remember($cacheKey, self::STANDARD_TTL_SECONDS, function () use ($latitude, $longitude, $storeId): Collection {
            $query = $this->applyDistanceSelect(
                Store::query()->active(),
                $latitude,
                $longitude,
            )
                ->havingRaw('distance_km <= delivery_radius_km')
                ->orderBy('distance_km');

            if ($storeId !== null) {
                $query->where('id', $storeId);
            }

            return $query->get();
        });
    }

    private function buildDeliveryCacheKey(float $latitude, float $longitude, ?int $storeId): string
    {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 0);
        $lat = round($latitude, 4);
        $lon = round($longitude, 4);
        $storePart = $storeId ?? 'all';

        return "delivering_stores:v{$version}:{$lat}:{$lon}:{$storePart}";
    }

    private function buildCacheKey(
        float $latitude,
        float $longitude,
        float $radiusKm,
        int $perPage,
        bool $openNow,
        int $page,
    ): string {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 0);
        $lat = round($latitude, 4);
        $lon = round($longitude, 4);
        $openFlag = $openNow ? '1' : '0';

        return "nearby_stores:v{$version}:{$lat}:{$lon}:{$radiusKm}:{$perPage}:{$page}:{$openFlag}";
    }

    /**
     * @param  Builder<Store>  $query
     * @return Builder<Store>
     */
    private function applyDistanceSelect(Builder $query, float $latitude, float $longitude): Builder
    {
        return $query->selectRaw('
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
                ', [self::EARTH_RADIUS_KM, $latitude, $longitude, $latitude]);
    }

    private function filterOpenStores(Builder $query): void
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
