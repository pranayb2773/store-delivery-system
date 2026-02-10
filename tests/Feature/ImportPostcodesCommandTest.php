<?php

declare(strict_types=1);

use App\Models\Postcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->csvPath = tempnam(sys_get_temp_dir(), 'postcodes_');
});

afterEach(function () {
    if (file_exists($this->csvPath)) {
        unlink($this->csvPath);
    }
});

it('fails when --path option is missing', function () {
    $this->artisan('import:postcodes')
        ->expectsOutputToContain('--path option is required')
        ->assertFailed();
});

it('fails when the file does not exist', function () {
    $this->artisan('import:postcodes', ['--path' => '/tmp/nonexistent.csv'])
        ->expectsOutputToContain('File not found')
        ->assertFailed();
});

it('imports postcodes from a valid CSV with headers', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'id,postcode,latitude,longitude',
        '1,SW1A 1AA,51.501009,-0.141588',
        '2,EC1A 1BB,51.520328,-0.097246',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    expect(Postcode::count())->toBe(2)
        ->and(Postcode::where('postcode', 'SW1A 1AA')->exists())->toBeTrue()
        ->and(Postcode::where('postcode', 'EC1A 1BB')->exists())->toBeTrue();
});

it('normalizes postcodes during import', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'postcode,latitude,longitude',
        'sw1a1aa,51.501009,-0.141588',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    expect(Postcode::where('postcode', 'SW1A 1AA')->exists())->toBeTrue();
});

it('skips rows with invalid postcode format', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'postcode,latitude,longitude',
        'INVALID,51.501009,-0.141588',
        'SW1A 1AA,51.501009,-0.141588',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    expect(Postcode::count())->toBe(1);
});

it('skips rows with non-numeric coordinates', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'postcode,latitude,longitude',
        'SW1A 1AA,not-a-number,-0.141588',
        'EC1A 1BB,51.520328,-0.097246',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    expect(Postcode::count())->toBe(1)
        ->and(Postcode::where('postcode', 'EC1A 1BB')->exists())->toBeTrue();
});

it('skips rows with out-of-range coordinates', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'postcode,latitude,longitude',
        'SW1A 1AA,91.0,-0.141588',
        'EC1A 1BB,51.520328,-181.0',
        'W1J 8EQ,51.507322,-0.142691',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    expect(Postcode::count())->toBe(1)
        ->and(Postcode::where('postcode', 'W1J 8EQ')->exists())->toBeTrue();
});

it('upserts duplicate postcodes by updating coordinates', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'postcode,latitude,longitude',
        'SW1A 1AA,51.501009,-0.141588',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    $original = Postcode::where('postcode', 'SW1A 1AA')->first();
    expect((float) $original->latitude)->toBe(51.501009);

    file_put_contents($this->csvPath, implode("\n", [
        'postcode,latitude,longitude',
        'SW1A 1AA,51.999999,-0.999999',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    expect(Postcode::where('postcode', 'SW1A 1AA')->count())->toBe(1);

    $updated = Postcode::where('postcode', 'SW1A 1AA')->first();
    expect((float)$updated->latitude)->toBe(51.999999)
        ->and((float)$updated->longitude)->toBe(-0.999999);
});

it('dispatches a batch when running in async mode', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'postcode,latitude,longitude',
        'SW1A 1AA,51.501009,-0.141588',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath])
        ->expectsOutputToContain('Batch ID:')
        ->assertSuccessful();

    $batch = Bus::findBatch(
        Illuminate\Support\Facades\DB::table('job_batches')->value('id')
    );

    expect($batch)->not->toBeNull()
        ->and($batch->name)->toBe('Import Postcodes')
        ->and($batch->totalJobs)->toBe(1);
});

it('handles CSV with alternative header names', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'pcd,lat,lng',
        'SW1A 1AA,51.501009,-0.141588',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true])
        ->assertSuccessful();

    expect(Postcode::where('postcode', 'SW1A 1AA')->exists())->toBeTrue();
});

it('handles CSV without headers using positional columns', function () {
    file_put_contents($this->csvPath, implode("\n", [
        'SW1A 1AA,51.501009,-0.141588',
        'EC1A 1BB,51.520328,-0.097246',
    ]));

    $this->artisan('import:postcodes', ['--path' => $this->csvPath, '--sync' => true, '--no-header' => true])
        ->assertSuccessful();

    expect(Postcode::count())->toBe(2);
});
