<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Production\DataTransferObjects\CustomerData;
use App\Domain\Production\Models\Customer;
use App\Domain\Production\Services\CustomerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\CreateCustomerRequest;
use App\Http\Requests\Api\Production\UpdateCustomerRequest;
use App\Http\Resources\Production\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * CustomerController
 *
 * ROUTE STACK (routes/api_v1.php):
 *   Route::middleware(['auth:sanctum', 'factory.scope', 'factory.member'])
 *        ->apiResource('customers', CustomerController::class);
 *
 * DESIGN RULES:
 *   1. No business logic — delegates entirely to CustomerService.
 *   2. No DB queries — never touches Eloquent directly.
 *   3. No raw arrays in responses — always uses CustomerResource.
 *   4. factory_id is NEVER accepted from request body.
 *   5. destroy() deactivates, never hard-deletes.
 */
class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $service,
    ) {}

    // ── index ─────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        // Super-admin (factory_id=null) may pass ?factory_id=X to scope the listing
        // to a specific factory (e.g. for customer dropdown in the parts create form).
        $factoryId = $request->user()->factory_id
            ?? ($request->has('factory_id') ? $request->integer('factory_id') : null);

        $customers = $this->service->list(
            factoryId: $factoryId,
            filters:   $request->only(['search', 'status']),
            perPage:   $request->integer('per_page', 25),
        );

        return CustomerResource::collection($customers);
    }

    // ── show ──────────────────────────────────────────────────

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        $customer->loadMissing('parts.processes.processMaster');
        $customer->loadCount(['parts', 'parts as active_parts_count' => fn($q) => $q->where('status', 'active')]);

        return new CustomerResource($customer);
    }

    // ── store ─────────────────────────────────────────────────

    public function store(CreateCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        // Factory users use their own factory_id.
        // Super-admin passes factory_id in the request body (validated by CreateCustomerRequest).
        $factoryId = $request->user()->factory_id ?? $request->integer('factory_id');

        try {
            $customer = $this->service->create(
                $factoryId,
                CustomerData::fromCreateRequest($request)
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // ── update ────────────────────────────────────────────────

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        try {
            $updated = $this->service->update(
                $customer,
                CustomerData::fromUpdateRequest($request)
            );
        } catch (\DomainException $e) {
            abort(Response::HTTP_CONFLICT, $e->getMessage());
        }

        return new CustomerResource($updated);
    }

    // ── destroy ───────────────────────────────────────────────

    /**
     * Deactivates the customer — no hard delete.
     * Will fail with 409 if customer has active parts.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        try {
            $this->service->deactivate($customer);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json(
            ['message' => "Customer [{$customer->code}] has been deactivated."],
            Response::HTTP_OK
        );
    }
}
