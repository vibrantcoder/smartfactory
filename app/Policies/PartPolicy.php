<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Production\Models\Part;
use App\Domain\Shared\Enums\Permission;
use App\Models\User;

class PartPolicy extends BaseFactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_PART->value);
    }

    public function view(User $user, Part $part): bool
    {
        return $this->belongsToSameFactory($user, $part)
            && $this->hasPermissionFor($user, Permission::VIEW_PART->value);
    }

    public function create(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::CREATE_PART->value);
    }

    public function update(User $user, Part $part): bool
    {
        return $this->belongsToSameFactory($user, $part)
            && $this->hasPermissionFor($user, Permission::UPDATE_PART->value);
    }

    public function delete(User $user, Part $part): bool
    {
        return $this->belongsToSameFactory($user, $part)
            && $this->hasPermissionFor($user, Permission::DELETE_PART->value);
    }

    public function syncProcesses(User $user, Part $part): bool
    {
        return $this->belongsToSameFactory($user, $part)
            && $this->hasPermissionFor($user, Permission::UPDATE_PART->value);
    }
}
