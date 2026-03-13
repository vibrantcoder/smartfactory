<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PartProcess — Routing Table
 *
 * Ordered manufacturing steps a part must pass through.
 * sequence_order is 1-based; unique per part (uq_part_processes_part_seq).
 *
 * EXAMPLE (Bracket-A001):
 *   Seq 1 → Laser Cutting  (process_master_id: 1, machine_type: Laser)
 *   Seq 2 → Bending        (process_master_id: 2, machine_type: Press)
 *   Seq 3 → Welding        (process_master_id: 3, machine_type: Welder)
 *   Seq 4 → QC Inspection  (process_master_id: 4, machine_type: QC)
 *
 * @property int         $id
 * @property int         $part_id
 * @property int         $process_master_id
 * @property int         $sequence_order            1-based; UNIQUE per part
 * @property string|null $machine_type_required     restricts eligible machines
 * @property float|null  $standard_cycle_time       part-specific cycle time override
 * @property float|null  $setup_time                setup/changeover time in minutes
 * @property string      $process_type              'inhouse' or 'outside'
 * @property string|null $notes
 */
class PartProcess extends BaseModel
{
    protected $table = 'part_processes';

    protected $fillable = [
        'part_id',
        'process_master_id',
        'sequence_order',
        'machine_type_required',
        'standard_cycle_time',
        'setup_time',
        'load_unload_time',
        'process_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'sequence_order'      => 'integer',
            'standard_cycle_time' => 'decimal:2',
            'setup_time'          => 'decimal:2',
            'load_unload_time'    => 'decimal:2',
        ];
    }

    // ── Relationships ─────────────────────────────────────────

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    public function processMaster(): BelongsTo
    {
        return $this->belongsTo(ProcessMaster::class, 'process_master_id');
    }

    // ── Accessors ─────────────────────────────────────────────

    /**
     * Effective cycle time — uses part-specific override if set, otherwise 0.0.
     */
    public function effectiveCycleTime(): float
    {
        if ($this->standard_cycle_time !== null) {
            return (float) $this->standard_cycle_time;
        }

        return 0.0;
    }
}
