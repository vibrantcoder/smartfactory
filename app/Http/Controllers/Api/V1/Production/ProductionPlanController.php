<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Http\Controllers\Controller;
use App\Domain\Production\Models\ProductionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = ProductionPlan::query()
            ->with(['machine', 'part', 'shift'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id'  => 'required|exists:machines,id',
            'part_id'     => 'required|exists:parts,id',
            'shift_id'    => 'required|exists:shifts,id',
            'planned_date'=> 'required|date',
            'planned_qty' => 'required|integer|min:1',
            'notes'       => 'nullable|string',
        ]);

        $data['factory_id'] = $request->user()->factory_id;

        $plan = ProductionPlan::create($data);

        return response()->json($plan->load(['machine', 'part', 'shift']), 201);
    }

    public function show(ProductionPlan $productionPlan): JsonResponse
    {
        return response()->json($productionPlan->load(['machine', 'part', 'shift', 'actuals']));
    }

    public function update(Request $request, ProductionPlan $productionPlan): JsonResponse
    {
        $data = $request->validate([
            'planned_date'=> 'sometimes|date',
            'planned_qty' => 'sometimes|integer|min:1',
            'status'      => 'sometimes|in:draft,confirmed,in_progress,completed,cancelled',
            'notes'       => 'nullable|string',
        ]);

        $productionPlan->update($data);

        return response()->json($productionPlan->fresh(['machine', 'part', 'shift']));
    }

    public function destroy(ProductionPlan $productionPlan): JsonResponse
    {
        $productionPlan->delete();

        return response()->json(null, 204);
    }
}
