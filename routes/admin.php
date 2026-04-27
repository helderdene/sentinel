<?php

use App\Http\Controllers\Admin\AdminBarangayController;
use App\Http\Controllers\Admin\AdminCameraController;
use App\Http\Controllers\Admin\AdminChecklistTemplateController;
use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\Admin\AdminIncidentCategoryController;
use App\Http\Controllers\Admin\AdminIncidentOutcomeController;
use App\Http\Controllers\Admin\AdminIncidentTypeController;
use App\Http\Controllers\Admin\AdminPersonnelController;
use App\Http\Controllers\Admin\AdminUnitController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\EnrollmentController;
use Illuminate\Support\Facades\Route;

Route::resource('users', AdminUserController::class);
Route::resource('incident-categories', AdminIncidentCategoryController::class);
Route::resource('incident-types', AdminIncidentTypeController::class);
Route::resource('checklist-templates', AdminChecklistTemplateController::class)->except(['show']);
Route::resource('incident-outcomes', AdminIncidentOutcomeController::class)->except(['show']);
Route::resource('barangays', AdminBarangayController::class)->only(['index', 'edit', 'update']);
Route::resource('units', AdminUnitController::class);
Route::post('units/{unit}/recommission', [AdminUnitController::class, 'recommission'])->name('units.recommission');

Route::resource('cameras', AdminCameraController::class);
Route::post('cameras/{camera}/recommission', [AdminCameraController::class, 'recommission'])->name('cameras.recommission');

Route::resource('personnel', AdminPersonnelController::class);
Route::post('personnel/{personnel}/recommission', [AdminPersonnelController::class, 'recommission'])->name('personnel.recommission');
Route::post('personnel/{personnel}/enrollments/{camera}/retry', [EnrollmentController::class, 'retry'])
    ->name('personnel.enrollment.retry');
Route::post('personnel/{personnel}/enrollments/resync', [EnrollmentController::class, 'resyncAll'])
    ->name('personnel.enrollment.resync');

Route::get('city', [AdminCityController::class, 'edit'])->name('city.edit');
Route::put('city', [AdminCityController::class, 'update'])->name('city.update');
