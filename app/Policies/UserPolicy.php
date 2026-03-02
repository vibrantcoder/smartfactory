<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Shared\Enums\Permission;
use App\Models\User;

class UserPolicy extends BaseFactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_USER->value);
    }

    public function view(User $user, User $target): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_USER->value);
    }

    public function create(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::CREATE_USER->value);
    }

    public function update(User $user, User $target): bool
    {
        return $this->hasPermissionFor($user, Permission::UPDATE_USER->value);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->hasPermissionFor($user, Permission::DELETE_USER->value);
    }

    public function assignRole(User $user, User $target): bool
    {
        return $this->hasPermissionFor($user, Permission::ASSIGN_ROLE_USER->value);
    }

    public function revokeRole(User $user, User $target): bool
    {
        return $this->hasPermissionFor($user, Permission::REVOKE_ROLE_USER->value);
    }
}
