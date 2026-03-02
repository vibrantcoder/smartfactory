<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Production\Models\ProcessMaster;
use App\Domain\Shared\Enums\Permission;
use App\Models\User;

/**
 * ProcessMasterPolicy
 *
 * ProcessMasters are a global reference table (no factory_id).
 * Super Admin manages them; factory users can view.
 */
class ProcessMasterPolicy extends BaseFactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_PROCESS_MASTER->value);
    }

    public function view(User $user, ProcessMaster $processMaster): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_PROCESS_MASTER->value);
    }

    public function create(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::CREATE_PROCESS_MASTER->value);
    }

    public function update(User $user, ProcessMaster $processMaster): bool
    {
        return $this->hasPermissionFor($user, Permission::UPDATE_PROCESS_MASTER->value);
    }

    public function delete(User $user, ProcessMaster $processMaster): bool
    {
        return $this->hasPermissionFor($user, Permission::DELETE_PROCESS_MASTER->value);
    }
}
