<?php

declare(strict_types=1);

namespace App\Domain\Production\Services;

use App\Domain\Production\DataTransferObjects\CustomerData;
use App\Domain\Production\Models\Customer;
use App\Domain\Production\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * CustomerService
 *
 * All business logic for the Customer domain.
 * Controllers call this; this calls the repository.
 * No Eloquent queries here — repository interface only.
 */
class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $repository,
    ) {}

    // ── Queries ───────────────────────────────────────────────

    public function list(
        int|null $factoryId,
        array    $filters  = [],
        int      $perPage  = 25,
    ): LengthAwarePaginator {
        return $this->repository->paginate($factoryId, $filters, $perPage);
    }

    public function get(int $id): Customer
    {
        $customer = $this->repository->findById($id);

        if ($customer === null) {
            throw new \DomainException("Customer [{$id}] not found.");
        }

        return $customer;
    }

    public function dropdown(int $factoryId): Collection
    {
        return $this->repository->allActiveByFactory($factoryId);
    }

    // ── Mutations ─────────────────────────────────────────────

    public function create(int $factoryId, CustomerData $data): Customer
    {
        $this->guardDuplicateCode($factoryId, $data->code);

        return $this->repository->create($factoryId, $data);
    }

    public function update(Customer $customer, CustomerData $data): Customer
    {
        $this->guardDuplicateCode($customer->factory_id, $data->code, $customer->id);

        return $this->repository->update($customer, $data);
    }

    /**
     * Deactivate a customer.
     *
     * BUSINESS RULE: A customer with active parts cannot be deactivated.
     * Deactivating a customer with active manufacturing parts would break
     * production planning and OEE linkage.
     *
     * @throws \DomainException when customer has active parts
     */
    public function deactivate(Customer $customer): void
    {
        if ($customer->activeParts()->exists()) {
            throw new \DomainException(
                "Customer [{$customer->code}] has active parts and cannot be deactivated. " .
                "Discontinue all active parts first."
            );
        }

        $this->repository->deactivate($customer);
    }

    // ── Guards ────────────────────────────────────────────────

    /**
     * @throws \DomainException on duplicate code within factory
     */
    private function guardDuplicateCode(int $factoryId, string $code, ?int $ignoreId = null): void
    {
        if (! $this->repository->isCodeUniqueInFactory($factoryId, $code, $ignoreId)) {
            throw new \DomainException(
                "A customer with code [{$code}] already exists in this factory."
            );
        }
    }
}
