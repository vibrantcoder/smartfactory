<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Http\Controllers\Controller;
use App\Domain\Production\Models\ProductionActual;
use App\Domain\Production\Models\ProductionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionActualController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actuals = ProductionActual::query()
            ->with(['productionPlan', 'machine'])
            ->when($request->plan_id, fn($q) => $q->where('production_plan_id', $request->plan_id))
            ->latest('recorded_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($actuals);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'production_plan_id' => 'required|exists:production_plans,id',
            'actual_qty'         => 'required|integer|min:0',
            'defect_qty'         => 'required|integer|min:0',
            'recorded_at'        => 'nullable|date',
            'notes'              => 'nullable|string',
        ]);

        // Auto-derive machine_id, factory_id from the plan
        $plan = ProductionPlan::findOrFail($data['production_plan_id']);
        $data['machine_id']  = $plan->machine_id;
        $data['factory_id']  = $plan->factory_id ?? $request->user()->factory_id;
        $data['recorded_by'] = $request->user()->name ?? $request->user()->email;
        $data['recorded_at'] = $data['recorded_at'] ?? now();

        $actual = ProductionActual::create($data);

        return response()->json($actual->fresh(), 201);
    }

    public function show(ProductionActual $productionActual): JsonResponse
    {
        return response()->json($productionActual->load(['productionPlan', 'machine']));
    }

    public function update(Request $request, ProductionActual $productionActual): JsonResponse
    {
        $data = $request->validate([
            'actual_qty'  => 'sometimes|integer|min:0',
            'defect_qty'  => 'sometimes|integer|min:0',
            'recorded_at' => 'sometimes|date',
            'notes'       => 'nullable|string',
        ]);

        $productionActual->update($data);

        return response()->json($productionActual->fresh());
    }

    public function destroy(ProductionActual $productionActual): JsonResponse
    {
        $productionActual->delete();

        return response()->json(null, 204);
    }
}
