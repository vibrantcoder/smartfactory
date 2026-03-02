<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Iot;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\IotLog;
use App\Domain\Machine\Models\Machine;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * IotDashboardController
 *
 * Serves the Industry 4.0 real-time IoT dashboard (admin web).
 * All real-time data is fetched client-side via the /api/v1/iot/* endpoints.
 */
class IotDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user      = $request->user();
        $factories = $user->factory_id === null
            ? Factory::where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.iot.dashboard', [
            'apiToken'  => session('api_token'),
            'factoryId' => $user->factory_id,
            'factories' => $factories,
        ]);
    }

    /**
     * Web CSV download — uses session auth so browsers can download directly.
     * GET /admin/iot/machines/{machine}/export?hours=24
     */
    public function export(Request $request, Machine $machine): StreamedResponse
    {
        $this->authorize('view', $machine);

        $hours    = (int) $request->query('hours', 24);
        $hours    = max(1, min(168, $hours));
        $since    = now()->subHours($hours);
        $filename = 'iot-' . $machine->code . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($machine, $since) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'logged_at', 'alarm_code', 'auto_mode', 'cycle_state',
                'part_count', 'part_reject', 'slave_id', 'slave_name',
            ]);

            IotLog::query()
                ->where('machine_id', $machine->id)
                ->where('logged_at', '>=', $since)
                ->orderBy('logged_at')
                ->chunk(1000, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->logged_at->toDateTimeString(),
                            $log->alarm_code,
                            $log->auto_mode ? 1 : 0,
                            $log->cycle_state ? 1 : 0,
                            $log->part_count,
                            $log->part_reject,
                            $log->slave_id,
                            $log->slave_name,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
