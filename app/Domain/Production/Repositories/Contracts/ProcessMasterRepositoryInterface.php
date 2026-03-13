<?php

declare(strict_types=1);

namespace App\Domain\Production\Repositories\Contracts;

use App\Domain\Production\DataTransferObjects\ProcessMasterData;
use App\Domain\Production\Models\ProcessMaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProcessMasterRepositoryInterface
{
    /**
     * Find by PK.
     */
    public function findById(int $id): ?ProcessMaster;

    /**
     * Find by code — globally unique. Hits uq_process_masters_code.
     */
    public function findByCode(string $code): ?ProcessMaster;

    /**
     * Paginated list for the management index.
     * Supported filters: search (string), is_active (bool), machine_type_default (string).
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active process masters — used to populate the routing builder palette.
     * Returns full objects for the routing builder palette.
     */
    public function allActive(): Collection;

    /**
     * All active process masters limited to those matching a machine type.
     * Used when building routing for a specific machine type restriction.
     */
    public function allActiveForMachineType(string $machineType): Collection;

    /**
     * Fetch many by IDs in one query — used for total cycle time calculation.
     * Returns a Collection keyed by id for O(1) lookup.
     */
    public function findManyByIds(array $ids): Collection;

    public function create(ProcessMasterData $data): ProcessMaster;

    public function update(ProcessMaster $processMaster, ProcessMasterData $data): ProcessMaster;

    /**
     * Deactivate — no hard delete (routing history must remain intact).
     *
     * @throws \DomainException if processMaster is used in active parts
     */
    public function deactivate(ProcessMaster $processMaster): bool;

    /**
     * Check code uniqueness globally. Optionally excludes $ignoreId for updates.
     */
    public function isCodeUnique(string $code, ?int $ignoreId = null): bool;
}
