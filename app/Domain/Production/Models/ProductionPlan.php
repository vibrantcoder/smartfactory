<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Machine\Models\Machine;
use App\Domain\Shared\Models\BaseModel;
use App\Domain\Shared\Traits\BelongsToFactory;
use App\Domain\Shared\Traits\HasFactoryScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProductionPlan — Scheduled Manufacturing Run
 *
 * Links a Part to a Machine for a given date/shift with a planned quantity.
 * Actual outputs are recorded in production_actuals (HasMany).
 *
 * STATUS MACHINE:
 *   draft → scheduled → in_progress → completed
 *                    ↘ cancelled
 *
 * DESIGN RULES:
 *   - completed and cancelled plans are IMMUTABLE (policy enforced).
 *   - good_qty is GENERATED in production_actuals (actual_qty - defect_qty).
 *   - Attainment % = SUM(actual_good_qty) / planned_qty × 100.
 *
 * @property int         $id
 * @property int         $factory_id
 * @property int         $machine_id
 * @property int         $part_id
 * @property int|null    $part_process_id    which routing step this plan covers
 * @property int         $shift_id
 * @property string      $planned_date       DATE
 * @property int         $planned_qty
 * @property string      $status             draft|scheduled|in_progress|completed|cancelled
 * @property string|null $notes
 */
class ProductionPlan extends BaseModel
{
    use HasFactoryScope, BelongsToFactory;

    protected $table = 'production_plans';

    protected $fillable = [
        'factory_id',
        'machine_id',
        'part_id',
        'part_process_id',
        'work_order_id',
        'shift_id',
        'planned_date',
        'planned_qty',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'planned_date' => 'date',
            'planned_qty'  => 'integer',
            'status'       => 'string',
        ];
    }

    // ── Relationships ─────────────────────────────────────────

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    public function partProcess(): BelongsTo
    {
        return $this->belongsTo(PartProcess::class, 'part_process_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function actuals(): HasMany
    {
        return $this->hasMany(ProductionActual::class, 'production_plan_id');
    }

    // ── Accessors ─────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isImmutable(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    /**
     * Total good quantity produced across all actuals.
     * good_qty is a GENERATED ALWAYS AS column in production_actuals.
     */
    public function totalGoodQty(): int
    {
        return (int) $this->actuals()->sum('good_qty');
    }

    /**
     * Attainment percentage: actual good output vs planned.
     */
    public function attainmentPct(): float
    {
        if ($this->planned_qty === 0) {
            return 0.0;
        }

        return round($this->totalGoodQty() / $this->planned_qty * 100, 2);
    }
}
