<?php

declare(strict_types=1);

namespace App\Domain\Production\Services;

use App\Domain\Production\DataTransferObjects\PartData;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Repositories\Contracts\PartRepositoryInterface;
use App\Domain\Production\Repositories\Contracts\ProcessMasterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * PartService
 *
 * Business logic for the Part domain.
 * Handles scalar CRUD (PartData) and process routing (syncRouting).
 *
 * TOTAL CYCLE TIME FLOW:
 *   1. Client sends ordered processes array to syncRouting()
 *   2. Service fetches ProcessMasters in one batched query (findManyByIds)
 *   3. Service computes total_cycle_time: sum of each step's effective time
 *      (override minutes if set, else master's standard_time)
 *   4. Service passes computed total to repository
 *   5. Repository saves routing steps + total_cycle_time atomically in one transaction
 *
 * This ensures total_cycle_time is always consistent with the actual routing.
 */
class PartService
{
    public function __construct(
        private readonly PartRepositoryInterface          $repository,
        private readonly ProcessMasterRepositoryInterface $processMasterRepository,
    ) {}

    // ── Queries ───────────────────────────────────────────────

    public function list(
        int|null $factoryId,
        array    $filters  = [],
        int      $perPage  = 25,
    ): LengthAwarePaginator {
        return $this->repository->paginate($factoryId, $filters, $perPage);
    }

    public function get(int $id): Part
    {
        $part = $this->repository->findById($id);

        if ($part === null) {
            throw new \DomainException("Part [{$id}] not found.");
        }

        return $part;
    }

    public function dropdown(int $factoryId): Collection
    {
        return $this->repository->allActiveByFactory($factoryId);
    }

    public function dropdownByCustomer(int $customerId): Collection
    {
        return $this->repository->allActiveByCustomer($customerId);
    }

    // ── Mutations ─────────────────────────────────────────────

    public function create(int $factoryId, PartData $data): Part
    {
        $this->guardDuplicateNumber($factoryId, $data->partNumber);

        return $this->repository->create($factoryId, $data);
    }

    public function update(Part $part, PartData $data): Part
    {
        $this->guardDuplicateNumber($part->factory_id, $data->partNumber, $part->id);

        return $this->repository->update($part, $data);
    }

    /**
     * Discontinue a part.
     *
     * BUSINESS RULE: A part with draft/scheduled/in-progress production plans
     * cannot be discontinued. Completed/cancelled plans are fine (historical).
     *
     * @throws \DomainException when part has active production plans
     */
    public function discontinue(Part $part): void
    {
        $hasActivePlans = $part->productionPlans()
            ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
            ->exists();

        if ($hasActivePlans) {
            throw new \DomainException(
                "Part [{$part->part_number}] has active production plans and cannot be discontinued. " .
                "Complete or cancel all active plans first."
            );
        }

        $this->repository->discontinue($part);
    }

    /**
     * Replace the part's entire process routing and auto-calculate total_cycle_time.
     *
     * $processes is an ordered array:
     * [
     *   ['process_master_id' => 1, 'machine_type_required' => 'Laser', 'standard_cycle_time' => 45.0, 'notes' => null],
     *   ['process_master_id' => 2, 'machine_type_required' => null,    'standard_cycle_time' => null,  'notes' => null],
     * ]
     * sequence_order is auto-assigned as array index + 1 by the repository.
     *
     * BUSINESS RULE: Routing cannot be changed while a plan is in_progress.
     * SIDE EFFECT: parts.total_cycle_time is updated atomically with the routing change.
     *
     * @return Part The updated Part with fresh processes loaded.
     * @throws \DomainException when part has in-progress plans
     */
    public function syncRouting(Part $part, array $processes): Part
    {
        $hasInProgressPlans = $part->productionPlans()
            ->where('status', 'in_progress')
            ->exists();

        if ($hasInProgressPlans) {
            throw new \DomainException(
                "Part [{$part->part_number}] has in-progress production plans. " .
                "Cannot modify routing while production is running."
            );
        }

        // ── Compute total_cycle_time ───────────────────────────
        //
        // Batch-fetch all referenced process masters in ONE query (keyed by id).
        // Then sum each step's effective cycle time:
        //   step.standard_cycle_time  (if explicitly overridden)  ← part-specific
        //   processMaster.standard_time (fallback)                ← library default
        //   0.0                       (if neither set)
        //
        $masterIds      = array_column($processes, 'process_master_id');
        $processMasters = $this->processMasterRepository->findManyByIds($masterIds);

        $totalCycleTime = $this->computeTotalCycleTime($processes, $processMasters);

        // ── Persist atomically ────────────────────────────────
        $this->repository->syncProcesses($part, $processes, $totalCycleTime);

        // Return fresh instance with updated routing loaded
        return $part->fresh(['processes.processMaster']);
    }

    // ── Cycle Time Calculation ────────────────────────────────

    /**
     * Sum effective cycle times for a set of routing steps.
     *
     * @param  array      $steps           Raw steps array (before or after save)
     * @param  Collection $processMasters  Keyed by id — from ProcessMasterRepository::findManyByIds()
     * @return float  Total minutes
     */
    public function computeTotalCycleTime(array $steps, Collection $processMasters): float
    {
        return collect($steps)->reduce(
            function (float $total, array $step) use ($processMasters): float {
                $override = isset($step['standard_cycle_time'])
                    ? (float) $step['standard_cycle_time']
                    : null;

                if ($override !== null) {
                    return $total + $override;
                }

                $masterId = (int) $step['process_master_id'];
                $master   = $processMasters->get($masterId);

                return $total + (float) ($master?->standard_time ?? 0.0);
            },
            0.0
        );
    }

    // ── Guards ────────────────────────────────────────────────

    /**
     * @throws \DomainException on duplicate part_number within factory
     */
    private function guardDuplicateNumber(int $factoryId, string $partNumber, ?int $ignoreId = null): void
    {
        if (! $this->repository->isNumberUniqueInFactory($factoryId, $partNumber, $ignoreId)) {
            throw new \DomainException(
                "A part with number [{$partNumber}] already exists in this factory."
            );
        }
    }
}
