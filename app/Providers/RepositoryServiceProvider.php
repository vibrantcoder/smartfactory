<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Machine Domain ────────────────────────────────────
        $this->app->bind(
            \App\Domain\Machine\Repositories\Contracts\MachineRepositoryInterface::class,
            \App\Domain\Machine\Repositories\EloquentMachineRepository::class
        );

        // ── Production Domain ─────────────────────────────────
        $this->app->bind(
            \App\Domain\Production\Repositories\Contracts\PartRepositoryInterface::class,
            \App\Domain\Production\Repositories\EloquentPartRepository::class
        );

        $this->app->bind(
            \App\Domain\Production\Repositories\Contracts\CustomerRepositoryInterface::class,
            \App\Domain\Production\Repositories\EloquentCustomerRepository::class
        );

        $this->app->bind(
            \App\Domain\Production\Repositories\Contracts\ProcessMasterRepositoryInterface::class,
            \App\Domain\Production\Repositories\EloquentProcessMasterRepository::class
        );

        // ── Factory Domain ────────────────────────────────────
        $this->app->bind(
            \App\Domain\Factory\Repositories\Contracts\FactoryRepositoryInterface::class,
            \App\Domain\Factory\Repositories\EloquentFactoryRepository::class
        );
    }
}
