<?php

declare(strict_types=1);

namespace App\Domain\Production\Repositories;

use App\Domain\Production\DataTransferObjects\CustomerData;
use App\Domain\Production\Models\Customer;
use App\Domain\Production\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * EloquentCustomerRepository
 *
 * INDEX TARGETING:
 *   findById()               → PK clustered index
 *   findByFactoryAndCode()   → uq_customers_factory_code
 *   paginate()               → idx_customers_factory_id + idx_customers_status
 *   allActiveByFactory()     → idx_customers_factory_id filtered by status
 *   isCodeUniqueInFactory()  → uq_customers_factory_code
 *   countByFactory()         → idx_customers_factory_id (COUNT; no row fetch)
 */
class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer
    {
        // forAnyFactory() bypasses the global FactoryScope
        return Customer::query()->forAnyFactory()->find($id);
    }

    public function findByFactoryAndCode(int $factoryId, string $code): ?Customer
    {
        return Customer::query()
            ->forFactory($factoryId)           // uq_customers_factory_code (leading column)
            ->where('code', strtoupper($code))
            ->first();
    }

    public function paginate(int|null $factoryId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Customer::query();

        // Super-admin (null): FactoryScope global scope applies (no WHERE — sees all factories)
        // Factory user: forFactory() removes global scope and adds explicit WHERE factory_id = ?
        if ($factoryId !== null) {
            $query->forFactory($factoryId);    // idx_customers_factory_id
        }

        return $query
            ->withPartCount()
            ->withActivePartCount()
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('customers.status', $filters['status'])  // idx_customers_status
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
        return Customer::query()
            ->forFactory($factoryId)
            ->active()
            ->ordered()
            ->select(['id', 'name', 'code'])
            ->get();
    }

    public function create(int $factoryId, CustomerData $data): Customer
    {
        return Customer::create([
            ...$data->toArray(),
            'factory_id' => $factoryId,
        ]);
    }

    public function update(Customer $customer, CustomerData $data): Customer
    {
        $customer->update($data->toArray());
        return $customer->refresh();
    }

    public function deactivate(Customer $customer): bool
    {
        return (bool) $customer->update(['status' => 'inactive']);
    }

    public function isCodeUniqueInFactory(int $factoryId, string $code, ?int $ignoreId = null): bool
    {
        return ! Customer::query()
            ->forFactory($factoryId)           // uq_customers_factory_code (leading column)
            ->where('code', strtoupper($code))
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function countByFactory(int $factoryId): int
    {
        return Customer::query()
            ->forFactory($factoryId)
            ->count();
    }
}
