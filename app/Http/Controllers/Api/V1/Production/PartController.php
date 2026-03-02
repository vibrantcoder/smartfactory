<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Production\DataTransferObjects\PartData;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Services\PartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\CreatePartRequest;
use App\Http\Requests\Api\Production\SyncPartProcessesRequest;
use App\Http\Requests\Api\Production\UpdatePartRequest;
use App\Http\Resources\Production\PartResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * PartController
 *
 * ROUTE STACK (routes/api_v1.php):
 *   Route::middleware(['auth:sanctum', 'factory.scope', 'factory.member'])
 *        ->apiResource('parts', PartController::class);
 *
 *   // Extra route for routing management:
 *   Route::middleware(['auth:sanctum', 'factory.scope', 'factory.member'])
 *        ->put('parts/{part}/processes', [PartController::class, 'syncProcesses'])
 *        ->name('parts.processes.sync');
 *
 * DESIGN RULES:
 *   1. No business logic — delegates to PartService.
 *   2. No DB queries — repository interface only.
 *   3. destroy() discontinues, never hard-deletes.
 *   4. syncProcesses() is a separate action from CRUD updates.
 */
class PartController extends Controller
{
    public function __construct(
        private readonly PartService $service,
    ) {}

    // ── index ─────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Part::class);

        $user      = $request->user();
        $factoryId = $user->factory_id
            ?? ($request->has('factory_id') ? $request->integer('factory_id') : null);

        $parts = $this->service->list(
            factoryId: $factoryId,
            filters:   $request->only(['search', 'status', 'customer_id']),
            perPage:   $request->integer('per_page', 25),
        );

        return PartResource::collection($parts);
    }

    // ── show ──────────────────────────────────────────────────

    public function show(Part $part): PartResource
    {
        $this->authorize('view', $part);

        $part->loadMissing([
            'customer:id,factory_id,name,code,status',
            'processes.processMaster',
        ]);

        return new PartResource($part);
    }

    // ── store ─────────────────────────────────────────────────

    public function store(CreatePartRequest $request): JsonResponse
    {
        $this->authorize('create', Part::class);

        // Factory users use their own factory_id.
        // Super-admin passes factory_id in the request body (validated by CreatePartRequest).
        $factoryId = $request->user()->factory_id ?? $request->integer('factory_id');

        try {
            $part = $this->service->create(
                $factoryId,
                PartData::fromCreateRequest($request)
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return (new PartResource($part))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // ── update ────────────────────────────────────────────────

    public function update(UpdatePartRequest $request, Part $part): PartResource
    {
        $this->authorize('update', $part);

        try {
            $updated = $this->service->update($part, PartData::fromUpdateRequest($request));
        } catch (\DomainException $e) {
            abort(Response::HTTP_CONFLICT, $e->getMessage());
        }

        return new PartResource($updated);
    }

    // ── destroy ───────────────────────────────────────────────

    /**
     * Discontinues the part — no hard delete.
     * Will fail with 409 if part has active/scheduled production plans.
     */
    public function destroy(Part $part): JsonResponse
    {
        $this->authorize('delete', $part);

        try {
            $this->service->discontinue($part);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json(
            ['message' => "Part [{$part->part_number}] has been discontinued."],
            Response::HTTP_OK
        );
    }

    // ── syncProcesses ─────────────────────────────────────────

    /**
     * Replace the entire process routing for a part.
     *
     * PUT /parts/{part}/processes
     *
     * Request body:
     * {
     *   "processes": [
     *     {"process_master_id": 1, "machine_type_required": "Laser", "standard_cycle_time": 45, "notes": null},
     *     {"process_master_id": 2, "machine_type_required": null,    "standard_cycle_time": null,"notes": null}
     *   ]
     * }
     *
     * Returns the full part with updated processes loaded.
     * Atomic — either all steps are saved or none.
     */
    public function syncProcesses(SyncPartProcessesRequest $request, Part $part): PartResource
    {
        $this->authorize('update', $part);

        try {
            $this->service->syncRouting($part, $request->validated('processes'));
        } catch (\DomainException $e) {
            abort(Response::HTTP_CONFLICT, $e->getMessage());
        }

        // Reload part with fresh routing
        $part->load('processes.processMaster');

        return new PartResource($part);
    }
}
