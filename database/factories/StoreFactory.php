<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address_line1' => fake()->address(),
            'city' => fake()->city(),
            'postcode' => mb_strtoupper(fake()->bothify('??## #??')),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'delivery_radius_km' => fake()->randomFloat(2, 1, 50),
            'is_active' => fake()->boolean(),
            'opening_hours' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
