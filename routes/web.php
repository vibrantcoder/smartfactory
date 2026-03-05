<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\Iot\IotDashboardController;
use App\Http\Controllers\Admin\Downtime\DowntimeController as AdminDowntimeController;
use App\Http\Controllers\Admin\Employee\EmployeeController as AdminEmployeeController;
use App\Http\Controllers\Admin\Machine\MachineController as AdminMachineController;
use App\Http\Controllers\Admin\Part\PartRoutingController;
use App\Http\Controllers\Admin\ProcessMaster\ProcessMasterController as AdminProcessMasterController;
use App\Http\Controllers\Admin\Production\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\Production\ProductionPlanController as AdminProductionPlanController;
use App\Http\Controllers\Admin\Production\ShiftController as AdminShiftController;
use App\Http\Controllers\Admin\Role\RoleController;
use App\Http\Controllers\Admin\User\UserController;
use App\Http\Controllers\Employee\Auth\LoginController as EmployeeLoginController;
use App\Http\Controllers\Employee\Dashboard\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\Production\JobsController as EmployeeJobsController;
use App\Domain\Production\Models\Part;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('admin.login');
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth:web')
    ->name('admin.logout');

Route::get('/', fn () => redirect()->route('admin.dashboard'));

// Protected admin routes
Route::middleware(['auth:web', 'factory.scope', 'admin.role'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Users
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',    [UserController::class, 'index'])->name('index');
            Route::post('/',   [UserController::class, 'store'])->name('store');
            Route::get('/{user}',  [UserController::class, 'show'])->name('show');
            Route::put('/{user}',  [UserController::class, 'update'])->name('update');
            Route::post('/{user}/assign-role',   [UserController::class, 'assignRole'])->name('assign-role');
            Route::delete('/{user}/revoke-role', [UserController::class, 'revokeRole'])->name('revoke-role');
        });

        // Roles
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/',              [RoleController::class, 'index'])->name('index');
            Route::post('/',             [RoleController::class, 'store'])->name('store');
            Route::get('/{role}',        [RoleController::class, 'show'])->name('show');
            Route::get('/{role}/matrix', [RoleController::class, 'matrix'])->name('matrix');
            Route::post('/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('permissions');
            Route::delete('/{role}',     [RoleController::class, 'destroy'])->name('destroy');
        });

        // Customers
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [AdminCustomerController::class, 'index'])->name('index');
        });

        // Machines
        Route::prefix('machines')->name('machines.')->group(function () {
            Route::get('/', [AdminMachineController::class, 'index'])->name('index');
        });

        // Parts
        Route::prefix('parts')->name('parts.')->group(function () {
            Route::get('/', [PartRoutingController::class, 'index'])->name('index');
            Route::get('/{part}/routing', [PartRoutingController::class, 'edit'])->name('routing.edit');
            Route::get('/{part}', fn (Part $part) => redirect()->route('admin.parts.index'))->name('show');
        });

        // Production Planning Calendar
        Route::prefix('production')->name('production.')->group(function () {
            Route::get('/plans', [AdminProductionPlanController::class, 'index'])->name('plans.index');
        });

        // Shifts
        Route::prefix('shifts')->name('shifts.')->group(function () {
            Route::get('/',           [AdminShiftController::class, 'index'])->name('index');
            Route::post('/',          [AdminShiftController::class, 'store'])->name('store');
            Route::put('/{shift}',    [AdminShiftController::class, 'update'])->name('update');
            Route::delete('/{shift}', [AdminShiftController::class, 'destroy'])->name('destroy');
        });

        // Process Masters
        Route::prefix('process-masters')->name('process-masters.')->group(function () {
            Route::get('/', [AdminProcessMasterController::class, 'index'])->name('index');
        });

        // Downtime Management
        Route::prefix('downtimes')->name('downtimes.')->group(function () {
            Route::get('/', [AdminDowntimeController::class, 'index'])->name('index');
        });

        // IoT Dashboard
        Route::prefix('iot')->name('iot.')->group(function () {
            Route::get('/', [IotDashboardController::class, 'index'])->name('index');
            Route::get('/machines/{machine}/export', [IotDashboardController::class, 'export'])->name('export');
        });

        // Employee Permissions
        Route::prefix('employees')->name('employees.')->group(function () {
            Route::get('/', [AdminEmployeeController::class, 'index'])->name('index');
        });

        // User permission management (used by employee permission page)
        Route::get('/users/{user}/permissions',        [UserController::class, 'permissions'])->name('users.permissions');
        Route::post('/users/{user}/sync-permissions',  [UserController::class, 'syncPermissions'])->name('users.sync-permissions');

        // Machine assignment for operators
        Route::post('/users/{user}/assign-machine', [UserController::class, 'assignMachine'])->name('users.assign-machine');
    });

// ── Employee Portal ────────────────────────────────────────────────────────
Route::prefix('employee')->name('employee.')->group(function () {

    // Public auth
    Route::get('/login',  [EmployeeLoginController::class, 'showForm'])->name('login');
    Route::post('/login', [EmployeeLoginController::class, 'login'])->name('do-login');
    Route::post('/logout', [EmployeeLoginController::class, 'logout'])
        ->middleware('auth:web')
        ->name('logout');

    // Protected employee routes
    Route::middleware(['auth:web', 'factory.scope', 'employee.role'])->group(function () {

        // No-machine info page (no machine check here)
        Route::get('/no-machine', [EmployeeDashboardController::class, 'noMachine'])->name('no-machine');

        // Routes that require a machine assignment
        Route::middleware('employee.machine')->group(function () {
            Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
            Route::get('/jobs',      [EmployeeJobsController::class, 'index'])->name('jobs.index');
        });
    });
});
