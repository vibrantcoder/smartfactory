<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Factory;

use App\Domain\Factory\DataTransferObjects\FactoryData;
use App\Domain\Factory\Models\Factory;
use App\Domain\Factory\Models\FactoryHoliday;
use App\Domain\Factory\Services\FactoryService;
use App\Domain\Shared\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Factory\CreateFactoryRequest;
use App\Http\Requests\Api\Factory\UpdateFactoryRequest;
use App\Http\Resources\Factory\FactoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * FactoryController
 *
 * ROUTE STACK (routes/api_v1.php):
 *   Route::middleware(['auth:sanctum', 'factory.scope', 'role:super-admin'])
 *        ->apiResource('factories', FactoryController::class);
 *
 * DESIGN RULES:
 *   1. No business logic — delegates entirely to FactoryService.
 *   2. No DB queries — never touches Eloquent directly.
 *   3. No raw arrays in responses — always uses API Resources.
 *   4. factory_id is NEVER accepted from request body for mutations.
 */
class FactoryController extends Controller
{
    public function __construct(
        private readonly FactoryService $service,
    ) {}

    // ── index ─────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Factory::class);

        $factories = $this->service->list(
            filters: $request->only(['search', 'status']),
            perPage: $request->integer('per_page', 25),
        );

        return FactoryResource::collection($factories);
    }

    // ── show ──────────────────────────────────────────────────

    public function show(Factory $factory): FactoryResource
    {
        $this->authorize('view', $factory);

        // Load settings for the detail view
        $factory->loadMissing('settings');
        $factory->loadCount(['machines', 'users']);

        return new FactoryResource($factory);
    }

    // ── store ─────────────────────────────────────────────────

    public function store(CreateFactoryRequest $request): JsonResponse
    {
        // Authorization is inside CreateFactoryRequest::authorize()
        // Double-checking via gate for defence-in-depth
        $this->authorize('create', Factory::class);

        try {
            $factory = $this->service->create(
                FactoryData::fromCreateRequest($request)
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return (new FactoryResource($factory->load('settings')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // ── update ────────────────────────────────────────────────

    public function update(UpdateFactoryRequest $request, Factory $factory): FactoryResource
    {
        $this->authorize('update', $factory);

        $updated = $this->service->update(
            $factory,
            FactoryData::fromUpdateRequest($request)
        );

        return new FactoryResource($updated->load('settings'));
    }

    // ── week-off ──────────────────────────────────────────────

    /**
     * PATCH /api/v1/factories/{factory}/week-off
     * Update the week-off day bitmask (array of 0–6 integers).
     */
    public function updateWeekOff(Request $request, Factory $factory): JsonResponse
    {
        $this->authorize('update', $factory);

        $data = $request->validate([
            'week_off_days'   => 'present|array',
            'week_off_days.*' => 'integer|min:0|max:6',
        ]);

        $days = array_values(array_unique(array_map('intval', $data['week_off_days'] ?? [])));
        $factory->update(['week_off_days' => $days]);

        return response()->json([
            'message'       => 'Week-off days updated.',
            'week_off_days' => $factory->fresh()->week_off_days ?? [],
        ]);
    }

    // ── holidays ──────────────────────────────────────────────

    /** GET /api/v1/factories/{factory}/holidays */
    public function holidays(Factory $factory): JsonResponse
    {
        $this->authorize('view', $factory);

        return response()->json(
            $factory->holidays()->get(['id', 'holiday_date', 'name'])
                ->map(fn ($h) => [
                    'id'           => $h->id,
                    'holiday_date' => $h->holiday_date instanceof \Carbon\Carbon
                        ? $h->holiday_date->format('Y-m-d')
                        : (string) $h->holiday_date,
                    'name'         => $h->name,
                ])
        );
    }

    /** POST /api/v1/factories/{factory}/holidays */
    public function addHoliday(Request $request, Factory $factory): JsonResponse
    {
        $this->authorize('update', $factory);

        $data = $request->validate([
            'holiday_date' => 'required|date_format:Y-m-d',
            'name'         => 'required|string|max:100',
        ]);

        $holiday = FactoryHoliday::firstOrCreate(
            ['factory_id' => $factory->id, 'holiday_date' => $data['holiday_date']],
            ['name' => $data['name']]
        );

        if (! $holiday->wasRecentlyCreated) {
            $holiday->update(['name' => $data['name']]);
        }

        return response()->json([
            'id'           => $holiday->id,
            'holiday_date' => $data['holiday_date'],
            'name'         => $holiday->name,
        ], Response::HTTP_CREATED);
    }

    /** DELETE /api/v1/factories/{factory}/holidays/{holiday} */
    public function removeHoliday(Factory $factory, FactoryHoliday $holiday): JsonResponse
    {
        $this->authorize('update', $factory);

        if ($holiday->factory_id !== $factory->id) {
            abort(404);
        }

        $holiday->delete();

        return response()->json(['message' => 'Holiday removed.']);
    }

    // ── destroy ───────────────────────────────────────────────

    /**
     * Deactivates the factory (no hard delete — audit trail preservation).
     */
    public function destroy(Factory $factory): JsonResponse
    {
        $this->authorize('delete', $factory);

        try {
            $this->service->deactivate($factory);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json(
            ['message' => "Factory [{$factory->code}] has been deactivated."],
            Response::HTTP_OK
        );
    }
}
