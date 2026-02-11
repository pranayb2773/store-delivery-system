<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Postcode;
use App\Models\Store;
use Illuminate\Database\Seeder;

final class StoreSeeder extends Seeder
{
    private const int STORE_COUNT = 10000;

    private const array OPENING_HOURS_PRESETS = [
        'standard' => [
            'monday' => ['open' => '08:00', 'close' => '20:00'],
            'tuesday' => ['open' => '08:00', 'close' => '20:00'],
            'wednesday' => ['open' => '08:00', 'close' => '20:00'],
            'thursday' => ['open' => '08:00', 'close' => '20:00'],
            'friday' => ['open' => '08:00', 'close' => '20:00'],
            'saturday' => ['open' => '09:00', 'close' => '18:00'],
            'sunday' => ['open' => '10:00', 'close' => '16:00'],
        ],
        'extended' => [
            'monday' => ['open' => '06:00', 'close' => '23:00'],
            'tuesday' => ['open' => '06:00', 'close' => '23:00'],
            'wednesday' => ['open' => '06:00', 'close' => '23:00'],
            'thursday' => ['open' => '06:00', 'close' => '23:00'],
            'friday' => ['open' => '06:00', 'close' => '23:00'],
            'saturday' => ['open' => '07:00', 'close' => '22:00'],
            'sunday' => ['open' => '10:00', 'close' => '16:00'],
        ],
        'weekdays_only' => [
            'monday' => ['open' => '09:00', 'close' => '17:00'],
            'tuesday' => ['open' => '09:00', 'close' => '17:00'],
            'wednesday' => ['open' => '09:00', 'close' => '17:00'],
            'thursday' => ['open' => '09:00', 'close' => '17:00'],
            'friday' => ['open' => '09:00', 'close' => '17:00'],
        ],
    ];

    public function run(): void
    {
        $postcodes = Postcode::query()
            ->inRandomOrder()
            ->limit(self::STORE_COUNT)
            ->get();

        if ($postcodes->isEmpty()) {
            $this->command->warn('No postcodes found. Run the import:postcodes command first.');

            return;
        }

        $openingHoursPresets = array_values(self::OPENING_HOURS_PRESETS);

        $postcodes->each(function (Postcode $postcode) use ($openingHoursPresets): void {
            Store::factory()->create([
                'postcode' => $postcode->postcode,
                'latitude' => $postcode->latitude,
                'longitude' => $postcode->longitude,
                'is_active' => fake()->boolean(85),
                'opening_hours' => fake()->optional(0.8)->randomElement($openingHoursPresets),
            ]);
        });

        $this->command->info("Seeded {$postcodes->count()} stores.");
    }
}
