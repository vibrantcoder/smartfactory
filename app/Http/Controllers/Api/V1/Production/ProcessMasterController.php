<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Production\DataTransferObjects\ProcessMasterData;
use App\Domain\Production\Models\ProcessMaster;
use App\Domain\Production\Services\ProcessMasterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Production\CreateProcessMasterRequest;
use App\Http\Requests\Api\Production\PreviewCycleTimeRequest;
use App\Http\Requests\Api\Production\UpdateProcessMasterRequest;
use App\Http\Resources\Production\ProcessMasterResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProcessMasterController
 *
 * ROUTE STACK (routes/api_v1.php):
 *   Route::middleware(['auth:sanctum', 'factory.scope'])
 *        ->apiResource('process-masters', ProcessMasterController::class);
 *
 *   // AJAX cycle-time preview (no auth scope needed — stateless calculation)
 *   Route::middleware(['auth:sanctum'])
 *        ->post('process-masters/preview-cycle-time', [ProcessMasterController::class, 'previewCycleTime'])
 *        ->name('process-masters.preview-cycle-time');
 *
 * NOTE: Process masters are NOT factory-scoped (global reference table).
 *       factory.scope middleware is still needed for permission checks.
 *       destroy() deactivates rather than hard-deletes.
 */
class ProcessMasterController extends Controller
{
    public function __construct(
        private readonly ProcessMasterService $service,
    ) {}

    // ── index ─────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProcessMaster::class);

        $processMasters = $this->service->list(
            filters: $request->only(['search', 'is_active', 'machine_type_default']),
            perPage: $request->integer('per_page', 25),
        );

        return ProcessMasterResource::collection($processMasters);
    }

    // ── palette ───────────────────────────────────────────────

    /**
     * Lightweight endpoint for the routing builder palette.
     * GET /api/v1/process-masters/palette
     *
     * Returns all active process masters for the routing builder palette.
     */
    public function palette(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProcessMaster::class);

        return ProcessMasterResource::collection($this->service->palette());
    }

    // ── show ──────────────────────────────────────────────────

    public function show(ProcessMaster $processMaster): ProcessMasterResource
    {
        $this->authorize('view', $processMaster);

        $processMaster->loadCount('partProcesses');

        return new ProcessMasterResource($processMaster);
    }

    // ── store ─────────────────────────────────────────────────

    public function store(CreateProcessMasterRequest $request): JsonResponse
    {
        $this->authorize('create', ProcessMaster::class);

        try {
            $processMaster = $this->service->create(
                ProcessMasterData::fromCreateRequest($request)
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return (new ProcessMasterResource($processMaster))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // ── update ────────────────────────────────────────────────

    public function update(UpdateProcessMasterRequest $request, ProcessMaster $processMaster): ProcessMasterResource
    {
        $this->authorize('update', $processMaster);

        try {
            $updated = $this->service->update(
                $processMaster,
                ProcessMasterData::fromUpdateRequest($request)
            );
        } catch (\DomainException $e) {
            abort(Response::HTTP_CONFLICT, $e->getMessage());
        }

        return new ProcessMasterResource($updated);
    }

    // ── destroy ───────────────────────────────────────────────

    /**
     * Deactivates — no hard delete.
     * Will fail with 409 if process is in use by active parts.
     */
    public function destroy(ProcessMaster $processMaster): JsonResponse
    {
        $this->authorize('delete', $processMaster);

        try {
            $this->service->deactivate($processMaster);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json(
            ['message' => "Process [{$processMaster->code}] has been deactivated."],
            Response::HTTP_OK
        );
    }

    // ── previewCycleTime ──────────────────────────────────────

    /**
     * AJAX endpoint: calculate total cycle time WITHOUT saving.
     *
     * POST /api/v1/process-masters/preview-cycle-time
     *
     * Request:
     * {
     *   "steps": [
     *     {"process_master_id": 1, "standard_cycle_time": 45.0},
     *     {"process_master_id": 2, "standard_cycle_time": null},
     *     {"process_master_id": 3, "standard_cycle_time": 30.0}
     *   ]
     * }
     *
     * Response:
     * {
     *   "steps": [
     *     {"sequence_order": 1, "process_master_id": 1, "override_cycle_time": 45.0, "default_cycle_time": 30.0, "effective_cycle_time": 45.0},
     *     {"sequence_order": 2, "process_master_id": 2, "override_cycle_time": null, "default_cycle_time": 20.0, "effective_cycle_time": 20.0},
     *     {"sequence_order": 3, "process_master_id": 3, "override_cycle_time": 30.0, "default_cycle_time": 25.0, "effective_cycle_time": 30.0}
     *   ],
     *   "total_cycle_time": 95.0
     * }
     */
    public function previewCycleTime(PreviewCycleTimeRequest $request): JsonResponse
    {
        $result = $this->service->previewTotalCycleTime(
            $request->validated('steps')
        );

        return response()->json($result);
    }
}
