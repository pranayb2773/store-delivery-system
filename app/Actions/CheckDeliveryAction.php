<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Postcode;
use App\Services\GeoLocationService;
use Illuminate\Database\Eloquent\Collection;

final readonly class CheckDeliveryAction
{
    public function __construct(private GeoLocationService $geoLocationService) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{search_location: array<string, mixed>, can_deliver: bool, stores: Collection}
     */
    public function handle(array $data): array
    {
        $postcode = Postcode::query()
            ->where('postcode', $data['postcode'])
            ->firstOrFail();

        $storeId = isset($data['store_id']) ? (int) $data['store_id'] : null;

        $stores = $this->geoLocationService->findDeliveringStores(
            latitude: (float) $postcode->latitude,
            longitude: (float) $postcode->longitude,
            storeId: $storeId,
        );

        return [
            'search_location' => [
                'postcode' => $data['postcode'],
                'latitude' => $postcode->latitude,
                'longitude' => $postcode->longitude,
            ],
            'can_deliver' => $stores->isNotEmpty(),
            'stores' => $stores,
        ];
    }
}
