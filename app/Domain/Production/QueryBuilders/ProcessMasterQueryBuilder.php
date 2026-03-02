<?php

declare(strict_types=1);

namespace App\Domain\Production\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * ProcessMasterQueryBuilder
 *
 * INDEX TARGETING:
 *   active()   → idx_process_masters_is_active
 *   search()   → LIKE on name, code  (bounded by active() first)
 *   withUsage  → subquery COUNT on part_processes.process_master_id
 */
class ProcessMasterQueryBuilder extends Builder
{
    // ── Status Filters ────────────────────────────────────────

    public function active(): static
    {
        return $this->where('process_masters.is_active', true);
    }

    public function inactive(): static
    {
        return $this->where('process_masters.is_active', false);
    }

    // ── Machine Type ──────────────────────────────────────────

    /**
     * Filter by default machine type.
     * Useful when building routing for a specific machine category.
     */
    public function forMachineType(string $type): static
    {
        return $this->where('process_masters.machine_type_default', $type);
    }

    // ── Search ────────────────────────────────────────────────

    public function search(string $term): static
    {
        $like = "%{$term}%";

        return $this->where(function (Builder $q) use ($like) {
            $q->where('process_masters.name',        'LIKE', $like)
              ->orWhere('process_masters.code',        'LIKE', $like)
              ->orWhere('process_masters.description', 'LIKE', $like);
        });
    }

    // ── Eager Loads ───────────────────────────────────────────

    /**
     * Include count of parts that use this process in their routing.
     * Accessor: $processMaster->part_processes_count
     */
    public function withUsageCount(): static
    {
        return $this->withCount('partProcesses');
    }

    // ── Ordering ──────────────────────────────────────────────

    public function ordered(): static
    {
        return $this->orderBy('process_masters.name');
    }

    public function orderedByCode(): static
    {
        return $this->orderBy('process_masters.code');
    }
}
