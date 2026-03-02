<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Factory\Models\Factory;
use App\Domain\Shared\Enums\Permission;
use App\Domain\Shared\Enums\Role;
use App\Models\User;

/**
 * FactoryPolicy
 *
 * Factory resource authorization.
 * Super Admin (via Gate::before) bypasses all checks.
 * Factory Admin can view and update their own factory.
 * Only Super Admin can create or delete factories.
 */
class FactoryPolicy extends BaseFactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_FACTORY->value);
    }

    public function view(User $user, Factory $factory): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_FACTORY->value);
    }

    public function create(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::CREATE_FACTORY->value);
    }

    public function update(User $user, Factory $factory): bool
    {
        return $this->hasPermissionFor($user, Permission::UPDATE_FACTORY->value);
    }

    public function delete(User $user, Factory $factory): bool
    {
        return $this->hasPermissionFor($user, Permission::DELETE_FACTORY->value)
            && $this->hasMinimumRole($user, Role::SUPER_ADMIN);
    }

    public function viewSettings(User $user, Factory $factory): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_FACTORY_SETTINGS->value);
    }

    public function updateSettings(User $user, Factory $factory): bool
    {
        return $this->hasPermissionFor($user, Permission::UPDATE_FACTORY_SETTINGS->value);
    }
}
