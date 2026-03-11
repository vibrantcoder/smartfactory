<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\PartProcess;
use App\Domain\Production\Models\Shift;
use App\Domain\Production\Services\ProductionSchedulingService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MachineLoadController
 *
 * GET /api/v1/machine-load
 *
 * Returns daily machine utilisation data for a date range.
 * Intended for the Machine Load Chart in the production planning dashboard.
 *
 * RESPONSE SHAPE:
 * {
 *   "capacity_min": 660,
 *   "from_date": "2026-03-11",
 *   "to_date":   "2026-03-17",
 *   "machines": [
 *     {
 *       "id": 1, "name": "CNC Lathe A",
 *       "days": {
 *         "2026-03-11": { "planned_min": 330.0, "qty": 82, "plan_count": 1, "pct": 50.0 },
 *         "2026-03-12": { "planned_min": 0.0,   "qty": 0,  "plan_count": 0, "pct": 0.0  }
 *       }
 *     }
 *   ]
 * }
 */
class MachineLoadController extends Controller
{
    public function __construct(
        private readonly ProductionSchedulingService $scheduling,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from_date'  => 'required|date',
            'to_date'    => 'required|date|after_or_equal:from_date',
            'factory_id' => 'nullable|integer|exists:factories,id',
            'shift_id'   => 'nullable|integer|exists:shifts,id',
        ]);

        $factoryId = $request->user()->factory_id ?? $request->integer('factory_id');
        $fromDate  = $request->input('from_date');
        $toDate    = $request->input('to_date');

        // ── Active shifts for this factory ────────────────────
        $shifts = Shift::withoutGlobalScopes()
            ->where('factory_id', $factoryId)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get(['id', 'name', 'duration_min', 'break_min']);

        // Build shift capacity map: shift_id → planned_min
        $shiftCapacity = $shifts->mapWithKeys(fn ($s) => [
            $s->id => max(0, (int) $s->duration_min - (int) ($s->break_min ?? 0)),
        ]);

        // ── Load planned minutes per machine × date × shift ───
        $rows = DB::table('production_plans')
            ->join('machines', 'machines.id', '=', 'production_plans.machine_id')
            ->join('parts', 'parts.id', '=', 'production_plans.part_id')
            ->join('shifts', 'shifts.id', '=', 'production_plans.shift_id')
            ->leftJoin('part_processes', 'part_processes.id', '=', 'production_plans.part_process_id')
            ->leftJoin('process_masters', 'process_masters.id', '=', 'part_processes.process_master_id')
            ->where('production_plans.factory_id', $factoryId)
            ->whereBetween('production_plans.planned_date', [$fromDate, $toDate])
            ->whereNotIn('production_plans.status', ['cancelled'])
            ->groupBy(
                'production_plans.machine_id', 'machines.name',
                'production_plans.planned_date',
                'production_plans.shift_id', 'shifts.name'
            )
            ->selectRaw("
                production_plans.machine_id,
                machines.name AS machine_name,
                DATE_FORMAT(production_plans.planned_date, '%Y-%m-%d') AS plan_date,
                production_plans.shift_id,
                shifts.name AS shift_name,
                SUM(production_plans.planned_qty) AS total_qty,
                COALESCE(SUM(
                    production_plans.planned_qty *
                    COALESCE(
                        part_processes.standard_cycle_time,
                        process_masters.standard_time,
                        NULLIF(parts.total_cycle_time, 0),
                        parts.cycle_time_std / 60,
                        0
                    )
                ), 0) AS total_minutes
            ")
            ->get();

        // ── Index: machine_id → plan_date → shift_id ──────────
        $byMachine = $rows->groupBy('machine_id');

        // ── All active machines in factory ─────────────────────
        $machines = Machine::withoutGlobalScopes()
            ->where('factory_id', $factoryId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        // ── Generate date spine ────────────────────────────────
        $dates  = [];
        $cursor = Carbon::parse($fromDate);
        $endDate = Carbon::parse($toDate);
        while ($cursor->lte($endDate)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // ── Build response ─────────────────────────────────────
        $result = $machines->map(function ($machine) use ($byMachine, $dates, $shifts, $shiftCapacity) {
            // Group rows for this machine by date → shift
            $machineDates = $byMachine->get($machine->id, collect())
                ->groupBy('plan_date');

            $days = [];
            $totalPctSum  = 0;
            $activeDayCnt = 0;

            foreach ($dates as $date) {
                $shiftRows = $machineDates->get($date, collect())->keyBy('shift_id');

                $byShift   = [];
                $dayMinutes = 0.0;
                $dayQty    = 0;

                foreach ($shifts as $shift) {
                    $row     = $shiftRows->get($shift->id);
                    $minutes = $row ? round((float) $row->total_minutes, 2) : 0.0;
                    $cap     = $shiftCapacity[$shift->id] ?? 0;
                    $pct     = $cap > 0 ? round($minutes / $cap * 100, 1) : 0.0;

                    $byShift[$shift->id] = [
                        'shift_name'  => $shift->name,
                        'planned_min' => $minutes,
                        'capacity_min'=> $cap,
                        'qty'         => $row ? (int) $row->total_qty : 0,
                        'pct'         => $pct,
                    ];
                    $dayMinutes += $minutes;
                    $dayQty    += $row ? (int) $row->total_qty : 0;
                }

                $totalCap = $shiftCapacity->sum();
                $totalPct = $totalCap > 0 ? round($dayMinutes / $totalCap * 100, 1) : 0.0;

                if ($totalPct > 0) {
                    $totalPctSum += $totalPct;
                    $activeDayCnt++;
                }

                $days[$date] = [
                    'total_pct'   => $totalPct,
                    'total_qty'   => $dayQty,
                    'planned_min' => round($dayMinutes, 2),
                    'by_shift'    => $byShift,
                ];
            }

            return [
                'id'       => $machine->id,
                'name'     => $machine->name,
                'week_avg' => $activeDayCnt > 0 ? round($totalPctSum / $activeDayCnt, 1) : 0.0,
                'days'     => $days,
            ];
        });

        return response()->json([
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'shifts'    => $shifts->map(fn ($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'capacity_min' => $shiftCapacity[$s->id],
            ])->values(),
            'machines'  => $result->values(),
        ]);
    }

    /**
     * GET /api/v1/machine-availability
     *
     * Check if a machine has capacity on a given date (scoped to a shift).
     * If full, finds the next date with free capacity (up to 60 days ahead).
     *
     * Params: machine_id, shift_id, start_date, part_process_id (optional)
     *
     * Response:
     * {
     *   "date": "2026-03-11",
     *   "machine_name": "CNC Lathe A",
     *   "is_full": true,
     *   "used_min": 660, "capacity_min": 660, "free_min": 0, "free_qty": 0,
     *   "next_available_date": "2026-03-13",
     *   "next_free_qty": 82
     * }
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id'      => 'required|integer|exists:machines,id',
            'shift_id'        => 'required|integer|exists:shifts,id',
            'start_date'      => 'required|date',
            'part_process_id' => 'nullable|integer|exists:part_processes,id',
        ]);

        $shift       = Shift::findOrFail($data['shift_id']);
        $capacityMin = (float) $shift->planned_min;
        $machine     = Machine::withoutGlobalScopes()->findOrFail($data['machine_id']);

        // Resolve cycle time for free_qty calculation
        $cycleTimeMin = 0.0;
        if (!empty($data['part_process_id'])) {
            $pp = PartProcess::with('processMaster')->find($data['part_process_id']);
            if ($pp) {
                $cycleTimeMin = $pp->effectiveCycleTime();
            }
        }

        $minSlot   = $cycleTimeMin > 0 ? $cycleTimeMin : 1.0; // 1 min fallback
        $date      = Carbon::parse($data['start_date']);
        $machineId = (int) $data['machine_id'];

        // ── Check requested date ────────────────────────────────
        $usedMin = $this->scheduling->getMachineUsedMinutes($machineId, $date->toDateString());
        $freeMin = max(0.0, $capacityMin - $usedMin);
        $freeQty = $cycleTimeMin > 0 ? (int) floor($freeMin / $cycleTimeMin) : null;
        $isFull  = $freeMin < $minSlot;

        $response = [
            'date'          => $date->toDateString(),
            'machine_name'  => $machine->name,
            'is_full'       => $isFull,
            'used_min'      => round($usedMin, 1),
            'capacity_min'  => round($capacityMin, 1),
            'free_min'      => round($freeMin, 1),
            'free_qty'      => $freeQty,
            'next_available_date' => null,
            'next_free_qty'       => null,
        ];

        // ── If full, find next available date (up to 60 days) ──
        if ($isFull) {
            $next = $date->copy()->addDay();
            for ($i = 0; $i < 60; $i++, $next->addDay()) {
                $nextUsed = $this->scheduling->getMachineUsedMinutes($machineId, $next->toDateString());
                $nextFree = max(0.0, $capacityMin - $nextUsed);
                if ($nextFree >= $minSlot) {
                    $response['next_available_date'] = $next->toDateString();
                    $response['next_free_qty'] = $cycleTimeMin > 0
                        ? (int) floor($nextFree / $cycleTimeMin)
                        : null;
                    break;
                }
            }
        }

        return response()->json($response);
    }

    /**
     * Resolve daily capacity in minutes.
     * If a specific shift is given → use its planned_min.
     * Otherwise → sum all active shifts for the factory.
     */
    private function resolveCapacity(int $factoryId, int $shiftId = 0): int
    {
        if ($shiftId > 0) {
            $shift = Shift::find($shiftId);
            return $shift ? (int) $shift->planned_min : 480;
        }

        $total = Shift::withoutGlobalScopes()
            ->where('factory_id', $factoryId)
            ->where('is_active', true)
            ->sum(DB::raw('duration_min - COALESCE(break_min, 0)'));

        return $total > 0 ? (int) $total : 480;
    }
}
