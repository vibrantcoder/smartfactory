<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\Iot\IotDashboardController;
use App\Http\Controllers\Admin\Part\PartRoutingController;
use App\Http\Controllers\Admin\Production\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\Production\ProductionPlanController as AdminProductionPlanController;
use App\Http\Controllers\Admin\Role\RoleController;
use App\Http\Controllers\Admin\User\UserController;
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
Route::middleware(['auth:web', 'factory.scope'])
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
            Route::get('/{role}',        [RoleController::class, 'show'])->name('show');
            Route::get('/{role}/matrix', [RoleController::class, 'matrix'])->name('matrix');
            Route::post('/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('permissions');
        });

        // Customers
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [AdminCustomerController::class, 'index'])->name('index');
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

        // IoT Dashboard
        Route::prefix('iot')->name('iot.')->group(function () {
            Route::get('/', [IotDashboardController::class, 'index'])->name('index');
            Route::get('/machines/{machine}/export', [IotDashboardController::class, 'export'])->name('export');
        });
    });
