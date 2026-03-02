<?php

declare(strict_types=1);

namespace App\Domain\Production\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * CustomerQueryBuilder
 *
 * INDEX TARGETING:
 *   active()          → idx_customers_status
 *   search()          → full-text / LIKE on name, code, contact_person
 *   forFactory()      → idx_customers_factory_id  (via BelongsToFactory scope)
 *   withPartCount()   → subquery COUNT on parts.customer_id
 */
class CustomerQueryBuilder extends Builder
{
    // ── Status Filters ────────────────────────────────────────

    public function active(): static
    {
        return $this->where('customers.status', 'active');
    }

    public function inactive(): static
    {
        return $this->where('customers.status', 'inactive');
    }

    // ── Search ────────────────────────────────────────────────

    /**
     * LIKE search on name, code, contact_person.
     * Not an index scan — keep result sets small with factory scope first.
     */
    public function search(string $term): static
    {
        $like = "%{$term}%";

        return $this->where(function (Builder $q) use ($like) {
            $q->where('customers.name',           'LIKE', $like)
              ->orWhere('customers.code',           'LIKE', $like)
              ->orWhere('customers.contact_person', 'LIKE', $like)
              ->orWhere('customers.email',          'LIKE', $like);
        });
    }

    // ── Eager Loads ───────────────────────────────────────────

    /**
     * Include total part count via withCount.
     * Accessor: $customer->parts_count
     */
    public function withPartCount(): static
    {
        return $this->withCount('parts');
    }

    /**
     * Include active part count.
     * Accessor: $customer->active_parts_count
     */
    public function withActivePartCount(): static
    {
        return $this->withCount([
            'parts as active_parts_count' => fn(Builder $q) => $q->where('status', 'active'),
        ]);
    }

    /**
     * Eager-load parts with their routing step counts.
     * Useful for customer detail view.
     */
    public function withParts(): static
    {
        return $this->with([
            'parts' => fn($q) => $q
                ->active()
                ->withCount('processes')
                ->select(['id', 'customer_id', 'part_number', 'name', 'unit', 'status'])
                ->ordered(),
        ]);
    }

    // ── Ordering ──────────────────────────────────────────────

    public function ordered(): static
    {
        return $this->orderBy('customers.name');
    }

    public function orderedByCode(): static
    {
        return $this->orderBy('customers.code');
    }
}
