<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class StorePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        //
    }

    public function view(User $user, Store $store): bool {}

    public function create(User $user): bool {}

    public function update(User $user, Store $store): bool {}

    public function delete(User $user, Store $store): bool {}

    public function restore(User $user, Store $store): bool {}

    public function forceDelete(User $user, Store $store): bool {}
}
