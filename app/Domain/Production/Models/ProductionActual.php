<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductionActual — Per-Cycle Output Record
 *
 * Records actual production output against a plan.
 * good_qty is a MySQL GENERATED ALWAYS AS (actual_qty - defect_qty) STORED column —
 * never set it directly; it is computed by the database.
 *
 * @property int         $id
 * @property int         $production_plan_id
 * @property int         $actual_qty
 * @property int         $defect_qty
 * @property int         $good_qty           GENERATED ALWAYS AS (actual_qty - defect_qty)
 * @property string|null $recorded_at        DATETIME — when this batch was logged
 * @property string|null $notes
 */
class ProductionActual extends BaseModel
{
    protected $table = 'production_actuals';

    /**
     * good_qty intentionally excluded — it is a GENERATED column.
     * Writing to it would throw a MySQL error.
     */
    protected $fillable = [
        'production_plan_id',
        'actual_qty',
        'defect_qty',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'actual_qty'  => 'integer',
            'defect_qty'  => 'integer',
            'good_qty'    => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }
}
