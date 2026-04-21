<?php

use App\Http\Controllers\Admin\AdminBarangayController;
use App\Http\Controllers\Admin\AdminCameraController;
use App\Http\Controllers\Admin\AdminChecklistTemplateController;
use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\Admin\AdminIncidentCategoryController;
use App\Http\Controllers\Admin\AdminIncidentTypeController;
use App\Http\Controllers\Admin\AdminUnitController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::resource('users', AdminUserController::class);
Route::resource('incident-categories', AdminIncidentCategoryController::class);
Route::resource('incident-types', AdminIncidentTypeController::class);
Route::resource('checklist-templates', AdminChecklistTemplateController::class)->except(['show']);
Route::resource('barangays', AdminBarangayController::class)->only(['index', 'edit', 'update']);
Route::resource('units', AdminUnitController::class);
Route::post('units/{unit}/recommission', [AdminUnitController::class, 'recommission'])->name('units.recommission');

Route::resource('cameras', AdminCameraController::class);
Route::post('cameras/{camera}/recommission', [AdminCameraController::class, 'recommission'])->name('cameras.recommission');

Route::get('city', [AdminCityController::class, 'edit'])->name('city.edit');
Route::put('city', [AdminCityController::class, 'update'])->name('city.update');
