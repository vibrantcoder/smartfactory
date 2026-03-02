<?php

declare(strict_types=1);

namespace App\Domain\Production\Repositories\Contracts;

use App\Domain\Production\DataTransferObjects\PartData;
use App\Domain\Production\Models\Part;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PartRepositoryInterface
{
    /**
     * Find a single part by PK, bypassing factory scope.
     */
    public function findById(int $id): ?Part;

    /**
     * Find by factory + part_number. Hits uq_parts_factory_number.
     */
    public function findByFactoryAndNumber(int $factoryId, string $partNumber): ?Part;

    /**
     * Paginated list for the index endpoint.
     * Supported filters: search (string), status (string), customer_id (int).
     * When $factoryId is null (super-admin), the global FactoryScope applies (all factories visible).
     */
    public function paginate(int|null $factoryId, array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active parts in a factory — used in production plan dropdowns.
     * Returns minimal columns: id, customer_id, part_number, name, unit.
     */
    public function allActiveByFactory(int $factoryId): Collection;

    /**
     * All active parts belonging to a specific customer.
     */
    public function allActiveByCustomer(int $customerId): Collection;

    /**
     * Persist a new part. factory_id is injected here, not from DTO.
     */
    public function create(int $factoryId, PartData $data): Part;

    /**
     * Update an existing part's scalar fields.
     * Routing is updated separately via syncProcesses().
     */
    public function update(Part $part, PartData $data): Part;

    /**
     * Mark a part as discontinued. No hard delete — preserves plan history.
     */
    public function discontinue(Part $part): bool;

    /**
     * Atomically replace routing steps for a part AND store the pre-computed total.
     *
     * $processes is an ordered array of routing step definitions:
     * [
     *   ['process_master_id' => 1, 'machine_type_required' => 'Laser', 'standard_cycle_time' => 45, 'notes' => null],
     *   ['process_master_id' => 2, 'machine_type_required' => null,    'standard_cycle_time' => null,'notes' => null],
     * ]
     * sequence_order is auto-assigned as 1-based array index.
     * $totalCycleTime is calculated by PartService and stored on the parts row atomically.
     * Runs inside a single DB transaction — all steps saved + total updated or nothing.
     */
    public function syncProcesses(Part $part, array $processes, float $totalCycleTime): void;

    /**
     * Check part_number uniqueness within factory. Optionally excludes $ignoreId.
     * Targets uq_parts_factory_number composite index.
     */
    public function isNumberUniqueInFactory(int $factoryId, string $partNumber, ?int $ignoreId = null): bool;

    /**
     * Total part count for a factory.
     */
    public function countByFactory(int $factoryId): int;

    /**
     * Active part count for a specific customer.
     */
    public function countActiveByCustomer(int $customerId): int;
}
