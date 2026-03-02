<?php

declare(strict_types=1);

namespace App\Domain\Factory\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * FactoryQueryBuilder
 *
 * Typed query builder for the Factory model.
 * All domain-specific query logic lives here — not in the model or repository.
 *
 * Returned automatically when calling Factory::query() because
 * Factory::newEloquentBuilder() returns this class.
 *
 * USAGE:
 *   Factory::query()->active()->withSettings()->paginate(20);
 *   Factory::query()->search('Bangkok')->withMachineCount()->get();
 */
class FactoryQueryBuilder extends Builder
{
    // ── Status Filters ────────────────────────────────────────

    public function active(): static
    {
        return $this->where('factories.status', 'active');
    }

    public function inactive(): static
    {
        return $this->where('factories.status', 'inactive');
    }

    // ── Search ────────────────────────────────────────────────

    /**
     * Fuzzy search across name, code, and location.
     * Uses LIKE — acceptable at factory table scale (never millions of rows).
     */
    public function search(string $term): static
    {
        $like = "%{$term}%";

        return $this->where(function (Builder $q) use ($like) {
            $q->where('factories.name',     'LIKE', $like)
              ->orWhere('factories.code',   'LIKE', $like)
              ->orWhere('factories.location','LIKE', $like);
        });
    }

    // ── Eager Loads ───────────────────────────────────────────

    public function withSettings(): static
    {
        return $this->with('settings');
    }

    /**
     * Appends machine_count without loading all machine records.
     * Uses a subquery count — hits idx_machines_factory_id.
     */
    public function withMachineCount(): static
    {
        return $this->withCount('machines');
    }

    /**
     * Append machine count for ACTIVE machines only.
     */
    public function withActiveMachineCount(): static
    {
        return $this->withCount([
            'machines as active_machine_count' => fn(Builder $q) => $q->where('status', 'active'),
        ]);
    }

    public function withUserCount(): static
    {
        return $this->withCount('users');
    }

    // ── Ordering ──────────────────────────────────────────────

    public function ordered(): static
    {
        return $this->orderBy('factories.name');
    }
}
