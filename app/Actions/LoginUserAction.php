<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class LoginUserAction
{
    public function handle(array $credentials): array
    {
        $this->ensureIsNotRateLimited($credentials);

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($this->throttleKey($credentials));

            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }

        RateLimiter::clear($this->throttleKey($credentials));

        $user = Auth::user();

        // Delete existing tokens
        $user->tokens()->delete();

        // Generate a new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    private function ensureIsNotRateLimited(array $credentials): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($credentials), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [
                'Too many login attempts. Please try again in ',
                RateLimiter::availableIn($this->throttleKey($credentials)).' seconds.',
            ],
        ]);
    }

    private function throttleKey(array $credentials): string
    {
        return Str::transliterate(Str::lower($credentials['email']).'|'.request()->ip());
    }
}
