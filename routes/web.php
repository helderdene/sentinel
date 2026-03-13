<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DispatchConsoleController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IntakeStationController;
use App\Http\Controllers\IoTWebhookController;
use App\Http\Controllers\ResponderController;
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
        Route::get('dispatch', [DispatchConsoleController::class, 'show'])->name('dispatch.console');
        Route::post('dispatch/{incident}/assign', [DispatchConsoleController::class, 'assignUnit'])->name('dispatch.assign');
        Route::post('dispatch/{incident}/unassign', [DispatchConsoleController::class, 'unassignUnit'])->name('dispatch.unassign');
        Route::post('dispatch/{incident}/advance-status', [DispatchConsoleController::class, 'advanceStatus'])->name('dispatch.advance-status');
        Route::post('dispatch/{incident}/mutual-aid', [DispatchConsoleController::class, 'requestMutualAid'])->name('dispatch.mutual-aid');
        Route::get('dispatch/{incident}/nearby-units', [DispatchConsoleController::class, 'nearbyUnits'])->name('dispatch.nearby-units');
        Route::post('dispatch/{incident}/message', [DispatchConsoleController::class, 'sendMessage'])->name('dispatch.send-message');

        Route::get('incidents/queue', [IncidentController::class, 'queue'])->name('incidents.queue');
        Route::get('incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
        Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');

        Route::get('state-sync', StateSyncController::class)->name('state-sync');
    });

    // Shared API routes -- accessible to operator, dispatcher, supervisor, admin
    Route::middleware(['role:operator,dispatcher,supervisor,admin'])->group(function () {
        Route::get('api/priority/suggest', [IncidentController::class, 'suggestPriority'])->name('api.priority.suggest');
        Route::get('api/geocoding/search', [IncidentController::class, 'geocodingSearch'])->name('api.geocoding.search');
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
        Route::get('responder', [ResponderController::class, 'show'])->name('responder.station');
        Route::post('responder/{incident}/acknowledge', [ResponderController::class, 'acknowledge'])->name('responder.acknowledge');
        Route::post('responder/{incident}/advance-status', [ResponderController::class, 'advanceStatus'])->name('responder.advance-status');
        Route::post('responder/location', [ResponderController::class, 'updateLocation'])->name('responder.update-location');
        Route::post('responder/{incident}/message', [ResponderController::class, 'sendMessage'])->name('responder.send-message');
        Route::patch('responder/{incident}/checklist', [ResponderController::class, 'updateChecklist'])->name('responder.update-checklist');
        Route::patch('responder/{incident}/vitals', [ResponderController::class, 'updateVitals'])->name('responder.update-vitals');
        Route::patch('responder/{incident}/assessment-tags', [ResponderController::class, 'updateAssessmentTags'])->name('responder.update-assessment-tags');
        Route::post('responder/{incident}/resolve', [ResponderController::class, 'resolve'])->name('responder.resolve');
        Route::post('responder/{incident}/request-resource', [ResponderController::class, 'requestResource'])->name('responder.request-resource');

        // Backward-compatible aliases for placeholder routes replaced by responder.station
        Route::get('assignment', [ResponderController::class, 'show'])->name('assignment.index');
        Route::inertia('my-incidents', 'placeholder/ComingSoon', [
            'title' => 'My Incidents',
            'description' => 'History of your incident responses. Coming soon.',
        ])->name('my-incidents.index');
    });

    // Supervisor + Admin routes
    Route::middleware(['role:supervisor,admin'])->group(function () {
        Route::redirect('units', '/admin/units')->name('units.index');
    });

    // Analytics routes (supervisor + admin, gate-checked in controller)
    Route::middleware(['role:supervisor,admin'])->prefix('analytics')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard'])->name('analytics.dashboard');
        Route::get('/heatmap', [AnalyticsController::class, 'heatmap'])->name('analytics.heatmap');
        Route::get('/heatmap/barangay/{barangay}', [AnalyticsController::class, 'barangayDetail'])->name('analytics.barangay-detail');
        Route::get('/reports', [AnalyticsController::class, 'reports'])->name('analytics.reports');
        Route::get('/reports/{generated_report}/download', [AnalyticsController::class, 'downloadReport'])->name('analytics.download-report');
        Route::post('/reports/generate', [AnalyticsController::class, 'generateReport'])->name('analytics.generate-report');
    });
});

require __DIR__.'/settings.php';
