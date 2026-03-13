<?php

declare(strict_types=1);

namespace App\Domain\Production\Services;

use App\Domain\Production\DataTransferObjects\ProcessMasterData;
use App\Domain\Production\Models\ProcessMaster;
use App\Domain\Production\Repositories\Contracts\ProcessMasterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * ProcessMasterService
 *
 * Handles CRUD for the global process library AND the cycle time calculation
 * that powers both the live routing builder UI and PartService::syncRouting().
 *
 * CYCLE TIME CALCULATION DESIGN:
 *   Each routing step has an effective cycle time:
 *     - Use part_processes.standard_cycle_time if explicitly overridden
 *     - Otherwise contributes 0 minutes
 *
 *   computeTotalCycleTime() accepts the raw steps array (before DB save) and
 *   a keyed Collection of ProcessMasters to avoid N+1 queries.
 *   This makes it usable for both preview (AJAX) and post-save recalculation.
 */
class ProcessMasterService
{
    public function __construct(
        private readonly ProcessMasterRepositoryInterface $repository,
    ) {}

    // ── Queries ───────────────────────────────────────────────

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function get(int $id): ProcessMaster
    {
        $pm = $this->repository->findById($id);

        if ($pm === null) {
            throw new \DomainException("Process master [{$id}] not found.");
        }

        return $pm;
    }

    /**
     * All active process masters for the routing builder palette.
     */
    public function palette(): Collection
    {
        return $this->repository->allActive();
    }

    // ── Mutations ─────────────────────────────────────────────

    public function create(ProcessMasterData $data): ProcessMaster
    {
        $this->guardDuplicateCode($data->code);

        return $this->repository->create($data);
    }

    public function update(ProcessMaster $processMaster, ProcessMasterData $data): ProcessMaster
    {
        $this->guardDuplicateCode($data->code, $processMaster->id);

        return $this->repository->update($processMaster, $data);
    }

    /**
     * Deactivate a process master.
     *
     * BUSINESS RULE: Cannot deactivate a process that is currently used in any
     * ACTIVE part's routing. This would break the routing builder palette and
     * prevent new plans from being scheduled.
     * Parts that are discontinued may still reference this process (historical).
     *
     * @throws \DomainException if in use by active parts
     */
    public function deactivate(ProcessMaster $processMaster): void
    {
        $activeUsageCount = $processMaster->partProcesses()
            ->whereHas('part', fn($q) => $q->where('status', 'active'))
            ->count();

        if ($activeUsageCount > 0) {
            throw new \DomainException(
                "Process [{$processMaster->code}] is used in {$activeUsageCount} active part routing(s) " .
                "and cannot be deactivated. Update those routings first."
            );
        }

        $this->repository->deactivate($processMaster);
    }

    // ── Cycle Time Calculation ────────────────────────────────

    /**
     * Compute total cycle time for a set of routing steps.
     *
     * Called in two contexts:
     *   1. AJAX preview — before saving (returns total for UI display)
     *   2. Post-save    — by PartService::syncRouting() to store on Part
     *
     * @param  array      $steps  Raw routing array:
     *                            [['process_master_id' => int, 'standard_cycle_time' => float|null], ...]
     * @param  Collection $processMasters  Keyed by id — from findManyByIds()
     * @return float  Total minutes (sum of effective cycle times)
     */
    public function computeTotalCycleTime(array $steps, Collection $processMasters): float
    {
        return collect($steps)->reduce(function (float $total, array $step) use ($processMasters): float {
            return $total + $this->resolveEffectiveCycleTime(
                processMasterId:  (int) $step['process_master_id'],
                override:         isset($step['standard_cycle_time'])
                                      ? (float) $step['standard_cycle_time']
                                      : null,
                processMasters:   $processMasters,
            );
        }, 0.0);
    }

    /**
     * Resolve the effective cycle time for a single step.
     *
     * @param  int        $processMasterId
     * @param  float|null $override         Part-level override (null = use master default)
     * @param  Collection $processMasters   Keyed by id
     * @return float  Minutes
     */
    public function resolveEffectiveCycleTime(
        int        $processMasterId,
        ?float     $override,
        Collection $processMasters,
    ): float {
        if ($override !== null) {
            return $override;
        }

        return 0.0;
    }

    /**
     * Convenience: fetch process masters for a steps array and compute total.
     * Makes one batched DB call to fetch all needed process masters.
     *
     * Used by the AJAX preview endpoint.
     */
    public function previewTotalCycleTime(array $steps): array
    {
        $ids = array_column($steps, 'process_master_id');
        $processMasters = $this->repository->findManyByIds($ids);

        $stepResults = collect($steps)->map(function (array $step, int $index) use ($processMasters): array {
            $effectiveTime = $this->resolveEffectiveCycleTime(
                processMasterId: (int) $step['process_master_id'],
                override:        isset($step['standard_cycle_time'])
                                     ? (float) $step['standard_cycle_time']
                                     : null,
                processMasters:  $processMasters,
            );

            return [
                'sequence_order'        => $index + 1,
                'process_master_id'     => $step['process_master_id'],
                'override_cycle_time'   => isset($step['standard_cycle_time'])
                                              ? (float) $step['standard_cycle_time']
                                              : null,
                'default_cycle_time'    => 0.0,
                'effective_cycle_time'  => $effectiveTime,
            ];
        })->values()->all();

        $total = array_sum(array_column($stepResults, 'effective_cycle_time'));

        return [
            'steps'            => $stepResults,
            'total_cycle_time' => round($total, 2),
        ];
    }

    // ── Guards ────────────────────────────────────────────────

    /**
     * @throws \DomainException on duplicate code globally
     */
    private function guardDuplicateCode(string $code, ?int $ignoreId = null): void
    {
        if (! $this->repository->isCodeUnique($code, $ignoreId)) {
            throw new \DomainException(
                "A process master with code [{$code}] already exists."
            );
        }
    }
}
