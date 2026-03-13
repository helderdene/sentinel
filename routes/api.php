<?php

use App\Http\Controllers\Api\V1\CitizenReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/citizen')->group(function () {
    Route::get('incident-types', [CitizenReportController::class, 'incidentTypes'])
        ->middleware('throttle:citizen-reads')
        ->name('api.citizen.incident-types');

    Route::get('barangays', [CitizenReportController::class, 'barangays'])
        ->middleware('throttle:citizen-reads')
        ->name('api.citizen.barangays');

    Route::post('reports', [CitizenReportController::class, 'store'])
        ->middleware('throttle:citizen-reports')
        ->name('api.citizen.reports.store');

    Route::get('reports/{token}', [CitizenReportController::class, 'show'])
        ->middleware('throttle:citizen-reads')
        ->name('api.citizen.reports.show');
});
