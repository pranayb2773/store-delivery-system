<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: int
{
    case Admin = 1;
    case User = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }
}
