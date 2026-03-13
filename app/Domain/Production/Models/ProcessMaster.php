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
 * UNIT CONVENTION: all cycle times in this system are MINUTES.
 *
 * @property int         $id
 * @property string      $name                  e.g. "Laser Cutting"
 * @property string      $code                  e.g. "LASER" — globally unique
 * @property string|null $machine_type_default  default machine type for routing steps
 * @property string      $process_type          'inhouse' or 'outside'
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
        'machine_type_default',
        'process_type',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_active' => 'boolean',
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
