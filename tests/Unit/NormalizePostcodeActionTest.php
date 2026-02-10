<?php

declare(strict_types=1);

use App\Actions\NormalizePostcodeAction;

beforeEach(function () {
    $this->action = new NormalizePostcodeAction;
});

it('trims whitespace from postcodes', function () {
    expect($this->action->handle('  SW1A 1AA  '))->toBe('SW1A 1AA');
});

it('converts postcodes to uppercase', function () {
    expect($this->action->handle('sw1a 1aa'))->toBe('SW1A 1AA');
});

it('collapses multiple spaces into a single space', function () {
    expect($this->action->handle('SW1A   1AA'))->toBe('SW1A 1AA');
});

it('inserts a space before the last 3 characters when missing', function () {
    expect($this->action->handle('SW1A1AA'))->toBe('SW1A 1AA');
});

it('returns an already normalized postcode unchanged', function () {
    expect($this->action->handle('EC1A 1BB'))->toBe('EC1A 1BB');
});

it('normalizes various postcode formats', function (string $input, string $expected) {
    expect($this->action->handle($input))->toBe($expected);
})->with([
    'short format' => ['m11ae', 'M1 1AE'],
    'medium format' => ['b338th', 'B33 8TH'],
    'long format' => ['ec1a1bb', 'EC1A 1BB'],
    'extra spaces' => ['  W1J   8EQ  ', 'W1J 8EQ'],
]);
