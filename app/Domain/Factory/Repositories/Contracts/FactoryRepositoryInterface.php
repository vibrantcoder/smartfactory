<?php

declare(strict_types=1);

namespace App\Domain\Factory\Repositories\Contracts;

use App\Domain\Factory\DataTransferObjects\FactoryData;
use App\Domain\Factory\Models\Factory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * FactoryRepositoryInterface
 *
 * Contract for all factory data-access operations.
 * The concrete implementation (Eloquent) is bound in RepositoryServiceProvider.
 * A future HttpFactoryRepository can be swapped in when this domain
 * is extracted to its own microservice.
 */
interface FactoryRepositoryInterface
{
    /**
     * Find a factory by primary key. Returns null if not found.
     */
    public function findById(int $id): ?Factory;

    /**
     * Find a factory by its unique code (e.g. FAC-BKK-01).
     */
    public function findByCode(string $code): ?Factory;

    /**
     * Paginated list for the admin panel. Supports search + status filter.
     *
     * @param  array{search?: string, status?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active factories — for dropdowns and seeder references.
     */
    public function allActive(): Collection;

    /**
     * Create a new factory from a DTO.
     * Also creates a default FactorySettings row.
     */
    public function create(FactoryData $data): Factory;

    /**
     * Update an existing factory from a DTO.
     */
    public function update(Factory $factory, FactoryData $data): Factory;

    /**
     * Soft-deactivate a factory (status = inactive).
     * Hard-delete is disallowed at the repository level.
     */
    public function deactivate(Factory $factory): bool;

    /**
     * Check whether a code is unique, optionally ignoring a specific ID.
     * Used by form request validation.
     */
    public function isCodeUnique(string $code, ?int $ignoreId = null): bool;

    /**
     * Count total factories (for Super Admin dashboard).
     */
    public function count(): int;
}
