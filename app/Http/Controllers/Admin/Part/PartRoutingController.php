<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Part;

use App\Domain\Production\Models\Part;
use App\Domain\Production\Services\ProcessMasterService;
use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PartRoutingController
 *
 * Serves the drag-and-drop routing builder admin page.
 *
 * ROUTE (routes/admin.php):
 *   Route::middleware(['role:factory-admin|super-admin'])
 *        ->get('/parts/{part}/routing', [PartRoutingController::class, 'edit'])
 *        ->name('parts.routing.edit');
 *
 * Data strategy:
 *   - Pre-load $part with processes.processMaster to avoid AJAX on first render
 *   - Pre-load $palette (all active ProcessMasters) — JSON-embedded in blade
 *   - This gives instant paint; no loading skeleton on first open
 *   - The Alpine component uses this pre-loaded data and can refresh via AJAX
 */
class PartRoutingController extends Controller
{
    use ResolvesFactory;

    public function __construct(
        private readonly ProcessMasterService $processMasterService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Part::class);

        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] = $this->resolveFactories($user);

        return view('admin.parts.index', [
            'apiToken'  => session('api_token'),
            'factoryId' => $factoryId,
            'factories' => $factories,
        ]);
    }

    public function edit(Request $request, Part $part): View
    {
        $this->authorize('update', $part);

        // Load full routing with process master details for initial render
        $part->loadMissing('processes.processMaster');

        // Palette: all active process masters for the routing builder
        $palette = $this->processMasterService->palette();

        // Shape $initialSteps for the Alpine component
        // Maps DB structure → camelCase shape expected by routing-builder.js
        $initialSteps = $part->processes->map(fn($p) => [
            'id'                   => $p->id,
            'process_master_id'    => $p->process_master_id,
            'process_master_name'  => $p->processMaster?->name ?? '',
            'process_master_code'  => $p->processMaster?->code ?? '',
            'machine_type_default' => $p->processMaster?->machine_type_default,
            'default_cycle_time'   => null,
            'standard_cycle_time'  => $p->standard_cycle_time ? (float) $p->standard_cycle_time : null,
            'setup_time'           => $p->setup_time ? (float) $p->setup_time : null,
            'load_unload_time'     => $p->load_unload_time ? (float) $p->load_unload_time : null,
            'process_type'         => $p->process_type ?? 'inhouse',
            'machine_type_required'=> $p->machine_type_required,
            'notes'                => $p->notes,
            'sequence_order'       => $p->sequence_order,
        ])->values()->all();

        // Shape $paletteData for the Alpine palette sidebar
        $paletteData = $palette->map(fn($pm) => [
            'id'                   => $pm->id,
            'name'                 => $pm->name,
            'code'                 => $pm->code,
            'machine_type_default' => $pm->machine_type_default,
            'description'          => $pm->description,
        ])->values()->all();

        // Prefer Sanctum bearer token; fall back to session token (web admin login)
        $apiToken = $request->user()?->currentAccessToken()?->plainTextToken
            ?? session('api_token');

        return view('admin.parts.routing', compact(
            'part',
            'initialSteps',
            'paletteData',
            'apiToken',
        ));
    }
}
