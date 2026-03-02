<?php

declare(strict_types=1);

namespace App\Domain\Machine\QueryBuilders;

use App\Domain\Shared\Enums\MachineStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * MachineQueryBuilder
 *
 * INDEX TARGETING:
 *   active()         → idx_machines_status
 *   ofType()         → idx_machines_type
 *   forFactory()     → idx_machines_factory_id  (via BelongsToFactory scope)
 *   forDeviceToken() → uq_machines_device_token (unique index — O(1) lookup)
 *   withOeeForDate() → uq_oee_machine_shift_date
 */
class MachineQueryBuilder extends Builder
{
    // ── Status Filters ────────────────────────────────────────

    public function active(): static
    {
        return $this->where('machines.status', 'active');
    }

    public function inMaintenance(): static
    {
        return $this->where('machines.status', 'maintenance');
    }

    public function retired(): static
    {
        return $this->where('machines.status', 'retired');
    }

    public function operational(): static
    {
        // Active OR in maintenance — any machine that could be logging data
        return $this->whereIn('machines.status', ['active', 'maintenance']);
    }

    // ── Type Filter ───────────────────────────────────────────

    /**
     * Filter by machine type. Hits idx_machines_type.
     */
    public function ofType(string $type): static
    {
        return $this->where('machines.type', $type);
    }

    /**
     * Filter by multiple machine types.
     */
    public function ofTypes(array $types): static
    {
        return $this->whereIn('machines.type', $types);
    }

    // ── IoT Auth ─────────────────────────────────────────────

    /**
     * Find by device token — hits uq_machines_device_token (unique index).
     * Used by AuthenticateMachineDevice middleware.
     * ALWAYS select minimal columns for this lookup — it's hot path.
     */
    public function forDeviceToken(string $token): static
    {
        return $this->where('machines.device_token', $token)
                    ->select(['machines.id', 'machines.factory_id', 'machines.status']);
    }

    // ── Search ────────────────────────────────────────────────

    public function search(string $term): static
    {
        $like = "%{$term}%";

        return $this->where(function (Builder $q) use ($like) {
            $q->where('machines.name',         'LIKE', $like)
              ->orWhere('machines.code',         'LIKE', $like)
              ->orWhere('machines.type',         'LIKE', $like)
              ->orWhere('machines.model',        'LIKE', $like)
              ->orWhere('machines.manufacturer', 'LIKE', $like);
        });
    }

    // ── Eager Loads ───────────────────────────────────────────

    /**
     * Attach the latest OEE record for the given date.
     * Hits uq_oee_machine_shift_date composite index.
     */
    public function withOeeForDate(Carbon $date): static
    {
        return $this->with([
            'oeeDailyRecords' => fn($q) => $q->where('oee_date', $date->toDateString())
                                             ->select([
                                                 'machine_id', 'oee_date',
                                                 'oee_pct', 'availability_pct',
                                                 'performance_pct', 'quality_pct',
                                             ]),
        ]);
    }

    /**
     * Load only the latest hourly log snapshot (for floor map widget).
     * Never loads raw machine_logs — uses the aggregation table.
     */
    public function withLatestHourlySnapshot(): static
    {
        return $this->with([
            'hourlyLogs' => fn($q) => $q
                ->where('hour_start', '>=', now()->startOfHour()->subHour())
                ->orderBy('hour_start', 'desc')
                ->limit(1)
                ->select(['machine_id', 'hour_start', 'total_count', 'runtime_min', 'fault_min', 'avg_power_kw']),
        ]);
    }

    /**
     * Load the currently open downtime if any.
     */
    public function withActiveDowntime(): static
    {
        return $this->with([
            'activeDowntime' => fn($q) => $q->with('reason:id,category,description')
                                            ->select(['id', 'machine_id', 'started_at', 'category', 'reason_id']),
        ]);
    }

    public function withLatestOee(): static
    {
        return $this->with('latestOee:id,machine_id,oee_date,oee_pct,availability_pct,performance_pct,quality_pct');
    }

    public function withFactory(): static
    {
        return $this->with('factory:id,name,code,timezone');
    }

    // ── Ordering ──────────────────────────────────────────────

    public function ordered(): static
    {
        return $this->orderBy('machines.name');
    }

    public function orderedByCode(): static
    {
        return $this->orderBy('machines.code');
    }
}
