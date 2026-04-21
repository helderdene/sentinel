<?php

namespace App\Http\Controllers\Fras;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, unauthenticated photo endpoint used by FRAS cameras during enrollment
 * (D-20..D-23). The token IS the access boundary (UUIDv4 = 122 bits of entropy).
 *
 * Access is revoked automatically once every enrollment for the personnel has
 * settled (done/failed); while at least one row is still pending or syncing
 * the cameras can fetch the binary, after which 404 ends the window.
 */
class FrasPhotoAccessController extends Controller
{
    public function show(string $token, Request $request): StreamedResponse
    {
        $personnel = Personnel::where('photo_access_token', $token)->first();
        if (! $personnel) {
            abort(404);
        }

        $hasLive = $personnel->enrollments()
            ->whereIn('status', ['pending', 'syncing'])
            ->exists();
        if (! $hasLive) {
            abort(404);
        }

        Log::channel('mqtt')->info('fras.photo.access', [
            'personnel_id' => $personnel->id,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
        ]);

        /** @var StreamedResponse $response */
        $response = Storage::disk('fras_photos')->response("personnel/{$personnel->id}.jpg");

        return $response;
    }
}
