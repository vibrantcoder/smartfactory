<?php

declare(strict_types=1);

namespace App\Http\Middleware\Employee;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureHasMachineAssigned
 *
 * Redirects operator to a "no machine assigned" page if machine_id is null.
 * Applied to routes that require a machine context (dashboard, jobs).
 */
class EnsureHasMachineAssigned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user !== null && $user->machine_id === null) {
            return redirect()->route('employee.no-machine');
        }

        return $next($request);
    }
}
