<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Postcode;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PostcodePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        //
    }

    public function view(User $user, Postcode $postcode): bool {}

    public function create(User $user): bool {}

    public function update(User $user, Postcode $postcode): bool {}

    public function delete(User $user, Postcode $postcode): bool {}

    public function restore(User $user, Postcode $postcode): bool {}

    public function forceDelete(User $user, Postcode $postcode): bool {}
}
