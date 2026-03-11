<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Production;

use App\Domain\Production\Models\Shift;
use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): View
    {
        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] =
            $this->resolveFactories($user, $request->integer('factory_id') ?: null);

        $shifts = $factoryId
            ? Shift::forFactory($factoryId)->orderBy('start_time')->get()
            : collect();

        return view('admin.shifts.index', [
            'shifts'    => $shifts,
            'factories' => $factories,
            'factoryId' => $factoryId,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user      = $request->user();
        $factoryId = $user->factory_id ?? $request->integer('factory_id');

        $request->validate([
            'name'        => 'required|string|max:50',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end'   => 'nullable|date_format:H:i|required_with:break_start',
            'is_active'   => 'boolean',
            'factory_id'  => $user->factory_id === null ? 'required|integer|exists:factories,id' : 'nullable',
        ]);

        [$crossesMidnight, $durationMin] = $this->computeMeta(
            $request->input('start_time'),
            $request->input('end_time')
        );

        $breakStart = $request->input('break_start') ?: null;
        $breakEnd   = $request->input('break_end')   ?: null;
        $breakMin   = $this->computeBreakMin($breakStart, $breakEnd, $durationMin);

        $shift = Shift::create([
            'factory_id'       => $factoryId,
            'name'             => $request->input('name'),
            'start_time'       => $request->input('start_time') . ':00',
            'end_time'         => $request->input('end_time') . ':00',
            'duration_min'     => $durationMin,
            'break_start'      => $breakStart ? $breakStart . ':00' : null,
            'break_end'        => $breakEnd   ? $breakEnd   . ':00' : null,
            'break_min'        => $breakMin,
            'crosses_midnight' => $crossesMidnight,
            'is_active'        => $request->boolean('is_active', true),
        ]);

        return response()->json($shift, 201);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:50',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end'   => 'nullable|date_format:H:i|required_with:break_start',
            'is_active'   => 'boolean',
        ]);

        [$crossesMidnight, $durationMin] = $this->computeMeta(
            $request->input('start_time'),
            $request->input('end_time')
        );

        $breakStart = $request->input('break_start') ?: null;
        $breakEnd   = $request->input('break_end')   ?: null;
        $breakMin   = $this->computeBreakMin($breakStart, $breakEnd, $durationMin);

        $shift->update([
            'name'             => $request->input('name'),
            'start_time'       => $request->input('start_time') . ':00',
            'end_time'         => $request->input('end_time') . ':00',
            'duration_min'     => $durationMin,
            'break_start'      => $breakStart ? $breakStart . ':00' : null,
            'break_end'        => $breakEnd   ? $breakEnd   . ':00' : null,
            'break_min'        => $breakMin,
            'crosses_midnight' => $crossesMidnight,
            'is_active'        => $request->boolean('is_active', $shift->is_active),
        ]);

        return response()->json($shift);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        if ($shift->productionPlans()->whereIn('status', ['draft', 'scheduled', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Cannot deactivate: this shift has active production plans.',
            ], 409);
        }

        $shift->update(['is_active' => false]);

        return response()->json(['message' => 'Shift deactivated successfully.']);
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Calculate break_min from break_start/break_end times.
     * Break is always within the same calendar day (end > start).
     * Capped to durationMin so it cannot exceed the shift length.
     */
    private function computeBreakMin(?string $breakStart, ?string $breakEnd, int $durationMin): int
    {
        if (!$breakStart || !$breakEnd) return 0;

        [$bsh, $bsm] = array_map('intval', explode(':', $breakStart));
        [$beh, $bem] = array_map('intval', explode(':', $breakEnd));

        $startMin = $bsh * 60 + $bsm;
        $endMin   = $beh * 60 + $bem;

        $breakMin = $endMin > $startMin ? $endMin - $startMin : 0;

        return min($breakMin, $durationMin);
    }

    /**
     * Compute crosses_midnight and duration_min from start/end times.
     *
     * Morning  08:00 → 20:00  → same day,         720 min
     * Night    20:00 → 08:00  → crosses midnight,  720 min
     */
    private function computeMeta(string $start, string $end): array
    {
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));

        $startMin = $sh * 60 + $sm;
        $endMin   = $eh * 60 + $em;

        $crossesMidnight = $endMin <= $startMin;
        $durationMin     = $crossesMidnight
            ? (1440 - $startMin) + $endMin   // (midnight − start) + end
            : $endMin - $startMin;

        return [$crossesMidnight, $durationMin];
    }
}
