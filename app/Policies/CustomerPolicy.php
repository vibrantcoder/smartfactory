<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Production\Models\Customer;
use App\Domain\Shared\Enums\Permission;
use App\Models\User;

class CustomerPolicy extends BaseFactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_CUSTOMER->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->belongsToSameFactory($user, $customer)
            && $this->hasPermissionFor($user, Permission::VIEW_CUSTOMER->value);
    }

    public function create(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::CREATE_CUSTOMER->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->belongsToSameFactory($user, $customer)
            && $this->hasPermissionFor($user, Permission::UPDATE_CUSTOMER->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->belongsToSameFactory($user, $customer)
            && $this->hasPermissionFor($user, Permission::DELETE_CUSTOMER->value);
    }
}
