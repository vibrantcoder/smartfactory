<?php

declare(strict_types=1);

namespace App\Domain\Factory\Repositories;

use App\Domain\Factory\DataTransferObjects\FactoryData;
use App\Domain\Factory\Models\Factory;
use App\Domain\Factory\Models\FactorySettings;
use App\Domain\Factory\Repositories\Contracts\FactoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EloquentFactoryRepository
 *
 * INDEX AWARENESS:
 *   findByCode()     → hits uq_factories_code (unique index)
 *   paginate()       → hits idx_factories_status for status filter
 *   isCodeUnique()   → hits uq_factories_code
 *   allActive()      → hits idx_factories_status
 */
class EloquentFactoryRepository implements FactoryRepositoryInterface
{
    public function findById(int $id): ?Factory
    {
        // SELECT on PK — always uses clustered index
        return Factory::query()->find($id);
    }

    public function findByCode(string $code): ?Factory
    {
        // uq_factories_code index
        return Factory::query()
            ->where('code', strtoupper($code))
            ->first();
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Factory::query()
            ->withMachineCount()
            ->withUserCount()
            ->withSettings()
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status'])  // idx_factories_status
            )
            ->when(
                isset($filters['search']),
                fn($q) => $q->search($filters['search'])
            )
            ->ordered()
            ->paginate($perPage);
    }

    public function allActive(): Collection
    {
        return Factory::query()
            ->active()              // idx_factories_status
            ->ordered()
            ->select(['id', 'name', 'code', 'timezone'])
            ->get();
    }

    /**
     * Creates factory + default settings atomically.
     * If settings insert fails, factory creation is rolled back.
     */
    public function create(FactoryData $data): Factory
    {
        return DB::transaction(function () use ($data): Factory {
            $factory = Factory::create($data->toArray());

            // Create default settings for the new factory
            FactorySettings::create([
                'factory_id' => $factory->id,
                // Defaults are defined in FactorySettings::resolveFor()
            ]);

            return $factory->load('settings');
        });
    }

    public function update(Factory $factory, FactoryData $data): Factory
    {
        $factory->update($data->toArray());
        return $factory->refresh();
    }

    public function deactivate(Factory $factory): bool
    {
        return (bool) $factory->update(['status' => 'inactive']);
    }

    public function isCodeUnique(string $code, ?int $ignoreId = null): bool
    {
        return ! Factory::query()
            ->where('code', strtoupper($code))
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function count(): int
    {
        // forAnyFactory() removes the global FactoryScope so Super Admin sees all
        return Factory::query()->forAnyFactory()->count();
    }
}
