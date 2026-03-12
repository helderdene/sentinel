<?php

use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IntakeStationController;
use App\Http\Controllers\IoTWebhookController;
use App\Http\Controllers\SmsWebhookController;
use App\Http\Controllers\StateSyncController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::prefix('webhooks')->group(function () {
    Route::post('iot-sensor', IoTWebhookController::class)
        ->middleware('verify-iot-signature')
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('webhooks.iot-sensor');

    Route::post('sms-inbound', SmsWebhookController::class)
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('webhooks.sms-inbound');
});

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Operator + Supervisor + Admin intake routes
    Route::middleware(['role:operator,supervisor,admin'])->group(function () {
        Route::get('intake', [IntakeStationController::class, 'show'])->name('intake.station');
        Route::post('intake/{incident}/triage', [IntakeStationController::class, 'triage'])->name('intake.triage');
        Route::post('intake/manual', [IntakeStationController::class, 'storeAndTriage'])->name('intake.store-and-triage');
        Route::post('intake/{incident}/override-priority', [IntakeStationController::class, 'overridePriority'])->name('intake.override-priority');
        Route::post('intake/{incident}/recall', [IntakeStationController::class, 'recall'])->name('intake.recall');
    });

    // Dispatcher + Supervisor + Admin routes
    Route::middleware(['role:dispatcher,supervisor,admin'])->group(function () {
        Route::inertia('dispatch', 'placeholder/ComingSoon', [
            'title' => 'Dispatch Console',
            'description' => 'Real-time map with incident and unit tracking. Coming in Phase 4.',
        ])->name('dispatch.index');

        Route::get('incidents/queue', [IncidentController::class, 'queue'])->name('incidents.queue');
        Route::get('incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
        Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');

        Route::get('api/priority/suggest', [IncidentController::class, 'suggestPriority'])->name('api.priority.suggest');
        Route::get('api/geocoding/search', [IncidentController::class, 'geocodingSearch'])->name('api.geocoding.search');

        Route::get('state-sync', StateSyncController::class)->name('state-sync');
    });

    // Messages -- accessible to ALL communication roles
    Route::middleware(['role:dispatcher,responder,supervisor,admin'])->group(function () {
        Route::inertia('messages', 'placeholder/ComingSoon', [
            'title' => 'Messages',
            'description' => 'Bi-directional dispatch-responder communication. Coming in Phase 5.',
        ])->name('messages.index');
    });

    // Responder routes
    Route::middleware(['role:responder'])->group(function () {
        Route::inertia('assignment', 'placeholder/ComingSoon', [
            'title' => 'Active Assignment',
            'description' => 'Receive and manage your current incident assignment. Coming in Phase 5.',
        ])->name('assignment.index');

        Route::inertia('my-incidents', 'placeholder/ComingSoon', [
            'title' => 'My Incidents',
            'description' => 'History of your incident responses. Coming in Phase 5.',
        ])->name('my-incidents.index');
    });

    // Supervisor + Admin routes
    Route::middleware(['role:supervisor,admin'])->group(function () {
        Route::inertia('units', 'placeholder/ComingSoon', [
            'title' => 'Units',
            'description' => 'Unit status and management. Coming in Phase 4.',
        ])->name('units.index');

        Route::inertia('analytics', 'placeholder/ComingSoon', [
            'title' => 'Analytics & Reports',
            'description' => 'KPI dashboard and compliance reports. Coming in Phase 7.',
        ])->name('analytics.index');
    });
});

require __DIR__.'/settings.php';
