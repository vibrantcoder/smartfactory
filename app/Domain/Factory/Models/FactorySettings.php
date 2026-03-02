<?php

declare(strict_types=1);

namespace App\Domain\Factory\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FactorySettings
 *
 * One-to-one with Factory. Stores runtime configuration consumed
 * by scheduler jobs, OEE alerts, and aggregation pipelines.
 *
 * @property int     $id
 * @property int     $factory_id
 * @property float   $working_hours_per_day     Total planned operating hours per calendar day
 *                                              (sum across all shifts, e.g. 24.0 for 3 × 8h shifts)
 *                                              Used as denominator in daily target calculations.
 * @property float   $oee_target_pct
 * @property float   $availability_target_pct
 * @property float   $performance_target_pct
 * @property float   $quality_target_pct
 * @property int     $log_interval_seconds
 * @property int     $downtime_threshold_min
 * @property int     $aggregation_lag_min
 * @property int     $raw_log_retention_days
 */
class FactorySettings extends BaseModel
{
    protected $table = 'factory_settings';

    protected $fillable = [
        'factory_id',
        'working_hours_per_day',
        'oee_target_pct',
        'availability_target_pct',
        'performance_target_pct',
        'quality_target_pct',
        'log_interval_seconds',
        'downtime_threshold_min',
        'aggregation_lag_min',
        'raw_log_retention_days',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'working_hours_per_day'    => 'decimal:2',
            'oee_target_pct'           => 'decimal:2',
            'availability_target_pct'  => 'decimal:2',
            'performance_target_pct'   => 'decimal:2',
            'quality_target_pct'       => 'decimal:2',
            'log_interval_seconds'     => 'integer',
            'downtime_threshold_min'   => 'integer',
            'aggregation_lag_min'      => 'integer',
            'raw_log_retention_days'   => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Returns settings merged with config defaults.
     * Safe for use when settings row may not yet exist.
     */
    public static function resolveFor(int $factoryId): self
    {
        return static::firstOrCreate(
            ['factory_id' => $factoryId],
            config('oee.defaults', [
                'working_hours_per_day'   => 8.00,
                'oee_target_pct'          => 85.00,
                'availability_target_pct' => 90.00,
                'performance_target_pct'  => 95.00,
                'quality_target_pct'      => 99.00,
                'log_interval_seconds'    => 5,
                'downtime_threshold_min'  => 5,
                'aggregation_lag_min'     => 10,
                'raw_log_retention_days'  => 90,
            ])
        );
    }
}
