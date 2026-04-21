<?php

use App\Http\Controllers\Admin\AdminPersonnelPhotoController;
use Illuminate\Support\Facades\Route;

/*
 | FRAS authenticated routes gated at role:operator,supervisor,admin (D-22).
 | Registered in bootstrap/app.php alongside routes/admin.php. The URL prefix
 | ("admin/") and name prefix ("admin.") are preserved so operator-facing URLs
 | remain /admin/personnel/{id}/photo even though the role gate is broader
 | than `role:admin` (the admin.php group inherits).
 */

Route::get('personnel/{personnel}/photo', [AdminPersonnelPhotoController::class, 'show'])
    ->middleware('signed')
    ->name('personnel.photo');
