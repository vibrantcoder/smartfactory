<?php

declare(strict_types=1);

namespace App\Domain\Factory\Services;

use App\Domain\Factory\DataTransferObjects\FactoryData;
use App\Domain\Factory\Events\FactoryCreatedEvent;
use App\Domain\Factory\Models\Factory;
use App\Domain\Factory\Repositories\Contracts\FactoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * FactoryService
 *
 * Orchestrates factory operations and enforces business rules
 * that span multiple repositories or require event dispatch.
 *
 * RESPONSIBILITY SPLIT:
 *   Service   → business rules, event dispatch, cross-repo orchestration
 *   Repository → data access, query building, transaction management
 *   Controller → HTTP in/out, request validation, response shaping
 */
class FactoryService
{
    public function __construct(
        private readonly FactoryRepositoryInterface $factories,
    ) {}

    // ── Queries ───────────────────────────────────────────────

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->factories->paginate($filters, $perPage);
    }

    public function findOrFail(int $id): Factory
    {
        return $this->factories->findById($id)
            ?? throw new \App\Domain\Factory\Exceptions\FactoryNotFoundException($id);
    }

    public function allActiveForDropdown(): Collection
    {
        return $this->factories->allActive();
    }

    // ── Write Operations ──────────────────────────────────────

    public function create(FactoryData $data): Factory
    {
        $this->guardDuplicateCode($data->code);

        $factory = $this->factories->create($data);

        event(new FactoryCreatedEvent($factory));

        return $factory;
    }

    public function update(Factory $factory, FactoryData $data): Factory
    {
        $this->guardDuplicateCode($data->code, ignoreId: $factory->id);

        return $this->factories->update($factory, $data);
    }

    /**
     * Deactivate a factory.
     *
     * BUSINESS RULE: Cannot deactivate a factory that has active machines.
     * Active machines should be retired first to prevent orphaned IoT data.
     */
    public function deactivate(Factory $factory): void
    {
        $activeMachineCount = $factory->machines()
            ->where('status', 'active')
            ->count();

        if ($activeMachineCount > 0) {
            throw new \DomainException(
                "Cannot deactivate factory [{$factory->code}]: " .
                "{$activeMachineCount} active machine(s) must be retired first."
            );
        }

        $this->factories->deactivate($factory);
    }

    // ── Guards ────────────────────────────────────────────────

    private function guardDuplicateCode(string $code, ?int $ignoreId = null): void
    {
        if (! $this->factories->isCodeUnique($code, $ignoreId)) {
            throw new \DomainException(
                "Factory code [{$code}] is already in use. Codes must be globally unique."
            );
        }
    }
}
