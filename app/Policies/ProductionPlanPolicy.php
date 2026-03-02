<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Shared\Enums\Permission;
use App\Domain\Shared\Enums\Role;
use App\Models\User;

/**
 * ProductionPlanPolicy
 *
 * Factory-scoped authorization for ProductionPlan resources.
 *
 * BUSINESS RULE: Only the creator or a Production Manager+ can
 * delete a plan that is already 'in_progress'.
 */
class ProductionPlanPolicy extends BaseFactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::VIEW_ANY_PRODUCTION_PLAN->value);
    }

    public function view(User $user, ProductionPlan $plan): bool
    {
        return $this->belongsToSameFactory($user, $plan)
            && $this->hasPermissionFor($user, Permission::VIEW_PRODUCTION_PLAN->value);
    }

    public function create(User $user): bool
    {
        return $this->hasPermissionFor($user, Permission::CREATE_PRODUCTION_PLAN->value);
    }

    public function update(User $user, ProductionPlan $plan): bool
    {
        // Completed or cancelled plans cannot be updated
        if (in_array($plan->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        return $this->belongsToSameFactory($user, $plan)
            && $this->hasPermissionFor($user, Permission::UPDATE_PRODUCTION_PLAN->value);
    }

    /**
     * Approve a plan: changes status from 'scheduled' to 'in_progress'.
     * Only Production Manager and above.
     */
    public function approve(User $user, ProductionPlan $plan): bool
    {
        if ($plan->status !== 'scheduled') {
            return false;
        }

        return $this->belongsToSameFactory($user, $plan)
            && $this->hasPermissionFor($user, Permission::APPROVE_PRODUCTION_PLAN->value)
            && $this->hasMinimumRole($user, Role::PRODUCTION_MANAGER);
    }

    /**
     * Delete a plan.
     * In-progress plans require Production Manager minimum.
     * Completed plans cannot be deleted by anyone (audit trail).
     */
    public function delete(User $user, ProductionPlan $plan): bool
    {
        if ($plan->status === 'completed') {
            return false; // completed plans are immutable audit records
        }

        if ($plan->status === 'in_progress') {
            return $this->belongsToSameFactory($user, $plan)
                && $this->hasPermissionFor($user, Permission::DELETE_PRODUCTION_PLAN->value)
                && $this->hasMinimumRole($user, Role::PRODUCTION_MANAGER);
        }

        return $this->belongsToSameFactory($user, $plan)
            && $this->hasPermissionFor($user, Permission::DELETE_PRODUCTION_PLAN->value);
    }
}
