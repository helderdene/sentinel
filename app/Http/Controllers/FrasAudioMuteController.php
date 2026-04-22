<?php

namespace App\Http\Controllers;

use App\Http\Requests\Fras\UpdateFrasAudioMuteRequest;
use Illuminate\Http\RedirectResponse;

final class FrasAudioMuteController extends Controller
{
    /**
     * Update the authenticated user's fras_audio_muted preference.
     *
     * Scoped to $request->user() — no user_id parameter accepted (T-22-05-05
     * mitigation: prevents cross-user preference tampering).
     */
    public function update(UpdateFrasAudioMuteRequest $request): RedirectResponse
    {
        $request->user()->update([
            'fras_audio_muted' => $request->validated('muted'),
        ]);

        return back();
    }
}
