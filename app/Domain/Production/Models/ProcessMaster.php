<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Production\QueryBuilders\ProcessMasterQueryBuilder;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProcessMaster — Global Process Library
 *
 * Defines reusable manufacturing process types (Laser Cutting, Bending, Welding…).
 * NOT factory-scoped: shared across all factories (global reference table).
 *
 * standard_time is the DEFAULT cycle time per step in MINUTES.
 * Parts may override it per routing step in part_processes.standard_cycle_time.
 * effectiveCycleTime() on PartProcess resolves which value to use.
 *
 * UNIT CONVENTION: all cycle times in this system are MINUTES.
 *
 * @property int         $id
 * @property string      $name                  e.g. "Laser Cutting"
 * @property string      $code                  e.g. "LASER" — globally unique
 * @property float|null  $standard_time         minutes per cycle (default)
 * @property string|null $machine_type_default  default machine type for routing steps
 * @property string|null $description
 * @property bool        $is_active
 *
 * @method static ProcessMasterQueryBuilder query()
 * @method static ProcessMasterQueryBuilder active()
 * @method static ProcessMasterQueryBuilder search(string $term)
 */
class ProcessMaster extends BaseModel
{
    protected $table = 'process_masters';

    protected $fillable = [
        'name',
        'code',
        'standard_time',
        'machine_type_default',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'standard_time' => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    public function newEloquentBuilder($query): ProcessMasterQueryBuilder
    {
        return new ProcessMasterQueryBuilder($query);
    }

    // ── Relationships ─────────────────────────────────────────

    /**
     * All routing steps that reference this process.
     * Use Part::processes() for ordered routing per part.
     */
    public function partProcesses(): HasMany
    {
        return $this->hasMany(PartProcess::class, 'process_master_id');
    }

    // ── Accessors ─────────────────────────────────────────────

    /**
     * How many parts have a routing step that uses this process.
     */
    public function getUsageCountAttribute(): int
    {
        return $this->partProcesses()->distinct('part_id')->count('part_id');
    }
}
