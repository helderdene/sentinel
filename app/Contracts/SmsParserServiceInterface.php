<?php

namespace App\Contracts;

interface SmsParserServiceInterface
{
    /**
     * Classify an SMS message by scanning for incident-related keywords.
     *
     * @return array{incident_type_code: string, matched_keyword: string|null}
     */
    public function classify(string $message): array;

    /**
     * Extract a location string from an SMS message using common preposition patterns.
     */
    public function extractLocation(string $message): ?string;

    /**
     * Parse an inbound SMS webhook payload, normalizing sender/message/timestamp keys.
     *
     * @return array{sender: string, message: string, timestamp: string}
     */
    public function parsePayload(array $payload): array;
}
