<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyPushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    /**
     * Store or update a push subscription for the authenticated user.
     */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->updatePushSubscription(
            $request->validated('endpoint'),
            $request->validated('public_key'),
            $request->validated('auth_token'),
            $request->validated('content_encoding'),
        );

        return response()->json(['message' => 'Subscription saved.'], 201);
    }

    /**
     * Remove a push subscription for the authenticated user.
     */
    public function destroy(DestroyPushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->deletePushSubscription($request->validated('endpoint'));

        return response()->json(null, 204);
    }
}
