<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Postcode;
use App\Models\Store;

final readonly class CreateStoreAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Store
    {
        $postcode = Postcode::query()
            ->where('postcode', $data['postcode'])
            ->firstOrFail();

        return Store::query()->create([
            ...$data,
            'latitude' => $postcode->latitude,
            'longitude' => $postcode->longitude,
        ])->refresh();
    }
}
