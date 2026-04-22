<?php

use App\Http\Controllers\Admin\AdminPersonnelPhotoController;
use App\Http\Controllers\FrasEventFaceController;
use App\Http\Controllers\FrasEventSceneController;
use Illuminate\Support\Facades\Route;

/*
 | FRAS authenticated routes gated at role:operator,supervisor,admin (D-22).
 | Registered in bootstrap/app.php alongside routes/admin.php under the
 | middleware chain ['web','auth','verified','role:operator,supervisor,admin'].
 |
 | Per-route URL + name prefixes are applied explicitly below so legacy
 | operator-facing URLs remain /admin/personnel/{id}/photo (name
 | `admin.personnel.photo`) while newer FRAS-specific routes live under
 | /fras/* (name `fras.*`).
 */

// Legacy admin-prefixed operator photo stream (Phase 20 D-22).
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('personnel/{personnel}/photo', [AdminPersonnelPhotoController::class, 'show'])
        ->middleware('signed')
        ->name('personnel.photo');
});

// Phase 21 D-20 (option a): signed 5-min URL for face-image crops on the
// IntakeStation FRAS rail + modal. Role gate enforced at the bootstrap
// layer; signed middleware added here per-route. Phase 22 will layer
// `fras_access_log` writes inside FrasEventFaceController.
Route::get('fras/events/{event}/face', [FrasEventFaceController::class, 'show'])
    ->middleware('signed')
    ->name('fras.event.face');

// Phase 22 D-26: scene image endpoint — operator/supervisor/admin only
// (defense-in-depth layer 1 of 3 for responder exclusion). Every fetch
// writes a fras_access_log row synchronously per D-16.
Route::get('fras/events/{event}/scene', [FrasEventSceneController::class, 'show'])
    ->middleware('signed')
    ->name('fras.events.scene.show');
