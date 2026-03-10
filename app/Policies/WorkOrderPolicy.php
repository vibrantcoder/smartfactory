<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Production\Models\WorkOrder;
use App\Domain\Shared\Enums\Permission;
use App\Models\User;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VIEW_ANY_WORK_ORDER->value);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::VIEW_WORK_ORDER->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CREATE_WORK_ORDER->value);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::UPDATE_WORK_ORDER->value);
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::DELETE_WORK_ORDER->value);
    }
}
