<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Factory\Models\Factory;
use App\Domain\Production\Models\WorkOrder;
use App\Domain\Production\Services\ProductionSchedulingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkOrder\CreateWorkOrderRequest;
use App\Http\Requests\Admin\WorkOrder\UpdateWorkOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WorkOrderController (API v1)
 *
 * ROUTES:
 *   GET    /api/v1/work-orders           → index
 *   POST   /api/v1/work-orders           → store
 *   GET    /api/v1/work-orders/{wo}      → show
 *   PUT    /api/v1/work-orders/{wo}      → update
 *   DELETE /api/v1/work-orders/{wo}      → destroy
 */
class WorkOrderController extends Controller
{
    public function __construct(
        private readonly ProductionSchedulingService $schedulingService,
    ) {}

    // ── index ─────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrder::class);

        $query = WorkOrder::query()->with(['customer:id,name,code', 'part:id,name,part_number,unit']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('part_id')) {
            $query->where('part_id', $request->integer('part_id'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('expected_delivery_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('expected_delivery_date', '<=', $request->to_date);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('wo_number', 'like', "%{$term}%")
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$term}%"))
                  ->orWhereHas('part', fn($p) => $p->where('part_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
            });
        }

        $orders = $query
            ->orderByRaw("FIELD(priority, 'urgent','high','medium','low')")
            ->orderBy('expected_delivery_date')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            ...$orders->toArray(),
            'data' => $orders->getCollection()->map(fn($wo) => $this->formatWo($wo))->values(),
        ]);
    }

    // ── store ─────────────────────────────────────────────────

    public function store(CreateWorkOrderRequest $request): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);

        $factoryId = $request->user()->factory_id
            ?? $request->integer('factory_id');

        $wo = WorkOrder::create(array_merge($request->validated(), [
            'factory_id' => $factoryId,
            'wo_number'  => WorkOrder::generateWoNumber($factoryId),
            'created_by' => $request->user()->id,
        ]));

        $wo->load(['customer:id,name,code', 'part:id,name,part_number,unit']);

        return response()->json([
            'message' => "Work Order [{$wo->wo_number}] created.",
            'data'    => $this->formatWo($wo),
        ], Response::HTTP_CREATED);
    }

    // ── show ──────────────────────────────────────────────────

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        $workOrder->load(['customer:id,name,code', 'part:id,name,part_number,unit,cycle_time_std']);

        return response()->json(['data' => $this->formatWo($workOrder)]);
    }

    // ── update ────────────────────────────────────────────────

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);

        if ($workOrder->isImmutable()) {
            return response()->json(
                ['message' => "Work Order [{$workOrder->wo_number}] is {$workOrder->status} and cannot be modified."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $data = $request->validated();

        // Stamp transition timestamps
        if (isset($data['status']) && $data['status'] !== $workOrder->status) {
            match ($data['status']) {
                'confirmed'   => $data['confirmed_at'] = now(),
                'released'    => $data['released_at']  = now(),
                'completed'   => $data['completed_at'] = now(),
                default       => null,
            };
        }

        $workOrder->update($data);
        $workOrder->refresh()->load(['customer:id,name,code', 'part:id,name,part_number,unit']);

        return response()->json([
            'message' => "Work Order [{$workOrder->wo_number}] updated.",
            'data'    => $this->formatWo($workOrder),
        ]);
    }

    // ── destroy ───────────────────────────────────────────────

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('delete', $workOrder);

        if (! $workOrder->isDraft()) {
            return response()->json(
                ['message' => 'Only draft work orders can be deleted. Cancel it first.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $workOrder->delete();

        return response()->json(['message' => "Work Order [{$workOrder->wo_number}] deleted."]);
    }

    // ── Helpers ───────────────────────────────────────────────

    // ── scheduledQty ──────────────────────────────────────────

    /**
     * GET /api/v1/work-orders/{wo}/scheduled-qty
     *
     * Returns how many units are already scheduled for this WO,
     * broken down by part_process_id. Used by the Schedule modal to
     * show remaining qty and per-process history.
     */
    public function scheduledQty(WorkOrder $wo): JsonResponse
    {
        // Per process + date breakdown (one row per process × date)
        $rows = \Illuminate\Support\Facades\DB::table('production_plans')
            ->leftJoin('part_processes', 'part_processes.id', '=', 'production_plans.part_process_id')
            ->leftJoin('process_masters', 'process_masters.id', '=', 'part_processes.process_master_id')
            ->where('production_plans.work_order_id', $wo->id)
            ->whereNotIn('production_plans.status', ['cancelled'])
            ->groupBy(
                'production_plans.part_process_id',
                'part_processes.sequence_order',
                'process_masters.name',
                'production_plans.planned_date'
            )
            ->selectRaw("
                production_plans.part_process_id,
                part_processes.sequence_order,
                process_masters.name AS process_name,
                DATE_FORMAT(production_plans.planned_date, '%Y-%m-%d') AS planned_date,
                SUM(production_plans.planned_qty) AS day_qty
            ")
            ->orderBy('part_processes.sequence_order')
            ->orderBy('production_plans.planned_date')
            ->get();

        // Group into processes with nested date rows
        $byProcess = $rows->groupBy('part_process_id')->map(function ($dateRows) use ($wo) {
            $first = $dateRows->first();
            return [
                'part_process_id' => $first->part_process_id,
                'sequence_order'  => $first->sequence_order,
                'process_name'    => $first->process_name ?? '(no process)',
                'scheduled_qty'   => (int) $dateRows->sum('day_qty'),
                'dates'           => $dateRows->map(fn($r) => [
                    'planned_date' => $r->planned_date,
                    'planned_qty'  => (int) $r->day_qty,
                ])->values(),
            ];
        })->sortBy('sequence_order')->values();

        $totalScheduled = (int) $byProcess->sum('scheduled_qty');

        return response()->json([
            'total_planned_qty' => (int) $wo->total_planned_qty,
            'total_scheduled'   => $totalScheduled,
            'remaining'         => max(0, (int) $wo->total_planned_qty - $totalScheduled),
            'by_process'        => $byProcess,
        ]);
    }

    // ── schedule ──────────────────────────────────────────────

    /**
     * POST /api/v1/work-orders/{wo}/schedule
     *
     * Auto-schedule a confirmed/released work order across calendar days on a
     * specific machine and shift. Uses ProductionSchedulingService to distribute
     * the planned quantity respecting existing machine load (auto-rescheduling).
     */
    public function schedule(Request $request, WorkOrder $wo): JsonResponse
    {
        $data = $request->validate([
            'machine_id'              => 'required|integer|exists:machines,id',
            'shift_ids'               => 'required|array|min:1',
            'shift_ids.*'             => 'integer|exists:shifts,id',
            'start_date'              => 'required|date',
            'part_process_id'         => 'nullable|integer|exists:part_processes,id',
            'plan_qty'                => 'nullable|integer|min:1',
            'allow_week_off_holiday'  => 'boolean',
        ]);

        if (!in_array($wo->status, ['confirmed', 'released', 'in_progress'], true)) {
            return response()->json([
                'message' => 'Work order must be confirmed or released before scheduling production.',
            ], 422);
        }

        // ── Guard: remaining qty check (process-scoped when process given) ──
        $partProcessId = isset($data['part_process_id']) ? (int) $data['part_process_id'] : null;

        $scheduledQuery = \Illuminate\Support\Facades\DB::table('production_plans')
            ->where('work_order_id', $wo->id)
            ->whereNotIn('status', ['cancelled']);

        if ($partProcessId !== null) {
            $scheduledQuery->where('part_process_id', $partProcessId);
        }

        $alreadyScheduled = (int) $scheduledQuery->sum('planned_qty');
        $remaining        = (int) $wo->total_planned_qty - $alreadyScheduled;

        $scopeLabel = $partProcessId !== null ? "for this process" : "for this work order";

        if ($remaining <= 0) {
            return response()->json([
                'message'          => "All {$wo->total_planned_qty} units are already scheduled {$scopeLabel}. Cancel existing plans to reschedule.",
                'remaining'        => 0,
                'part_process_id'  => $partProcessId,
            ], 422);
        }

        if (isset($data['plan_qty']) && (int) $data['plan_qty'] > $remaining) {
            return response()->json([
                'message'         => "Plan qty ({$data['plan_qty']}) exceeds remaining qty ({$remaining}) {$scopeLabel}. Maximum allowed: {$remaining}.",
                'remaining'       => $remaining,
                'part_process_id' => $partProcessId,
            ], 422);
        }

        $factoryId = $request->user()->factory_id ?? $wo->factory_id;

        // Load factory calendar constraints
        $factory      = Factory::find((int) $factoryId);
        $weekOffDays  = $factory ? array_map('intval', $factory->week_off_days ?? []) : [];
        $holidayDates = $factory
            ? $factory->holidays()->pluck('holiday_date')
                ->map(fn($d) => $d instanceof \Carbon\Carbon ? $d->format('Y-m-d') : (string) $d)
                ->toArray()
            : [];

        try {
            $plans = $this->schedulingService->schedule(
                wo:                   $wo,
                machineId:            (int) $data['machine_id'],
                shiftIds:             array_map('intval', $data['shift_ids']),
                startDate:            $data['start_date'],
                factoryId:            (int) $factoryId,
                partProcessId:        isset($data['part_process_id']) ? (int) $data['part_process_id'] : null,
                planQty:              isset($data['plan_qty']) ? (int) $data['plan_qty'] : null,
                weekOffDays:          $weekOffDays,
                holidayDates:         $holidayDates,
                allowWeekOffHoliday:  (bool) ($data['allow_week_off_holiday'] ?? false),
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $totalQty = array_sum(array_column(
            array_map(fn($p) => ['qty' => $p->planned_qty], $plans),
            'qty'
        ));

        return response()->json([
            'message'    => count($plans) . ' production plan(s) created successfully.',
            'plan_count' => count($plans),
            'total_qty'  => $totalQty,
            'from_date'  => $plans[0]->planned_date?->format('Y-m-d') ?? null,
            'to_date'    => $plans[count($plans) - 1]->planned_date?->format('Y-m-d') ?? null,
            'plans'      => array_map(fn($p) => [
                'id'           => $p->id,
                'planned_date' => $p->planned_date?->format('Y-m-d'),
                'planned_qty'  => $p->planned_qty,
            ], $plans),
        ], 201);
    }

    private function formatWo(WorkOrder $wo): array
    {
        return [
            'id'                     => $wo->id,
            'wo_number'              => $wo->wo_number,
            'factory_id'             => $wo->factory_id,
            'customer_id'            => $wo->customer_id,
            'customer_name'          => $wo->customer?->name,
            'customer_code'          => $wo->customer?->code,
            'part_id'                => $wo->part_id,
            'part_name'              => $wo->part?->name,
            'part_number'            => $wo->part?->part_number,
            'part_unit'              => $wo->part?->unit,
            'order_qty'              => $wo->order_qty,
            'excess_qty'             => $wo->excess_qty,
            'total_planned_qty'      => $wo->total_planned_qty,
            'expected_delivery_date' => $wo->expected_delivery_date?->format('Y-m-d'),
            'planned_start_date'     => $wo->planned_start_date?->format('Y-m-d'),
            'priority'               => $wo->priority,
            'status'                 => $wo->status,
            'notes'                  => $wo->notes,
            'created_by'             => $wo->created_by,
            'confirmed_at'           => $wo->confirmed_at?->toISOString(),
            'released_at'            => $wo->released_at?->toISOString(),
            'completed_at'           => $wo->completed_at?->toISOString(),
            'created_at'             => $wo->created_at?->toISOString(),
            'updated_at'             => $wo->updated_at?->toISOString(),
        ];
    }
}
