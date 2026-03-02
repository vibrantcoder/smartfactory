<?php

declare(strict_types=1);

namespace App\Domain\Shared\Scopes;

use App\Domain\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

/**
 * FactoryScope — Global Eloquent Scope
 *
 * Automatically filters every model query to the authenticated user's factory.
 * Applied via the HasFactoryScope trait's boot method.
 *
 * BEHAVIOUR MATRIX:
 *   ┌──────────────────────────────┬────────────────────────────────────┐
 *   │ Context                      │ Behaviour                          │
 *   ├──────────────────────────────┼────────────────────────────────────┤
 *   │ HTTP + Auth + factory user   │ WHERE factory_id = user.factory_id │
 *   │ HTTP + Auth + super-admin    │ No scope (sees all)                │
 *   │ HTTP + unauthenticated       │ No scope (middleware blocks first) │
 *   │ Queue job / Console          │ No scope (use forFactory() scope)  │
 *   └──────────────────────────────┴────────────────────────────────────┘
 *
 * MACROS ADDED TO BUILDER:
 *   ->withoutFactoryScope()          — remove global scope for this query
 *   ->forFactory(int $factoryId)     — override to specific factory (jobs/console)
 *   ->forAnyFactory()                — alias for withoutFactoryScope()
 */
class FactoryScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Skip in console/queue — jobs must use forFactory() explicitly
        if (app()->runningInConsole()) {
            return;
        }

        if (! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        // Deactivated users should not receive any data
        if (! $user->is_active) {
            $builder->whereRaw('1 = 0');
            return;
        }

        // Super Admin: bypass scope — check without Spatie team constraint
        if ($this->isSuperAdmin($user)) {
            return;
        }

        // Regular user: restrict to their factory
        if ($user->factory_id !== null) {
            $builder->where(
                $model->getTable() . '.factory_id',
                $user->factory_id
            );
        } else {
            // User without a factory — sees nothing
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Register macros so callers can escape the scope when needed.
     */
    public function extend(Builder $builder): void
    {
        // Remove this scope for a single query
        $builder->macro('withoutFactoryScope', function (Builder $builder): Builder {
            return $builder->withoutGlobalScope(FactoryScope::class);
        });

        // Alias
        $builder->macro('forAnyFactory', function (Builder $builder): Builder {
            return $builder->withoutGlobalScope(FactoryScope::class);
        });

        // Override to a specific factory (for jobs and console commands)
        $builder->macro('forFactory', function (Builder $builder, int $factoryId): Builder {
            return $builder
                ->withoutGlobalScope(FactoryScope::class)
                ->where($builder->getModel()->getTable() . '.factory_id', $factoryId);
        });
    }

    private function isSuperAdmin(mixed $user): bool
    {
        $registrar      = app(PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(0);

        $result = $user->hasRole(Role::SUPER_ADMIN->value);

        $registrar->setPermissionsTeamId($originalTeamId);

        return $result;
    }
}
