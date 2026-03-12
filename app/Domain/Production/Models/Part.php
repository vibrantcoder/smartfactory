<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Production\QueryBuilders\PartQueryBuilder;
use App\Domain\Shared\Models\BaseModel;
use App\Domain\Shared\Traits\BelongsToFactory;
use App\Domain\Shared\Traits\HasFactoryScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Production\Models\PartDrawing;

/**
 * Part
 *
 * A manufactured item ordered by a customer.
 * Has a routing: ordered sequence of process steps (part_processes).
 *
 * CYCLE TIME DISTINCTION:
 *   cycle_time_std   — ideal time per UNIT (seconds); drives OEE Performance %
 *                      (= theoretical minimum machine cycle time)
 *   total_cycle_time — sum of ALL routing step effective cycle times (minutes)
 *                      (= total time to complete one unit through all processes)
 *   Both are stored values. total_cycle_time is auto-recalculated by
 *   PartService::syncRouting() after every routing change.
 *
 * @property int         $id
 * @property int         $customer_id
 * @property int         $factory_id      denormalized for factory-scoped queries
 * @property string      $part_number     unique within factory (uq_parts_factory_number)
 * @property string      $name
 * @property string|null $description
 * @property string      $unit            pcs|kg|m|mm|set|lot
 * @property float       $cycle_time_std  ideal seconds/unit; drives OEE Performance %
 * @property float       $total_cycle_time sum of all routing step minutes (auto-calculated)
 * @property string      $status          active|discontinued
 *
 * @method static PartQueryBuilder query()
 * @method static PartQueryBuilder active()
 * @method static PartQueryBuilder forCustomer(int $customerId)
 */
class Part extends BaseModel
{
    use HasFactoryScope, BelongsToFactory;

    protected $table = 'parts';

    protected $fillable = [
        'customer_id',
        'factory_id',
        'part_number',
        'name',
        'description',
        'unit',
        'cycle_time_std',
        'total_cycle_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'cycle_time_std'   => 'decimal:2',
            'total_cycle_time' => 'decimal:2',
            'status'           => 'string',
        ];
    }

    public function newEloquentBuilder($query): PartQueryBuilder
    {
        return new PartQueryBuilder($query);
    }

    // ── Relationships ─────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Ordered routing steps. Always ordered by sequence_order.
     * Use this for routing display and cycle time calculation.
     */
    public function processes(): HasMany
    {
        return $this->hasMany(PartProcess::class, 'part_id')
            ->orderBy('sequence_order');
    }

    /**
     * Convenience: process masters via pivot.
     * Does NOT guarantee sequence — use processes() for ordered routing.
     */
    public function processMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            ProcessMaster::class,
            'part_processes',
            'part_id',
            'process_master_id'
        )->withPivot(['sequence_order', 'machine_type_required', 'standard_cycle_time', 'notes'])
         ->orderByPivot('sequence_order');
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'part_id');
    }

    public function drawings(): HasMany
    {
        return $this->hasMany(PartDrawing::class, 'part_id')->orderBy('created_at');
    }

    // ── Accessors ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Compute total cycle time from loaded processes.
     * Only use when processes relation is already loaded — avoids extra query.
     * For persistence, use PartService::syncRouting() which stores the result.
     */
    public function computeTotalCycleTimeFromLoaded(): float
    {
        if (! $this->relationLoaded('processes')) {
            return (float) ($this->total_cycle_time ?? 0);
        }

        return (float) $this->processes->sum(
            fn(PartProcess $p) => $p->effectiveCycleTime()
        );
    }
}
