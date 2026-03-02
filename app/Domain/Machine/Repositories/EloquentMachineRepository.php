<?php

declare(strict_types=1);

namespace App\Domain\Machine\Repositories;

use App\Domain\Machine\DataTransferObjects\MachineData;
use App\Domain\Machine\Models\Machine;
use App\Domain\Machine\Repositories\Contracts\MachineRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * EloquentMachineRepository
 *
 * INDEX TARGETING:
 *   findById()                → PK clustered index
 *   findByDeviceToken()       → uq_machines_device_token (Redis cache wraps this)
 *   findByFactoryAndCode()    → uq_machines_factory_code
 *   paginate()                → idx_machines_factory_id + idx_machines_status/type
 *   allActiveByFactory()      → idx_machines_factory_id filtered by status
 *   isCodeUniqueInFactory()   → uq_machines_factory_code
 *   countByFactory()          → idx_machines_factory_id (COUNT; no row fetch)
 */
class EloquentMachineRepository implements MachineRepositoryInterface
{
    private const TOKEN_CACHE_TTL = 300; // seconds — matches config/iot.php

    public function findById(int $id): ?Machine
    {
        return Machine::query()->forAnyFactory()->find($id);
    }

    /**
     * Device token lookup is on the critical IoT path (50 req/s per machine).
     * Redis cache converts DB lookup into a ~1ms memory read.
     * Cache is busted in rotateDeviceToken().
     */
    public function findByDeviceToken(string $token): ?Machine
    {
        $cacheKey = "machine.token.{$token}";

        $machineData = Cache::remember(
            $cacheKey,
            self::TOKEN_CACHE_TTL,
            fn() => Machine::query()
                ->forAnyFactory()
                ->forDeviceToken($token)     // uq_machines_device_token
                ->where('status', 'active')
                ->first()
                ?->only(['id', 'factory_id', 'status'])
        );

        if ($machineData === null) {
            return null;
        }

        // Return a hydrated Machine without hitting DB again
        return Machine::newModelInstance($machineData)->fill($machineData)->forceFill(['id' => $machineData['id']]);
    }

    public function findByFactoryAndCode(int $factoryId, string $code): ?Machine
    {
        return Machine::query()
            ->forFactory($factoryId)        // uq_machines_factory_code (leading column)
            ->where('code', strtoupper($code))
            ->first();
    }

    public function paginate(int $factoryId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Machine::query()
            ->forFactory($factoryId)        // idx_machines_factory_id
            ->withLatestOee()
            ->withActiveDowntime()
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('machines.status', $filters['status'])  // idx_machines_status
            )
            ->when(
                isset($filters['type']),
                fn($q) => $q->ofType($filters['type'])  // idx_machines_type
            )
            ->when(
                isset($filters['search']),
                fn($q) => $q->search($filters['search'])
            )
            ->ordered()
            ->paginate($perPage);
    }

    public function allActiveByFactory(int $factoryId): Collection
    {
        return Machine::query()
            ->forFactory($factoryId)
            ->active()
            ->ordered()
            ->select(['id', 'name', 'code', 'type'])
            ->get();
    }

    public function distinctTypesByFactory(int $factoryId): Collection
    {
        return Machine::query()
            ->forFactory($factoryId)
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');
    }

    public function create(int $factoryId, MachineData $data): Machine
    {
        return Machine::create([
            ...$data->toArray(),
            'factory_id'   => $factoryId,
            'device_token' => $this->generateToken(),
        ]);
    }

    public function update(Machine $machine, MachineData $data): Machine
    {
        $machine->update($data->toArray());
        return $machine->refresh();
    }

    public function retire(Machine $machine): bool
    {
        $updated = (bool) $machine->update(['status' => 'retired']);

        // Bust cache so retired machine can't receive IoT data
        $this->bustTokenCache($machine->device_token);

        return $updated;
    }

    public function rotateDeviceToken(Machine $machine): string
    {
        $this->bustTokenCache($machine->device_token);

        $newToken = $this->generateToken();

        $machine->update(['device_token' => hash('sha256', $newToken)]);

        // Cache the new token immediately
        Cache::put(
            "machine.token.{$newToken}",
            $machine->only(['id', 'factory_id', 'status']),
            self::TOKEN_CACHE_TTL
        );

        // Return PLAINTEXT token — caller logs/delivers this; never stored in plain
        return $newToken;
    }

    public function isCodeUniqueInFactory(int $factoryId, string $code, ?int $ignoreId = null): bool
    {
        return ! Machine::query()
            ->forFactory($factoryId)               // uq_machines_factory_code (leading column)
            ->where('code', strtoupper($code))
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function countByFactory(int $factoryId): int
    {
        return Machine::query()
            ->forFactory($factoryId)
            ->count();
    }

    // ── Private ───────────────────────────────────────────────

    private function generateToken(): string
    {
        return hash('sha256', uniqid('mch_', true) . random_bytes(32));
    }

    private function bustTokenCache(string $hashedToken): void
    {
        Cache::forget("machine.token.{$hashedToken}");
    }
}
