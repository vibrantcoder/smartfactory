<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Shift;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MachineOeeShift
 *
 * Pre-aggregated OEE record for one machine × shift × date.
 * Populated by OeeAggregationService; read by OeeController.
 *
 * @property int         $id
 * @property int         $machine_id
 * @property int         $factory_id
 * @property int         $shift_id
 * @property string      $oee_date          Y-m-d
 * @property int         $planned_qty
 * @property int         $total_parts
 * @property int         $good_parts
 * @property int         $reject_parts
 * @property int         $planned_minutes
 * @property int         $alarm_minutes
 * @property int         $available_minutes
 * @property float       $availability_pct
 * @property float|null  $performance_pct   null if no cycle_time_std
 * @property float       $quality_pct
 * @property float|null  $oee_pct           null if no cycle_time_std
 * @property float|null  $attainment_pct    null if no plan
 * @property int         $log_count
 * @property int         $log_interval_seconds
 * @property \Carbon\Carbon|null $calculated_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MachineOeeShift extends BaseModel
{
    protected $table = 'machine_oee_shifts';

    protected $fillable = [
        'machine_id',
        'factory_id',
        'shift_id',
        'oee_date',
        'planned_qty',
        'total_parts',
        'good_parts',
        'reject_parts',
        'planned_minutes',
        'alarm_minutes',
        'available_minutes',
        'availability_pct',
        'performance_pct',
        'quality_pct',
        'oee_pct',
        'attainment_pct',
        'log_count',
        'log_interval_seconds',
        'chart_data',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'oee_date'             => 'date',
            'availability_pct'     => 'float',
            'performance_pct'      => 'float',
            'quality_pct'          => 'float',
            'oee_pct'              => 'float',
            'attainment_pct'       => 'float',
            'chart_data'           => 'array',
            'calculated_at'        => 'datetime',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
