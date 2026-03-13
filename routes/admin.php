<?php

use App\Http\Controllers\Admin\AdminBarangayController;
use App\Http\Controllers\Admin\AdminIncidentTypeController;
use App\Http\Controllers\Admin\AdminUnitController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::resource('users', AdminUserController::class);
Route::resource('incident-types', AdminIncidentTypeController::class);
Route::resource('barangays', AdminBarangayController::class)->only(['index', 'edit', 'update']);
Route::resource('units', AdminUnitController::class);
Route::post('units/{unit}/recommission', [AdminUnitController::class, 'recommission'])->name('units.recommission');
