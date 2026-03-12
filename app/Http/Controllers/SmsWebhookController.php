<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsWebhookController extends Controller
{
    /**
     * Handle incoming SMS webhook payload.
     *
     * Placeholder -- full implementation in Task 2.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['error' => 'Not implemented'], 501);
    }
}
