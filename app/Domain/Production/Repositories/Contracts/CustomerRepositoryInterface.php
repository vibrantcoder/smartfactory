<?php

declare(strict_types=1);

namespace App\Domain\Production\Repositories\Contracts;

use App\Domain\Production\DataTransferObjects\CustomerData;
use App\Domain\Production\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CustomerRepositoryInterface
{
    /**
     * Find a single customer by PK, bypassing factory scope.
     * Returns null if not found.
     */
    public function findById(int $id): ?Customer;

    /**
     * Find by factory + code composite. Hits uq_customers_factory_code.
     */
    public function findByFactoryAndCode(int $factoryId, string $code): ?Customer;

    /**
     * Paginated list for the index endpoint.
     * Supported filters: search (string), status (string).
     * When $factoryId is null (super-admin), the global FactoryScope applies (all factories visible).
     */
    public function paginate(int|null $factoryId, array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active customers for a factory — used in select dropdowns.
     * Returns minimal columns: id, name, code.
     */
    public function allActiveByFactory(int $factoryId): Collection;

    /**
     * Persist a new customer.
     */
    public function create(int $factoryId, CustomerData $data): Customer;

    /**
     * Update an existing customer.
     */
    public function update(Customer $customer, CustomerData $data): Customer;

    /**
     * Deactivate (soft-status) — no hard delete; preserves part + plan history.
     */
    public function deactivate(Customer $customer): bool;

    /**
     * Check code uniqueness within factory. Optionally excludes $ignoreId.
     * Targets uq_customers_factory_code composite index.
     */
    public function isCodeUniqueInFactory(int $factoryId, string $code, ?int $ignoreId = null): bool;

    /**
     * Total customer count in a factory (for dashboard counters).
     */
    public function countByFactory(int $factoryId): int;
}
