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
            'postcode' => mb_strtoupper(fake()->bothify('??## #??')),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
