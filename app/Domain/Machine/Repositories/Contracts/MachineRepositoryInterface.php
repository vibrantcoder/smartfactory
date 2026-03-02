<?php

declare(strict_types=1);

namespace App\Domain\Machine\Repositories\Contracts;

use App\Domain\Machine\DataTransferObjects\MachineData;
use App\Domain\Machine\Models\Machine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MachineRepositoryInterface
{
    public function findById(int $id): ?Machine;

    /**
     * Device token lookup — hot path for IoT auth.
     * Implementation MUST use a Redis cache layer.
     */
    public function findByDeviceToken(string $token): ?Machine;

    /**
     * Composite lookup — uses uq_machines_factory_code index.
     */
    public function findByFactoryAndCode(int $factoryId, string $code): ?Machine;

    /**
     * Paginated list with optional filters.
     *
     * @param array{search?: string, status?: string, type?: string} $filters
     */
    public function paginate(int $factoryId, array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active machines for a factory — used by production plan dropdowns.
     * Minimal columns: id, name, code, type.
     */
    public function allActiveByFactory(int $factoryId): Collection;

    /**
     * Distinct machine types for a factory — populates type filter dropdown.
     */
    public function distinctTypesByFactory(int $factoryId): Collection;

    public function create(int $factoryId, MachineData $data): Machine;

    public function update(Machine $machine, MachineData $data): Machine;

    /**
     * Retire a machine — sets status = 'retired'.
     * Hard-delete is disallowed (machine_logs reference machine_id).
     */
    public function retire(Machine $machine): bool;

    /**
     * Rotate the device token — invalidates previous IoT credential.
     * Returns the new plaintext token (store it; not stored in plaintext).
     */
    public function rotateDeviceToken(Machine $machine): string;

    /**
     * Is the code unique within the factory?
     */
    public function isCodeUniqueInFactory(int $factoryId, string $code, ?int $ignoreId = null): bool;

    public function countByFactory(int $factoryId): int;
}
