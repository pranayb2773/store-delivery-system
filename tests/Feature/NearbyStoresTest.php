<?php

declare(strict_types=1);

use App\Models\Postcode;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Search location: Westminster (SW1A 1AA)
    $this->searchPostcode = Postcode::factory()->create([
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.5010090,
        'longitude' => -0.1415880,
    ]);

    $this->user = User::factory()->create();
});

describe('authentication', function () {
    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/stores/nearby?postcode=SW1A+1AA')
            ->assertUnauthorized();
    });
});

describe('validation', function () {
    it('requires a postcode', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('postcode');
    });

    it('rejects a postcode that does not exist in the postcodes table', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=ZZ99+9ZZ')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('postcode');
    });

    it('rejects an invalid radius', function (mixed $value) {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&radius='.$value)
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('radius');
    })->with([0, -1, 101, 'abc']);

    it('rejects an invalid per_page', function (mixed $value) {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&per_page='.$value)
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('per_page');
    })->with([0, -1, 101, 'abc']);

    it('rejects an invalid page', function (mixed $value) {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&page='.$value)
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('page');
    })->with([0, -1, 'abc']);

    it('rejects an invalid open_now value', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&open_now=yes')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('open_now');
    });
});

describe('successful nearby store lookup', function () {
    it('returns stores sorted by distance within the radius', function () {
        // ~1.5km from SW1A 1AA (Waterloo area)
        Store::factory()->create([
            'name' => 'Near Store',
            'postcode' => 'SE1 7PB',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'is_active' => true,
        ]);

        // ~3.5km from SW1A 1AA (Camden area)
        Store::factory()->create([
            'name' => 'Medium Store',
            'postcode' => 'NW1 0NE',
            'latitude' => 51.5340,
            'longitude' => -0.1390,
            'is_active' => true,
        ]);

        // ~50km away - outside default radius
        Store::factory()->create([
            'name' => 'Far Store',
            'postcode' => 'RG1 1AA',
            'latitude' => 51.4543,
            'longitude' => -0.9781,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA')
            ->assertSuccessful();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.search_location.postcode', 'SW1A 1AA')
            ->assertJsonPath('data.pagination.total', 2);

        $stores = $response->json('data.stores');
        expect($stores[0]['name'])->toBe('Near Store')
            ->and($stores[1]['name'])->toBe('Medium Store')
            ->and($stores[0]['distance_km'])->toBeLessThan($stores[1]['distance_km']);
    });

    it('includes latitude and longitude in search_location', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA')
            ->assertSuccessful();

        $searchLocation = $response->json('data.search_location');
        expect((float) $searchLocation['latitude'])->toBe(51.501009)
            ->and((float) $searchLocation['longitude'])->toBe(-0.141588);
    });

    it('includes distance_km in each store resource', function () {
        Store::factory()->create([
            'name' => 'Nearby Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA')
            ->assertSuccessful();

        $stores = $response->json('data.stores');
        expect($stores[0])->toHaveKey('distance_km')
            ->and($stores[0]['distance_km'])->toBeNumeric();
    });
});

describe('filtering', function () {
    it('only returns active stores', function () {
        Store::factory()->create([
            'name' => 'Active Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'is_active' => true,
        ]);

        Store::factory()->create([
            'name' => 'Inactive Store',
            'latitude' => 51.5040,
            'longitude' => -0.1135,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA')
            ->assertSuccessful();

        $storeNames = collect($response->json('data.stores'))->pluck('name');
        expect($storeNames)->toContain('Active Store')
            ->and($storeNames)->not->toContain('Inactive Store');
    });

    it('respects the radius filter', function () {
        // ~2km away
        Store::factory()->create([
            'name' => 'Close Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'is_active' => true,
        ]);

        // ~3.5km away
        Store::factory()->create([
            'name' => 'Further Store',
            'latitude' => 51.5340,
            'longitude' => -0.1390,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&radius=2.5')
            ->assertSuccessful();

        $storeNames = collect($response->json('data.stores'))->pluck('name');
        expect($storeNames)->toContain('Close Store')
            ->and($storeNames)->not->toContain('Further Store');
    });

    it('filters by open_now correctly', function () {
        $now = Carbon::now();
        $currentDay = mb_strtolower($now->format('l'));

        Store::factory()->create([
            'name' => 'Open Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'is_active' => true,
            'opening_hours' => [
                $currentDay => ['open' => '00:00', 'close' => '23:59'],
            ],
        ]);

        Store::factory()->create([
            'name' => 'Closed Store',
            'latitude' => 51.5040,
            'longitude' => -0.1135,
            'is_active' => true,
            'opening_hours' => [
                $currentDay => ['open' => '00:00', 'close' => '00:01'],
            ],
        ]);

        // Freeze time to noon so "Open Store" is open and "Closed Store" is closed
        Carbon::setTestNow($now->copy()->setTime(12, 0));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&open_now=1')
            ->assertSuccessful();

        $storeNames = collect($response->json('data.stores'))->pluck('name');
        expect($storeNames)->toContain('Open Store')
            ->and($storeNames)->not->toContain('Closed Store');

        Carbon::setTestNow();
    });

    it('excludes stores with no opening hours when open_now is true', function () {
        Store::factory()->create([
            'name' => 'No Hours Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'is_active' => true,
            'opening_hours' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&open_now=1')
            ->assertSuccessful();

        expect($response->json('data.pagination.total'))->toBe(0);
    });
});

describe('pagination', function () {
    it('paginates results with per_page parameter', function () {
        for ($i = 0; $i < 5; $i++) {
            Store::factory()->create([
                'name' => "Store {$i}",
                'latitude' => 51.5010 + ($i * 0.001),
                'longitude' => -0.1416,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&per_page=2')
            ->assertSuccessful();

        expect($response->json('data.stores'))->toHaveCount(2)
            ->and($response->json('data.pagination.total'))->toBe(5)
            ->and($response->json('data.pagination.per_page'))->toBe(2)
            ->and($response->json('data.pagination.current_page'))->toBe(1)
            ->and($response->json('data.pagination.last_page'))->toBe(3);
    });

    it('returns the correct page when page parameter is provided', function () {
        for ($i = 0; $i < 5; $i++) {
            Store::factory()->create([
                'name' => "Store {$i}",
                'latitude' => 51.5010 + ($i * 0.001),
                'longitude' => -0.1416,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&per_page=2&page=2')
            ->assertSuccessful();

        expect($response->json('data.stores'))->toHaveCount(2)
            ->and($response->json('data.pagination.current_page'))->toBe(2);
    });

    it('returns the last page with remaining items', function () {
        for ($i = 0; $i < 5; $i++) {
            Store::factory()->create([
                'name' => "Store {$i}",
                'latitude' => 51.5010 + ($i * 0.001),
                'longitude' => -0.1416,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&per_page=2&page=3')
            ->assertSuccessful();

        expect($response->json('data.stores'))->toHaveCount(1)
            ->and($response->json('data.pagination.current_page'))->toBe(3)
            ->and($response->json('data.pagination.last_page'))->toBe(3);
    });

    it('uses default per_page of 10 when not provided', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA')
            ->assertSuccessful()
            ->assertJsonPath('data.pagination.per_page', 10);
    });
});

describe('edge cases', function () {
    it('returns empty results when no stores are within radius', function () {
        Store::factory()->create([
            'name' => 'Far Away Store',
            'latitude' => 53.4808,
            'longitude' => -2.2426,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA&radius=1')
            ->assertSuccessful();

        expect($response->json('data.pagination.total'))->toBe(0)
            ->and($response->json('data.stores'))->toBeEmpty();
    });

    it('normalizes postcode before lookup', function () {
        Store::factory()->create([
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'is_active' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=sw1a1aa')
            ->assertSuccessful()
            ->assertJsonPath('data.search_location.postcode', 'SW1A 1AA');
    });

    it('uses default radius when not provided', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/nearby?postcode=SW1A+1AA')
            ->assertSuccessful()
            ->assertJsonPath('data.search_location.radius_km', 10);
    });
});
