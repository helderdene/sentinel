<?php

namespace App\Http\Controllers;

use App\Http\Requests\Fras\PromoteRecognitionEventRequest;
use App\Models\RecognitionEvent;
use App\Services\FrasIncidentFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class FrasEventHistoryController extends Controller
{
    public function __construct(
        private readonly FrasIncidentFactory $factory,
    ) {}

    /**
     * Stub — full filter + replay-count + signed-URL hydration lands in
     * Task 2 of Plan 22-05.
     */
    public function index(Request $request): Response
    {
        abort(501, 'FrasEventHistoryController::index lands in Plan 22-05 Task 2.');
    }

    /**
     * Stub — promotes a recognition event to an incident via the
     * FrasIncidentFactory. Full delegation body lands in Task 2.
     */
    public function promote(PromoteRecognitionEventRequest $request, RecognitionEvent $event): RedirectResponse
    {
        abort(501, 'FrasEventHistoryController::promote lands in Plan 22-05 Task 2.');
    }
}
