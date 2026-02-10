<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Postcode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class PostcodeFactory extends Factory
{
    protected $model = Postcode::class;

    public function definition(): array
    {
        return [
            'postcode' => $this->faker->postcode(), //
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
