<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'factory-admin']);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasRole(['super-admin', 'factory-admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('manage-role', $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasRole('super-admin');
    }
}
