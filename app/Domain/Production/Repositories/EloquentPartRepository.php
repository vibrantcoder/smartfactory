<?php

declare(strict_types=1);

namespace App\Domain\Production\Repositories;

use App\Domain\Production\DataTransferObjects\PartData;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\PartProcess;
use App\Domain\Production\Repositories\Contracts\PartRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EloquentPartRepository
 *
 * INDEX TARGETING:
 *   findById()                  → PK clustered index
 *   findByFactoryAndNumber()    → uq_parts_factory_number
 *   paginate()                  → idx_parts_factory_id + idx_parts_customer_id + idx_parts_status
 *   allActiveByFactory()        → idx_parts_factory_id + idx_parts_status
 *   allActiveByCustomer()       → idx_parts_customer_id + idx_parts_status
 *   isNumberUniqueInFactory()   → uq_parts_factory_number
 *   countByFactory()            → idx_parts_factory_id (COUNT)
 *   countActiveByCustomer()     → idx_parts_customer_id (COUNT)
 *
 * NOTE: syncProcesses() deletes all existing steps and re-inserts.
 * This is intentional — routing changes are rare, always complete replacements,
 * and keeping sequence_order consistent is easier with a full rebuild.
 */
class EloquentPartRepository implements PartRepositoryInterface
{
    public function findById(int $id): ?Part
    {
        return Part::query()->forAnyFactory()->find($id);
    }

    public function findByFactoryAndNumber(int $factoryId, string $partNumber): ?Part
    {
        return Part::query()
            ->forFactory($factoryId)            // uq_parts_factory_number (leading column)
            ->where('part_number', strtoupper($partNumber))
            ->first();
    }

    public function paginate(int|null $factoryId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Part::query();

        // Super-admin (null): FactoryScope global scope applies (no WHERE — sees all factories)
        // Factory user: forFactory() removes global scope and adds explicit WHERE factory_id = ?
        if ($factoryId !== null) {
            $query->forFactory($factoryId);     // idx_parts_factory_id
        }

        return $query
            ->withCustomer()
            ->withProcessCount()
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('parts.status', $filters['status'])     // idx_parts_status
            )
            ->when(
                isset($filters['customer_id']),
                fn($q) => $q->forCustomer((int) $filters['customer_id'])    // idx_parts_customer_id
            )
            ->when(
                isset($filters['search']),
                fn($q) => $q->search($filters['search'])
            )
            ->ordered()
            ->paginate($perPage);
    }

    public function allActiveByFactory(int $factoryId): Collection
    {
        return Part::query()
            ->forFactory($factoryId)
            ->active()
            ->ordered()
            ->select(['id', 'customer_id', 'part_number', 'name', 'unit'])
            ->get();
    }

    public function allActiveByCustomer(int $customerId): Collection
    {
        return Part::query()
            ->forCustomer($customerId)          // idx_parts_customer_id
            ->active()
            ->ordered()
            ->select(['id', 'customer_id', 'part_number', 'name', 'unit'])
            ->get();
    }

    public function create(int $factoryId, PartData $data): Part
    {
        return Part::create([
            ...$data->toArray(),
            'factory_id' => $factoryId,
        ]);
    }

    public function update(Part $part, PartData $data): Part
    {
        $part->update($data->toArray());
        return $part->refresh();
    }

    public function discontinue(Part $part): bool
    {
        return (bool) $part->update(['status' => 'discontinued']);
    }

    /**
     * Atomically replace all routing steps and store total_cycle_time.
     *
     * Single transaction:
     *   1. DELETE all existing part_processes for this part
     *   2. INSERT new steps with 1-based sequence_order from array index
     *   3. UPDATE parts.total_cycle_time with the pre-computed total
     *
     * uq_part_processes_part_seq enforces no duplicate sequence_order per part.
     * total_cycle_time is computed by PartService before this call so the
     * service owns the business logic; the repository owns persistence atomicity.
     */
    public function syncProcesses(Part $part, array $processes, float $totalCycleTime): void
    {
        DB::transaction(function () use ($part, $processes, $totalCycleTime) {
            // Delete existing routing
            PartProcess::where('part_id', $part->id)->delete();

            // Re-insert with 1-based sequence from array position
            foreach ($processes as $index => $step) {
                PartProcess::create([
                    'part_id'               => $part->id,
                    'process_master_id'     => $step['process_master_id'],
                    'sequence_order'        => $index + 1,
                    'machine_type_required' => $step['machine_type_required'] ?? null,
                    'standard_cycle_time'   => $step['standard_cycle_time']   ?? null,
                    'setup_time'            => $step['setup_time']            ?? null,
                    'process_type'          => $step['process_type']          ?? 'inhouse',
                    'notes'                 => $step['notes']                 ?? null,
                ]);
            }

            // Store the auto-calculated total on the part — atomic with routing change
            $part->update(['total_cycle_time' => $totalCycleTime]);
        });
    }

    public function isNumberUniqueInFactory(int $factoryId, string $partNumber, ?int $ignoreId = null): bool
    {
        return ! Part::query()
            ->forFactory($factoryId)            // uq_parts_factory_number (leading column)
            ->where('part_number', strtoupper($partNumber))
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function countByFactory(int $factoryId): int
    {
        return Part::query()
            ->forFactory($factoryId)
            ->count();
    }

    public function countActiveByCustomer(int $customerId): int
    {
        return Part::query()
            ->forCustomer($customerId)
            ->active()
            ->count();
    }
}
