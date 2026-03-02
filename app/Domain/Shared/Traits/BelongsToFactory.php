<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

use App\Domain\Shared\Scopes\FactoryScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToFactory
 *
 * Attaches the FactoryScope global scope and provides the factory()
 * relationship + named scopes for all factory-owned models.
 *
 * USAGE:
 *   class Machine extends BaseModel
 *   {
 *       use HasFactoryScope;   ← global scope (HTTP-aware)
 *       use BelongsToFactory;  ← relationship + named scopes
 *   }
 *
 * NAMED SCOPES (for jobs & console where global scope is inactive):
 *   Machine::forFactory($factoryId)->get()
 *   Machine::forAnyFactory()->get()      ← Super Admin raw query
 */
trait BelongsToFactory
{
    public static function bootBelongsToFactory(): void
    {
        // Automatically scope factory_id on create to the auth user's factory.
        // Prevents factory_id from being overridden via mass-assignment.
        static::creating(function (self $model) {
            if ($model->factory_id === null && auth()->hasUser() && auth()->user()->factory_id) {
                $model->factory_id = auth()->user()->factory_id;
            }
        });
    }

    // ── Relationship ──────────────────────────────────────────

    public function factory(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\Factory\Models\Factory::class,
            'factory_id'
        );
    }

    // ── Scopes ────────────────────────────────────────────────

    /**
     * Scope to a specific factory ID — for jobs, seeders, console commands
     * where FactoryScope global scope is not active.
     *
     * Machine::forFactory(5)->where('status', 'active')->get()
     */
    public function scopeForFactory(Builder $builder, int $factoryId): Builder
    {
        return $builder
            ->withoutGlobalScope(FactoryScope::class)
            ->where($this->getTable() . '.factory_id', $factoryId);
    }

    /**
     * Remove factory constraint entirely — Super Admin raw queries.
     * Machine::forAnyFactory()->count()
     */
    public function scopeForAnyFactory(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope(FactoryScope::class);
    }

    /**
     * Filter by active factory only — prevents orphaned records.
     * Part::withActiveFactory()->get()
     */
    public function scopeWithActiveFactory(Builder $builder): Builder
    {
        return $builder->whereHas(
            'factory',
            fn(Builder $q) => $q->where('status', 'active')
        );
    }
}
