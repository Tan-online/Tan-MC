<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\BulkLocationReceiveController;
use App\Http\Controllers\BackgroundTaskController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DispatchEntryController;
use App\Http\Controllers\ExecutiveMappingController;
use App\Http\Controllers\ExecutiveReplacementController;
use App\Http\Controllers\ForcedPasswordChangeController;
use App\Http\Controllers\GeneratedExportController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MasterDataExportController;
use App\Http\Controllers\MasterDataImportController;
use App\Http\Controllers\OperationAreaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/password/change-required', [ForcedPasswordChangeController::class, 'edit'])->name('password.force.edit');
    Route::put('/password/change-required', [ForcedPasswordChangeController::class, 'update'])->name('password.force.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::middleware(['verified', 'force.password.change'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/search/global', GlobalSearchController::class)->name('search.global');
    Route::get('/system/background-tasks', [BackgroundTaskController::class, 'index'])->name('background-tasks.index');
    Route::get('/system/generated-exports/{generatedExport}/download', [GeneratedExportController::class, 'download'])->name('generated-exports.download');

    Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:departments.view')->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:departments.create')->name('departments.store');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:departments.edit')->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.delete')->name('departments.destroy');

    Route::get('states', [StateController::class, 'index'])->middleware('permission:states.view')->name('states.index');
    Route::post('states', [StateController::class, 'store'])->middleware('permission:states.create')->name('states.store');
    Route::put('states/{state}', [StateController::class, 'update'])->middleware('permission:states.edit')->name('states.update');
    Route::delete('states/{state}', [StateController::class, 'destroy'])->middleware('permission:states.delete')->name('states.destroy');

    Route::get('operation-areas', [OperationAreaController::class, 'index'])->middleware('permission:operation_areas.view')->name('operation-areas.index');
    Route::post('operation-areas', [OperationAreaController::class, 'store'])->middleware('permission:operation_areas.create')->name('operation-areas.store');
    Route::put('operation-areas/{operation_area}', [OperationAreaController::class, 'update'])->middleware('permission:operation_areas.edit')->name('operation-areas.update');
    Route::delete('operation-areas/{operation_area}', [OperationAreaController::class, 'destroy'])->middleware('permission:operation_areas.delete')->name('operation-areas.destroy');

    Route::get('teams', [TeamController::class, 'index'])->middleware('permission:teams.view')->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->middleware('permission:teams.create')->name('teams.store');
    Route::put('teams/{team}', [TeamController::class, 'update'])->middleware('permission:teams.edit')->name('teams.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->middleware('permission:teams.delete')->name('teams.destroy');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.deactivate')->name('users.deactivate');
    Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.reset_password')->name('users.reset-password');

    Route::get('clients', [ClientController::class, 'index'])->middleware('permission:clients.view')->name('clients.index');
    Route::post('clients', [ClientController::class, 'store'])->middleware('permission:clients.create')->name('clients.store');
    Route::put('clients/{client}', [ClientController::class, 'update'])->middleware('permission:clients.edit')->name('clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->middleware('permission:clients.delete')->name('clients.destroy');

    Route::get('locations', [LocationController::class, 'index'])->middleware('permission:locations.view')->name('locations.index');
    Route::post('locations', [LocationController::class, 'store'])->middleware('permission:locations.create')->name('locations.store');
    Route::put('locations/{location}', [LocationController::class, 'update'])->middleware('permission:locations.edit')->name('locations.update');
    Route::delete('locations/{location}', [LocationController::class, 'destroy'])->middleware('permission:locations.delete')->name('locations.destroy');

    Route::get('contracts', [ContractController::class, 'index'])->middleware('permission:contracts.view')->name('contracts.index');
    Route::post('contracts', [ContractController::class, 'store'])->middleware('permission:contracts.create')->name('contracts.store');
    Route::put('contracts/{contract}', [ContractController::class, 'update'])->middleware('permission:contracts.edit')->name('contracts.update');
    Route::delete('contracts/{contract}', [ContractController::class, 'destroy'])->middleware('permission:contracts.delete')->name('contracts.destroy');

    Route::get('service-orders', [ServiceOrderController::class, 'index'])->middleware('permission:service_orders.view')->name('service-orders.index');
    Route::post('service-orders', [ServiceOrderController::class, 'store'])->middleware('permission:service_orders.create')->name('service-orders.store');
    Route::put('service-orders/{service_order}', [ServiceOrderController::class, 'update'])->middleware('permission:service_orders.edit')->name('service-orders.update');
    Route::delete('service-orders/{service_order}', [ServiceOrderController::class, 'destroy'])->middleware('permission:service_orders.delete')->name('service-orders.destroy');

    Route::get('executive-mappings', [ExecutiveMappingController::class, 'index'])->middleware('permission:executive_mappings.view')->name('executive-mappings.index');
    Route::post('executive-mappings', [ExecutiveMappingController::class, 'store'])->middleware('permission:executive_mappings.create')->name('executive-mappings.store');
    Route::put('executive-mappings/{executive_mapping}', [ExecutiveMappingController::class, 'update'])->middleware('permission:executive_mappings.edit')->name('executive-mappings.update');
    Route::delete('executive-mappings/{executive_mapping}', [ExecutiveMappingController::class, 'destroy'])->middleware('permission:executive_mappings.delete')->name('executive-mappings.destroy');

    Route::get('imports/{type}/template', [MasterDataImportController::class, 'template'])->name('imports.template');
    Route::post('imports/{type}', [MasterDataImportController::class, 'store'])->name('imports.store');
    Route::get('exports/{type}', [MasterDataExportController::class, 'export'])->name('exports.master-data');

    Route::get('operations/dispatch-entry', [DispatchEntryController::class, 'index'])->middleware('permission:dispatch_entry.view')->name('dispatch-entry.index');
    Route::patch('operations/dispatch-entry/{dispatchEntry}/dispatch', [DispatchEntryController::class, 'dispatch'])->middleware('permission:service_orders.dispatch')->name('dispatch-entry.dispatch');

    Route::get('operations/bulk-receive', [BulkLocationReceiveController::class, 'index'])->middleware('permission:workflow.view')->name('bulk-receive.index');
    Route::post('operations/bulk-receive', [BulkLocationReceiveController::class, 'store'])->middleware('permission:muster.submit')->name('bulk-receive.store');
    Route::patch('operations/bulk-receive/{musterExpected}/review', [BulkLocationReceiveController::class, 'review'])->middleware('permission:muster.review')->name('bulk-receive.review');
    Route::patch('operations/bulk-receive/{musterExpected}/final-close', [BulkLocationReceiveController::class, 'finalClose'])->middleware('permission:workflow.final_close')->name('bulk-receive.final-close');
    Route::get('workflow/approvals', [BulkLocationReceiveController::class, 'index'])->middleware('permission:workflow.view')->name('workflow.approvals.index');

    Route::get('mapping/executive-replacements', [ExecutiveReplacementController::class, 'index'])->middleware('permission:executive_replacements.view')->name('executive-replacements.index');
    Route::post('mapping/executive-replacements', [ExecutiveReplacementController::class, 'store'])->middleware('permission:executive_replacements.create')->name('executive-replacements.store');

    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('reports/{report}/{format}', [ReportController::class, 'export'])->middleware('permission:reports.export')->name('reports.export');
    });
});

require __DIR__.'/auth.php';
