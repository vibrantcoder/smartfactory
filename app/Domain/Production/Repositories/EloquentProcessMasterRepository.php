<?php

declare(strict_types=1);

namespace App\Domain\Production\Repositories;

use App\Domain\Production\DataTransferObjects\ProcessMasterData;
use App\Domain\Production\Models\ProcessMaster;
use App\Domain\Production\Repositories\Contracts\ProcessMasterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * EloquentProcessMasterRepository
 *
 * INDEX TARGETING:
 *   findById()       → PK clustered index
 *   findByCode()     → uq_process_masters_code
 *   paginate()       → idx_process_masters_is_active  (leading filter)
 *   allActive()      → idx_process_masters_is_active
 *   findManyByIds()  → PK IN (…) — batch lookup for cycle time calculation
 *   isCodeUnique()   → uq_process_masters_code
 */
class EloquentProcessMasterRepository implements ProcessMasterRepositoryInterface
{
    public function findById(int $id): ?ProcessMaster
    {
        return ProcessMaster::find($id);
    }

    public function findByCode(string $code): ?ProcessMaster
    {
        return ProcessMaster::where('code', strtoupper($code))->first();
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return ProcessMaster::query()
            ->withUsageCount()
            ->when(
                isset($filters['is_active']),
                fn($q) => $q->where('process_masters.is_active', (bool) $filters['is_active'])
            )
            ->when(
                isset($filters['machine_type_default']),
                fn($q) => $q->forMachineType($filters['machine_type_default'])
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
        return ProcessMaster::query()
            ->active()
            ->ordered()
            ->select([
                'id', 'name', 'code',
                'standard_time', 'machine_type_default', 'description',
            ])
            ->get();
    }

    public function allActiveForMachineType(string $machineType): Collection
    {
        return ProcessMaster::query()
            ->active()
            ->forMachineType($machineType)
            ->ordered()
            ->select(['id', 'name', 'code', 'standard_time', 'machine_type_default'])
            ->get();
    }

    /**
     * Batch fetch by IDs — used by ProcessMasterService::computeTotalCycleTime().
     * Returns Collection keyed by id for O(1) access during summation.
     */
    public function findManyByIds(array $ids): Collection
    {
        return ProcessMaster::whereIn('id', array_unique($ids))
            ->select(['id', 'standard_time'])
            ->get()
            ->keyBy('id');
    }

    public function create(ProcessMasterData $data): ProcessMaster
    {
        return ProcessMaster::create($data->toArray());
    }

    public function update(ProcessMaster $processMaster, ProcessMasterData $data): ProcessMaster
    {
        $processMaster->update($data->toArray());
        return $processMaster->refresh();
    }

    public function deactivate(ProcessMaster $processMaster): bool
    {
        return (bool) $processMaster->update(['is_active' => false]);
    }

    public function isCodeUnique(string $code, ?int $ignoreId = null): bool
    {
        return ! ProcessMaster::where('code', strtoupper($code))
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
