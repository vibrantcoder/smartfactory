<?php

declare(strict_types=1);

namespace App\Domain\Production\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * PartQueryBuilder
 *
 * INDEX TARGETING:
 *   active()            → idx_parts_status
 *   forCustomer()       → idx_parts_customer_id
 *   forFactory()        → idx_parts_factory_id  (via BelongsToFactory scope)
 *   withProcessCount()  → subquery COUNT on part_processes.part_id
 *   search()            → LIKE on part_number, name  (keep factory scope first)
 */
class PartQueryBuilder extends Builder
{
    // ── Status Filters ────────────────────────────────────────

    public function active(): static
    {
        return $this->where('parts.status', 'active');
    }

    public function discontinued(): static
    {
        return $this->where('parts.status', 'discontinued');
    }

    // ── Customer Scope ────────────────────────────────────────

    /**
     * Filter by customer. Hits idx_parts_customer_id.
     */
    public function forCustomer(int $customerId): static
    {
        return $this->where('parts.customer_id', $customerId);
    }

    // ── Search ────────────────────────────────────────────────

    /**
     * LIKE search on part_number and name.
     * Always combined with forFactory() to keep result sets bounded.
     */
    public function search(string $term): static
    {
        $like = "%{$term}%";

        return $this->where(function (Builder $q) use ($like) {
            $q->where('parts.part_number', 'LIKE', $like)
              ->orWhere('parts.name',        'LIKE', $like)
              ->orWhere('parts.description', 'LIKE', $like);
        });
    }

    // ── Eager Loads ───────────────────────────────────────────

    /**
     * Include process (routing step) count.
     * Accessor: $part->processes_count
     */
    public function withProcessCount(): static
    {
        return $this->withCount('processes');
    }

    /**
     * Eager-load the owning customer (minimal columns).
     */
    public function withCustomer(): static
    {
        return $this->with('customer:id,factory_id,name,code,status');
    }

    /**
     * Eager-load fully ordered process routing.
     * Includes process master name for display.
     */
    public function withProcesses(): static
    {
        return $this->with([
            'processes' => fn($q) => $q
                ->with('processMaster:id,name,code,machine_type_default')
                ->orderBy('sequence_order'),
        ]);
    }

    // ── Ordering ──────────────────────────────────────────────

    public function ordered(): static
    {
        return $this->orderBy('parts.part_number');
    }

    public function orderedByName(): static
    {
        return $this->orderBy('parts.name');
    }
}
