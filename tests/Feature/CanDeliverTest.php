<?php

declare(strict_types=1);

use App\Models\Postcode;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        $this->getJson('/api/stores/can-deliver?postcode=SW1A+1AA')
            ->assertUnauthorized();
    });
});

describe('validation', function () {
    it('requires a postcode', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('postcode');
    });

    it('rejects a postcode that does not exist in the postcodes table', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=ZZ99+9ZZ')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('postcode');
    });

    it('rejects an invalid store_id', function (mixed $value) {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA&store_id='.$value)
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('store_id');
    })->with(['abc', 999999]);
});

describe('with store_id', function () {
    it('returns can_deliver true when store is within delivery radius', function () {
        // ~2km from SW1A 1AA, delivery radius 5km — can deliver
        $store = Store::factory()->create([
            'name' => 'Close Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'delivery_radius_km' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA&store_id='.$store->id)
            ->assertSuccessful();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.can_deliver', true)
            ->assertJsonCount(1, 'data.stores')
            ->assertJsonPath('data.stores.0.name', 'Close Store');

        expect($response->json('data.stores.0.distance_km'))->toBeNumeric();
    });

    it('returns can_deliver false when store is outside delivery radius', function () {
        // ~2km from SW1A 1AA, delivery radius 1km — cannot deliver
        $store = Store::factory()->create([
            'name' => 'Close But Small Radius',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'delivery_radius_km' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA&store_id='.$store->id)
            ->assertSuccessful();

        $response->assertJsonPath('data.can_deliver', false)
            ->assertJsonCount(0, 'data.stores');
    });
});

describe('without store_id', function () {
    it('returns all stores that can deliver', function () {
        // ~2km away, 5km radius — can deliver
        Store::factory()->create([
            'name' => 'Delivering Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'delivery_radius_km' => 5.00,
            'is_active' => true,
        ]);

        // ~2km away, 1km radius — cannot deliver
        Store::factory()->create([
            'name' => 'Non-Delivering Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'delivery_radius_km' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA')
            ->assertSuccessful();

        $response->assertJsonPath('data.can_deliver', true)
            ->assertJsonCount(1, 'data.stores');

        $storeNames = collect($response->json('data.stores'))->pluck('name');
        expect($storeNames)->toContain('Delivering Store')
            ->and($storeNames)->not->toContain('Non-Delivering Store');
    });

    it('returns can_deliver false when no stores can deliver', function () {
        // ~50km away, 5km radius — cannot deliver
        Store::factory()->create([
            'name' => 'Far Store',
            'latitude' => 51.4543,
            'longitude' => -0.9781,
            'delivery_radius_km' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA')
            ->assertSuccessful();

        $response->assertJsonPath('data.can_deliver', false)
            ->assertJsonCount(0, 'data.stores');
    });
});

describe('filtering', function () {
    it('only returns active stores', function () {
        Store::factory()->create([
            'name' => 'Active Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'delivery_radius_km' => 5.00,
            'is_active' => true,
        ]);

        Store::factory()->create([
            'name' => 'Inactive Store',
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'delivery_radius_km' => 5.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA')
            ->assertSuccessful();

        $storeNames = collect($response->json('data.stores'))->pluck('name');
        expect($storeNames)->toContain('Active Store')
            ->and($storeNames)->not->toContain('Inactive Store');
    });
});

describe('response structure', function () {
    it('includes search_location with postcode and coordinates', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA')
            ->assertSuccessful();

        $searchLocation = $response->json('data.search_location');
        expect($searchLocation)->toHaveKeys(['postcode', 'latitude', 'longitude'])
            ->and($searchLocation['postcode'])->toBe('SW1A 1AA')
            ->and((float) $searchLocation['latitude'])->toBe(51.501009)
            ->and((float) $searchLocation['longitude'])->toBe(-0.141588);
    });

    it('includes distance_km in each store resource', function () {
        Store::factory()->create([
            'latitude' => 51.5035,
            'longitude' => -0.1130,
            'delivery_radius_km' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=SW1A+1AA')
            ->assertSuccessful();

        $stores = $response->json('data.stores');
        expect($stores[0])->toHaveKey('distance_km')
            ->and($stores[0]['distance_km'])->toBeNumeric();
    });

    it('normalizes postcode before lookup', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stores/can-deliver?postcode=sw1a1aa')
            ->assertSuccessful()
            ->assertJsonPath('data.search_location.postcode', 'SW1A 1AA');
    });
});
