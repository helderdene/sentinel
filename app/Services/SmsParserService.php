<?php

namespace App\Services;

use App\Contracts\SmsParserServiceInterface;

class SmsParserService implements SmsParserServiceInterface
{
    /**
     * Classify an SMS message by scanning for keywords in config('sms.keyword_map').
     *
     * @return array{incident_type_code: string, matched_keyword: string|null}
     */
    public function classify(string $message): array
    {
        $lowerMessage = mb_strtolower($message);
        $keywordMap = config('sms.keyword_map', []);

        foreach ($keywordMap as $keyword => $typeCode) {
            if (str_contains($lowerMessage, $keyword)) {
                return [
                    'incident_type_code' => $typeCode,
                    'matched_keyword' => $keyword,
                ];
            }
        }

        return [
            'incident_type_code' => config('sms.default_type_code', 'PUB-001'),
            'matched_keyword' => null,
        ];
    }

    /**
     * Extract location from an SMS message using common Filipino and English preposition patterns.
     *
     * Looks for: "sa [location]", "at [location]", "near [location]", "dito sa [location]"
     */
    public function extractLocation(string $message): ?string
    {
        $patterns = [
            '/\bdito sa\s+(.+?)(?:[,.!?]|$)/iu',
            '/\bsa\s+(.+?)(?:[,.!?]|$)/iu',
            '/\bat\s+(.+?)(?:[,.!?]|$)/iu',
            '/\bnear\s+(.+?)(?:[,.!?]|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $location = trim($matches[1]);

                if ($location !== '') {
                    return $location;
                }
            }
        }

        return null;
    }

    /**
     * Parse an inbound SMS webhook payload, normalizing sender/message/timestamp keys.
     *
     * @return array{sender: string, message: string, timestamp: string}
     */
    public function parsePayload(array $payload): array
    {
        return [
            'sender' => $payload['sender'] ?? $payload['from'] ?? '',
            'message' => $payload['message'] ?? $payload['body'] ?? '',
            'timestamp' => $payload['timestamp'] ?? $payload['date'] ?? now()->toIso8601String(),
        ];
    }
}
