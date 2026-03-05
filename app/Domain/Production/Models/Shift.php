<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Models\BaseModel;
use App\Domain\Shared\Traits\BelongsToFactory;
use App\Domain\Shared\Traits\HasFactoryScope;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shift — Factory Work Shift Definition
 *
 * Defines named shifts (Morning, Afternoon, Night) per factory.
 * Used as the time dimension for OEE calculations.
 * machine_oee_daily is keyed by (machine_id, shift_id, oee_date).
 *
 * @property int         $id
 * @property int         $factory_id
 * @property string      $name          e.g. "Morning Shift"
 * @property string      $start_time    HH:MM:SS
 * @property string      $end_time      HH:MM:SS (may cross midnight)
 * @property int         $duration_min  planned operating minutes per shift
 * @property bool        $is_active
 */
class Shift extends BaseModel
{
    use HasFactoryScope, BelongsToFactory;

    protected $table = 'shifts';

    protected $fillable = [
        'factory_id',
        'name',
        'start_time',
        'end_time',
        'duration_min',
        'break_start',
        'break_end',
        'break_min',
        'crosses_midnight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_active'        => 'boolean',
            'crosses_midnight'  => 'boolean',
            'duration_min'     => 'integer',
            'break_min'        => 'integer',
        ];
    }

    /**
     * OEE planned operating minutes = shift duration minus scheduled breaks.
     * Availability = (planned_min - alarm_min) / planned_min
     */
    public function getPlannedMinAttribute(): int
    {
        return max(0, $this->duration_min - ($this->break_min ?? 0));
    }

    // ── Relationships ─────────────────────────────────────────

    public function productionPlans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class, 'shift_id');
    }
}
