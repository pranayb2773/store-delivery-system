<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Postcode;
use App\Services\GeoLocationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class GetNearByStoresAction
{
    public function __construct(private GeoLocationService $geoLocationService) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{search_location: array<string, mixed>, stores: LengthAwarePaginator}
     */
    public function handle(array $data): array
    {
        $postcode = Postcode::query()
            ->where('postcode', $data['postcode'])
            ->firstOrFail();

        $radiusKm = (float) ($data['radius'] ?? 10);
        $perPage = (int) ($data['per_page'] ?? 10);
        $openNow = (bool) ($data['open_now'] ?? false);

        $stores = $this->geoLocationService->findNearbyStores(
            latitude: (float) $postcode->latitude,
            longitude: (float) $postcode->longitude,
            radiusKm: $radiusKm,
            perPage: $perPage,
            openNow: $openNow,
        );

        return [
            'search_location' => [
                'postcode' => $data['postcode'],
                'latitude' => $postcode->latitude,
                'longitude' => $postcode->longitude,
                'radius_km' => $radiusKm,
            ],
            'stores' => $stores,
        ];
    }
}
