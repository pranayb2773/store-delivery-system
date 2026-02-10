<?php

declare(strict_types=1);

use App\Actions\CreateStoreAction;
use App\Models\Postcode;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->postcode = Postcode::factory()->create([
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.5010090,
        'longitude' => -0.1415880,
    ]);

    $this->validPayload = [
        'name' => 'Tesco Express',
        'address_line1' => '10 Downing Street',
        'city' => 'London',
        'postcode' => 'SW1A 1AA',
    ];
});

describe('authentication & authorization', function () {
    it('returns 401 for unauthenticated requests', function () {
        $this->postJson('/api/stores', $this->validPayload)
            ->assertUnauthorized();
    });

    it('returns 403 when a non-admin user tries to create a store', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/stores', $this->validPayload)
            ->assertForbidden();
    });

    it('allows admin users to create a store', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', $this->validPayload)
            ->assertCreated();
    });
});

describe('successful store creation', function () {
    it('creates a store and returns 201 with correct json structure', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'delivery_radius_km' => 3.50,
                'is_active' => true,
                'opening_hours' => [
                    'monday' => ['open' => '09:00', 'close' => '17:00'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tesco Express')
            ->assertJsonPath('data.address_line1', '10 Downing Street')
            ->assertJsonPath('data.city', 'London')
            ->assertJsonPath('data.postcode', 'SW1A 1AA')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.opening_hours.monday.open', '09:00')
            ->assertJsonPath('data.opening_hours.monday.close', '17:00');
    });

    it('persists the store in the database with lat/lon from the postcode', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', $this->validPayload)
            ->assertCreated();

        $store = Store::query()->where('name', 'Tesco Express')->first();

        expect($store)->not->toBeNull()
            ->and((float) $store->latitude)->toBe(51.5010090)
            ->and((float) $store->longitude)->toBe(-0.1415880);
    });

    it('uses default values for optional fields when not provided', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', $this->validPayload)
            ->assertCreated();

        $store = Store::query()->where('name', 'Tesco Express')->first();

        expect($store->is_active)->toBeTrue()
            ->and((float) $store->delivery_radius_km)->toBe(5.00)
            ->and($store->opening_hours)->toBeNull();
    });
});

describe('required field validation', function () {
    it('requires name, address_line1, city, and postcode', function (string $field) {
        $admin = User::factory()->admin()->create();
        $payload = $this->validPayload;
        unset($payload[$field]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor($field);
    })->with(['name', 'address_line1', 'city', 'postcode']);

    it('validates string fields do not exceed max length', function (string $field) {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                $field => str_repeat('a', 255),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor($field);
    })->with(['name', 'address_line1', 'city']);
});

describe('postcode validation', function () {
    it('rejects a postcode that does not exist in the postcodes table', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'postcode' => 'ZZ99 9ZZ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('postcode');
    });

    it('normalizes the postcode before validation', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'postcode' => 'sw1a1aa',
            ])
            ->assertCreated();

        expect(Store::query()->where('postcode', 'SW1A 1AA')->exists())->toBeTrue();
    });
});

describe('opening hours validation', function () {
    it('accepts null opening hours', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => null,
            ])
            ->assertCreated();
    });

    it('accepts valid opening hours for all days', function () {
        $admin = User::factory()->admin()->create();

        $hours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $hours[$day] = ['open' => '09:00', 'close' => '17:00'];
        }

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => $hours,
            ])
            ->assertCreated();
    });

    it('rejects opening hours with an invalid day name', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => [
                    'funday' => ['open' => '09:00', 'close' => '17:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('opening_hours.funday');
    });

    it('rejects opening hours with invalid time format', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => [
                    'monday' => ['open' => '9am', 'close' => '5pm'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('opening_hours.monday.open')
            ->assertJsonValidationErrorFor('opening_hours.monday.close');
    });

    it('rejects opening hours where close time is before open time', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => [
                    'monday' => ['open' => '17:00', 'close' => '09:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('opening_hours.monday.close');
    });

    it('rejects opening hours missing the open key', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => [
                    'monday' => ['close' => '17:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('opening_hours.monday.open');
    });

    it('rejects opening hours missing the close key', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => [
                    'monday' => ['open' => '09:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('opening_hours.monday.close');
    });

    it('accepts opening hours for a single day only', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'opening_hours' => [
                    'friday' => ['open' => '10:00', 'close' => '22:00'],
                ],
            ])
            ->assertCreated();
    });
});

describe('delivery radius validation', function () {
    it('rejects a negative delivery radius', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'delivery_radius_km' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('delivery_radius_km');
    });

    it('rejects a delivery radius exceeding the maximum', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'delivery_radius_km' => 101,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('delivery_radius_km');
    });

    it('rejects a non-numeric delivery radius', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'delivery_radius_km' => 'far',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('delivery_radius_km');
    });
});

describe('edge cases', function () {
    it('rejects duplicate store with same name and postcode', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', $this->validPayload)
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', $this->validPayload)
            ->assertUnprocessable();
    });

    it('allows same store name with a different postcode', function () {
        Postcode::factory()->create([
            'postcode' => 'EC1A 1BB',
            'latitude' => 51.5203280,
            'longitude' => -0.0972460,
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', $this->validPayload)
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'postcode' => 'EC1A 1BB',
            ])
            ->assertCreated();

        expect(Store::query()->where('name', 'Tesco Express')->count())->toBe(2);
    });

    it('rejects is_active when it is not a boolean', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stores', [
                ...$this->validPayload,
                'is_active' => 'yes',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('is_active');
    });
});

describe('CreateStoreAction', function () {
    it('creates a store with latitude and longitude from the postcode', function () {
        $action = new CreateStoreAction;

        $store = $action->handle([
            'name' => 'Action Store',
            'address_line1' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
        ]);

        expect($store)
            ->toBeInstanceOf(Store::class)
            ->name->toBe('Action Store')
            ->postcode->toBe('SW1A 1AA')
            ->and((float) $store->latitude)->toBe(51.5010090)
            ->and((float) $store->longitude)->toBe(-0.1415880);
    });

    it('uses default delivery_radius_km when not provided', function () {
        $action = new CreateStoreAction;

        $store = $action->handle([
            'name' => 'Default Radius Store',
            'address_line1' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
        ]);

        expect((float) $store->delivery_radius_km)->toBe(5.00);
    });

    it('allows overriding delivery_radius_km', function () {
        $action = new CreateStoreAction;

        $store = $action->handle([
            'name' => 'Custom Radius Store',
            'address_line1' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'delivery_radius_km' => 10.50,
        ]);

        expect((float) $store->delivery_radius_km)->toBe(10.50);
    });

    it('stores opening hours as json', function () {
        $action = new CreateStoreAction;
        $hours = [
            'monday' => ['open' => '09:00', 'close' => '17:00'],
            'tuesday' => ['open' => '09:00', 'close' => '17:00'],
        ];

        $store = $action->handle([
            'name' => 'Hours Store',
            'address_line1' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'opening_hours' => $hours,
        ]);

        expect($store->opening_hours)->toBe($hours);
    });

    it('throws ModelNotFoundException when postcode does not exist', function () {
        $action = new CreateStoreAction;

        $action->handle([
            'name' => 'Ghost Store',
            'address_line1' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'ZZ99 9ZZ',
        ]);
    })->throws(ModelNotFoundException::class);
});
