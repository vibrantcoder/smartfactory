<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Production\Models\WorkOrder;
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
