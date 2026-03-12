<?php

namespace App\Contracts;

interface SmsServiceInterface
{
    /**
     * Send an SMS message to a phone number.
     */
    public function send(string $to, string $message): void;

    /**
     * Parse an inbound SMS webhook payload.
     *
     * @return array{sender: string, message: string, timestamp: string}
     */
    public function parseInbound(array $payload): array;
}
