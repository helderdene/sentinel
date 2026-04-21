<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPersonnelPhotoController extends Controller
{
    /**
     * Stream a personnel photo to an authenticated operator/supervisor/admin
     * (D-22) via a short-lived signed URL. The route is registered in
     * routes/fras.php with middleware chain:
     *   ['web','auth','verified','role:operator,supervisor,admin','signed']
     */
    public function show(Personnel $personnel): StreamedResponse
    {
        if (! $personnel->photo_path) {
            abort(404);
        }

        /** @var StreamedResponse $response */
        $response = Storage::disk('fras_photos')->response("personnel/{$personnel->id}.jpg");

        return $response;
    }
}
