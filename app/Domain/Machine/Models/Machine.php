<?php

declare(strict_types=1);

namespace App\Domain\Machine\Models;

use App\Domain\Analytics\Models\MachineLogDaily;
use App\Domain\Analytics\Models\MachineLogHourly;
use App\Domain\Analytics\Models\MachineOeeDaily;
use App\Domain\Machine\QueryBuilders\MachineQueryBuilder;
use App\Domain\Shared\Models\BaseModel;
use App\Domain\Shared\Traits\BelongsToFactory;
use App\Domain\Shared\Traits\HasFactoryScope;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Machine
 *
 * Core IoT entity. Owns the highest-volume data relationships
 * (machine_logs, oee_daily). All relationships are lazy by default —
 * always eager-load explicitly to avoid N+1.
 *
 * @property int         $id
 * @property int         $factory_id
 * @property string      $name
 * @property string      $code            unique within factory (uq_machines_factory_code)
 * @property string      $type            CNC|Press|Lathe|Robot|…
 * @property string|null $model
 * @property string|null $manufacturer
 * @property string      $device_token    SHA-256 hex; IoT auth (uq_machines_device_token)
 * @property string      $status          active|maintenance|retired
 * @property \Carbon\Carbon|null $installed_at
 *
 * @method static MachineQueryBuilder query()
 * @method static MachineQueryBuilder active()
 * @method static MachineQueryBuilder ofType(string $type)
 * @method static MachineQueryBuilder forFactory(int $factoryId)
 */
class Machine extends BaseModel
{
    use HasFactoryScope, BelongsToFactory;

    protected $table = 'machines';

    protected $fillable = [
        'factory_id',
        'name',
        'code',
        'type',
        'model',
        'manufacturer',
        'device_token',
        'status',
        'installed_at',
    ];

    /**
     * device_token is NEVER included in API responses.
     * It is excluded here and hidden in MachineResource.
     */
    protected $hidden = ['device_token'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'installed_at' => 'date',
            'status'       => 'string',
        ];
    }

    // ── Custom QueryBuilder ───────────────────────────────────

    public function newEloquentBuilder($query): MachineQueryBuilder
    {
        return new MachineQueryBuilder($query);
    }

    // ── Relationships ─────────────────────────────────────────

    /**
     * Raw IoT logs — partitioned table.
     * NEVER eager-load this without a tight date range constraint.
     * Use machine_logs_hourly for dashboard reads instead.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MachineLog::class, 'machine_id');
    }

    /**
     * Hourly aggregations — safe to query without date restriction.
     * Source for live dashboard trending.
     */
    public function hourlyLogs(): HasMany
    {
        return $this->hasMany(MachineLogHourly::class, 'machine_id');
    }

    /**
     * Daily aggregations — source for historical reports.
     */
    public function dailyLogs(): HasMany
    {
        return $this->hasMany(MachineLogDaily::class, 'machine_id');
    }

    /**
     * OEE per shift per day.
     */
    public function oeeDailyRecords(): HasMany
    {
        return $this->hasMany(MachineOeeDaily::class, 'machine_id');
    }

    /**
     * Latest OEE record (today).
     */
    public function latestOee(): HasOne
    {
        return $this->hasOne(MachineOeeDaily::class, 'machine_id')
            ->latestOfMany('oee_date');
    }

    /**
     * Downtime records.
     */
    public function downtimes(): HasMany
    {
        return $this->hasMany(
            \App\Domain\Downtime\Models\Downtime::class,
            'machine_id'
        );
    }

    /**
     * Currently open (unresolved) downtime.
     */
    public function activeDowntime(): HasOne
    {
        return $this->hasOne(
            \App\Domain\Downtime\Models\Downtime::class,
            'machine_id'
        )->whereNull('ended_at');
    }

    /**
     * Production plans assigned to this machine.
     */
    public function productionPlans(): HasMany
    {
        return $this->hasMany(
            \App\Domain\Production\Models\ProductionPlan::class,
            'machine_id'
        );
    }

    // ── Accessors ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }

    public function isRetired(): bool
    {
        return $this->status === 'retired';
    }
}
