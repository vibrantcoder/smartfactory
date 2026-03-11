<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Domain\Factory\Models\Factory;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * ResolvesFactory
 *
 * Shared logic for determining which factory context to use for admin web pages.
 *
 * Rules:
 *  - 1 active factory in DB  → always auto-select it, never show a dropdown
 *  - 2+ factories + super-admin → show factory selector dropdown
 *  - 2+ factories + factory-admin → use their own factory, no dropdown
 */
trait ResolvesFactory
{
    /**
     * Returns ['factoryId' => int|null, 'factories' => Collection].
     *
     * @param  User     $user              The authenticated user
     * @param  int|null $requestFactoryId  Optional override (e.g. from a GET param)
     */
    protected function resolveFactories(User $user, ?int $requestFactoryId = null): array
    {
        $all     = Factory::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $isMulti = $all->count() > 1;

        if ($user->factory_id !== null) {
            // Factory-scoped user: always their own factory, never show selector
            $factoryId = $user->factory_id;
            $factories = collect();
        } elseif (! $isMulti) {
            // Super-admin but only one factory: auto-assign, no selector needed
            $factoryId = $all->first()?->id;
            $factories = collect();
        } else {
            // Super-admin with multiple factories: allow switching
            $factoryId = $requestFactoryId ?: $all->first()?->id;
            $factories = $all;
        }

        return compact('factoryId', 'factories');
    }
}
