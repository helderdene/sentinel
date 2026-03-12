<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIoTSignature
{
    /**
     * Handle an incoming request by verifying the HMAC-SHA256 signature.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature-256');
        $timestamp = $request->header('X-Timestamp');
        $secret = config('services.iot.webhook_secret');

        if (! $signature || ! $timestamp || ! $secret) {
            abort(401, 'Missing signature headers');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            abort(401, 'Request timestamp expired');
        }

        $payload = $timestamp.'.'.$request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid signature');
        }

        return $next($request);
    }
}
