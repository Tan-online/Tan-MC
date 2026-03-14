<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\BulkLocationReceiveController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ExecutiveMappingController;
use App\Http\Controllers\ExecutiveReplacementController;
use App\Http\Controllers\LocationController;
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
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('states', StateController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('operation-areas', OperationAreaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('teams', TeamController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('users', UserController::class)->only(['index', 'store', 'update'])->middleware('role:super_admin,admin,Admin');
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('role:super_admin,admin,Admin')->name('users.deactivate');
    Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('role:super_admin,admin,Admin')->name('users.reset-password');
    Route::resource('clients', ClientController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('contracts', ContractController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('service-orders', ServiceOrderController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('executive-mappings', ExecutiveMappingController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('imports/{type}/template', [MasterDataImportController::class, 'template'])->name('imports.template');
    Route::post('imports/{type}', [MasterDataImportController::class, 'store'])->name('imports.store');
    Route::get('operations/bulk-receive', [BulkLocationReceiveController::class, 'index'])->middleware('role:super_admin,admin,operations,reviewer,Admin,HOD,Manager,Dispatch,Executive')->name('bulk-receive.index');
    Route::post('operations/bulk-receive', [BulkLocationReceiveController::class, 'store'])->middleware('role:super_admin,admin,operations,reviewer,Admin,HOD,Manager,Dispatch,Executive')->name('bulk-receive.store');
    Route::patch('operations/bulk-receive/{musterExpected}/review', [BulkLocationReceiveController::class, 'review'])->middleware('role:super_admin,admin,reviewer,Admin,HOD,Manager')->name('bulk-receive.review');
    Route::get('mapping/executive-replacements', [ExecutiveReplacementController::class, 'index'])->middleware('role:super_admin,admin,Admin,HOD,Manager')->name('executive-replacements.index');
    Route::post('mapping/executive-replacements', [ExecutiveReplacementController::class, 'store'])->middleware('role:super_admin,admin,Admin,HOD,Manager')->name('executive-replacements.store');
    Route::get('reports', [ReportController::class, 'index'])->middleware('role:super_admin,admin,reviewer,viewer,Admin,HOD,Manager')->name('reports.index');
    Route::get('reports/{report}/{format}', [ReportController::class, 'export'])->middleware('role:super_admin,admin,reviewer,viewer,Admin,HOD,Manager')->name('reports.export');
});

require __DIR__.'/auth.php';
