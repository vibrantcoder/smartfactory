<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Machine;

use App\Domain\Machine\DataTransferObjects\MachineData;
use App\Domain\Machine\Models\Machine;
use App\Domain\Machine\Services\MachineLogIngestionService;
use App\Domain\Shared\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Machine\CreateMachineRequest;
use App\Http\Requests\Api\Machine\UpdateMachineRequest;
use App\Http\Resources\Machine\MachineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * MachineController
 *
 * Demonstrates the full RBAC authorization pattern:
 *   1. Middleware: auth:sanctum, factory.scope, factory.member
 *   2. Policy: $this->authorize() via MachinePolicy
 *   3. Permission check via Spatie in policy methods
 *   4. Factory scoping via BelongsToFactory trait on Machine model
 *
 * MIDDLEWARE STACK (routes/api_v1.php):
 *   Route::middleware([
 *       'auth:sanctum',
 *       'factory.scope',         ← SetFactoryPermissionScope
 *       'factory.member',        ← EnsureFactoryMembership
 *       'permission:view-any.machine',  ← Spatie permission middleware (index only)
 *   ])->group(function () {
 *       Route::apiResource('machines', MachineController::class);
 *   });
 */
class MachineController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // index
    // ─────────────────────────────────────────────────────────

    /**
     * List machines in the authenticated user's factory.
     *
     * AUTHORIZATION LAYERS:
     *   Layer 1: Spatie middleware 'permission:view-any.machine' (on route)
     *   Layer 2: $this->authorize() → MachinePolicy::viewAny() (redundant but explicit)
     *   Layer 3: Factory scope applied by HasFactoryScope global scope on Machine model
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Policy check (redundant with route middleware — defence in depth)
        $this->authorize('viewAny', Machine::class);

        $machines = Machine::query()
            // HasFactoryScope global scope automatically applies:
            // WHERE factory_id = auth()->user()->factory_id
            ->with(['factory:id,name'])
            ->when($request->filled('search'), fn($q) => $q->search($request->input('search')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'),   fn($q) => $q->where('type', $request->type))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return MachineResource::collection($machines);
    }

    // ─────────────────────────────────────────────────────────
    // show
    // ─────────────────────────────────────────────────────────

    /**
     * Show a specific machine.
     *
     * AUTHORIZATION:
     *   MachinePolicy::view() → checks permission AND same-factory guard.
     *   A user from Factory A cannot view Factory B's machine even if they
     *   know the ID, because the policy checks belongsToSameFactory().
     */
    public function show(Machine $machine): MachineResource
    {
        $this->authorize('view', $machine);

        return new MachineResource($machine->load(['factory:id,name']));
    }

    // ─────────────────────────────────────────────────────────
    // store
    // ─────────────────────────────────────────────────────────

    /**
     * Create a new machine in the authenticated user's factory.
     *
     * AUTHORIZATION: create.machine permission (factory-admin+).
     * The factory_id is always taken from the authenticated user — never from request body.
     * This prevents a malicious user from creating a machine in another factory.
     */
    public function store(CreateMachineRequest $request): JsonResponse
    {
        $this->authorize('create', Machine::class);

        $data = MachineData::fromRequest($request);

        // factory_id is forced from auth user — NOT from request input
        $machine = Machine::create([
            ...$data->toArray(),
            'factory_id'   => $request->user()->factory_id,
            'device_token' => hash('sha256', uniqid('mch_', true) . random_bytes(16)),
        ]);

        return (new MachineResource($machine))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // ─────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────

    /**
     * Update a machine.
     *
     * AUTHORIZATION: MachinePolicy::update() checks:
     *   1. belongsToSameFactory() — prevents IDOR
     *   2. update.machine permission — role must have it
     */
    public function update(UpdateMachineRequest $request, Machine $machine): MachineResource
    {
        $this->authorize('update', $machine);

        $machine->update($request->validated());

        return new MachineResource($machine->fresh());
    }

    // ─────────────────────────────────────────────────────────
    // destroy
    // ─────────────────────────────────────────────────────────

    /**
     * Delete a machine.
     *
     * AUTHORIZATION: MachinePolicy::delete() checks:
     *   1. belongsToSameFactory()
     *   2. delete.machine permission
     *   3. hasMinimumRole(FACTORY_ADMIN) — double-locks deletion to admins only
     */
    public function destroy(Machine $machine): JsonResponse
    {
        $this->authorize('delete', $machine);

        $machine->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    // ─────────────────────────────────────────────────────────
    // viewLogs — custom policy method example
    // ─────────────────────────────────────────────────────────

    /**
     * View machine logs for a specific machine.
     *
     * AUTHORIZATION: MachinePolicy::viewLogs() — custom ability.
     * Demonstrates that policies can have methods beyond standard CRUD.
     *
     * NOTE: This is a read-heavy endpoint. Machine log data comes from
     * machine_logs_hourly (not raw machine_logs) for performance.
     */
    public function logs(Request $request, Machine $machine): JsonResponse
    {
        // Custom policy ability — not a standard CRUD method
        $this->authorize('viewLogs', $machine);

        // Dashboard reads from hourly aggregation, never raw logs
        $logs = \App\Domain\Analytics\Models\MachineLogHourly::query()
            ->where('machine_id', $machine->id)
            ->whereBetween('hour_start', [
                now()->subHours(24)->startOfHour(),
                now()->endOfHour(),
            ])
            ->orderBy('hour_start')
            ->get();

        return response()->json(['data' => $logs]);
    }
}
