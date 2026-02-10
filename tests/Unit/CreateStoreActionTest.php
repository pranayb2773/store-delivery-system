<?php

declare(strict_types=1);

use App\Actions\CreateStoreAction;

it('is a readonly class', function () {
    $reflection = new ReflectionClass(CreateStoreAction::class);

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($reflection->isFinal())->toBeTrue();
});

it('has a handle method that accepts an array', function () {
    $method = new ReflectionMethod(CreateStoreAction::class, 'handle');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(1)
        ->and($method->getParameters()[0]->getType()->getName())->toBe('array')
        ->and($method->getReturnType()->getName())->toBe('App\Models\Store');
});
