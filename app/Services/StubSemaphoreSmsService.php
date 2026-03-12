<?php

namespace App\Services;

use App\Contracts\SmsServiceInterface;
use Illuminate\Support\Facades\Log;

class StubSemaphoreSmsService implements SmsServiceInterface
{
    /**
     * Send an SMS message to a phone number.
     */
    public function send(string $to, string $message): void
    {
        Log::info('StubSemaphoreSmsService::send', [
            'to' => $to,
            'message' => $message,
        ]);
    }

    /**
     * Parse an inbound SMS webhook payload.
     *
     * @return array{sender: string, message: string, timestamp: string}
     */
    public function parseInbound(array $payload): array
    {
        return [
            'sender' => $payload['sender'] ?? $payload['from'] ?? '',
            'message' => $payload['message'] ?? $payload['body'] ?? '',
            'timestamp' => $payload['timestamp'] ?? $payload['date'] ?? now()->toIso8601String(),
        ];
    }
}
