<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Machine\Models\Machine;
use App\Domain\Shared\Enums\Permission;
use App\Domain\Shared\Enums\Role;
use App\Models\User;

/**
 * MachinePolicy
 *
 * Factory-scoped authorization for Machine resources.
 *
 * REGISTRATION (AppServiceProvider or AuthServiceProvider):
 *   Gate::policy(Machine::class, MachinePolicy::class);
 *
 * CONTROLLER USAGE:
 *   $this->authorize('view', $machine);
 *   $this->authorize('create', Machine::class);
 *
 * The `before()` method in BaseFactoryPolicy handles:
 *   - Deactivated user denial
 *   - Super Admin bypass
 */
class MachinePolicy extends BaseFactoryPolicy
{
    /**
     * List machines (factory-scoped).
     * Every role with the permission can list machines in their factory.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_MACHINE->value);
    }

    /**
     * View a specific machine.
     * Additional guard: machine must belong to user's factory.
     */
    public function view(User $user, Machine $machine): bool
    {
        return $this->belongsToSameFactory($user, $machine)
            && $this->hasPermissionFor($user, Permission::VIEW_MACHINE->value);
    }

    /**
     * Create a machine within the user's factory.
     * Only factory-admin and above by default.
     */
    public function create(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::CREATE_MACHINE->value);
    }

    /**
     * Update a machine.
     * Machine must belong to user's factory.
     */
    public function update(User $user, Machine $machine): bool
    {
        return $this->belongsToSameFactory($user, $machine)
            && $this->hasPermissionFor($user, Permission::UPDATE_MACHINE->value);
    }

    /**
     * Delete a machine.
     * Only factory-admin and above.
     * Additional guard: cannot delete a machine with recent logs (application logic).
     */
    public function delete(User $user, Machine $machine): bool
    {
        return $this->belongsToSameFactory($user, $machine)
            && $this->hasPermissionFor($user, Permission::DELETE_MACHINE->value)
            && $this->hasMinimumRole($user, Role::FACTORY_ADMIN);
    }

    /**
     * View raw machine logs.
     * Restricted to factory-admin, production-manager, and above.
     */
    public function viewLogs(User $user, Machine $machine): bool
    {
        return $this->belongsToSameFactory($user, $machine)
            && $this->hasPermissionFor($user, Permission::VIEW_MACHINE_LOGS->value)
            && $this->hasMinimumRole($user, Role::PRODUCTION_MANAGER);
    }
}
