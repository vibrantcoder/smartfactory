<?php

declare(strict_types=1);

namespace App\Domain\Machine\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IotLog
 *
 * Raw telemetry record from a factory device (PLC / SCADA slave).
 * Append-only — no updated_at, never mutated after insert.
 *
 * @property int                   $id
 * @property int                   $machine_id
 * @property int                   $factory_id
 * @property int                   $alarm_code   0 = OK, >0 = fault code
 * @property bool                  $auto_mode    1=auto, 0=manual
 * @property bool                  $cycle_state  1=running, 0=idle/stopped
 * @property int                   $part_count   cumulative counter from device
 * @property int                   $part_reject  cumulative reject counter
 * @property string|null           $slave_id
 * @property string|null           $slave_name
 * @property \Carbon\Carbon        $logged_at
 * @property \Carbon\Carbon|null   $created_at
 */
class IotLog extends BaseModel
{
    protected $table = 'iot_logs';

    /**
     * Append-only log — no updated_at column.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'machine_id',
        'factory_id',
        'alarm_code',
        'auto_mode',
        'cycle_state',
        'part_count',
        'part_reject',
        'slave_id',
        'slave_name',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'alarm_code'  => 'integer',
            'auto_mode'   => 'boolean',
            'cycle_state' => 'boolean',
            'part_count'  => 'integer',
            'part_reject' => 'integer',
            'logged_at'   => 'datetime',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
