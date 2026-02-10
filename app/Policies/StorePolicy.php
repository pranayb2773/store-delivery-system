<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class StorePolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
