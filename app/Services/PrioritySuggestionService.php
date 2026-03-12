<?php

namespace App\Services;

class PrioritySuggestionService
{
    /** @var array<string, int> */
    private array $escalationKeywords;

    /** @var array<string, int> */
    private array $deescalationKeywords;

    private int $escalationThreshold;

    private int $deescalationThreshold;

    private int $baseConfidence;

    private int $minConfidence;

    private int $maxConfidence;

    public function __construct()
    {
        $this->escalationKeywords = config('priority.escalation_keywords', []);
        $this->deescalationKeywords = config('priority.deescalation_keywords', []);
        $this->escalationThreshold = config('priority.escalation_threshold', 25);
        $this->deescalationThreshold = config('priority.deescalation_threshold', -25);
        $this->baseConfidence = config('priority.base_confidence', 70);
        $this->minConfidence = config('priority.min_confidence', 30);
        $this->maxConfidence = config('priority.max_confidence', 99);
    }

    /**
     * Suggest a priority and confidence score based on incident type and notes.
     *
     * @return array{priority: string, confidence: int}
     */
    public function suggest(string $defaultPriority, string $notes): array
    {
        $priority = $defaultPriority;
        $confidence = $this->baseConfidence;
        $cumulativeAdjustment = 0;

        if ($notes !== '') {
            $words = preg_split('/[\s,.\-;:!?]+/', mb_strtolower($notes));

            foreach ($words as $word) {
                if (isset($this->escalationKeywords[$word])) {
                    $cumulativeAdjustment += $this->escalationKeywords[$word];
                }

                if (isset($this->deescalationKeywords[$word])) {
                    $cumulativeAdjustment += $this->deescalationKeywords[$word];
                }
            }

            $confidence += $cumulativeAdjustment;

            if ($cumulativeAdjustment > $this->escalationThreshold) {
                $levels = (int) floor($cumulativeAdjustment / $this->escalationThreshold);

                for ($i = 0; $i < $levels; $i++) {
                    $priority = $this->escalatePriority($priority);
                }
            } elseif ($cumulativeAdjustment < $this->deescalationThreshold) {
                $levels = (int) floor(abs($cumulativeAdjustment) / abs($this->deescalationThreshold));

                for ($i = 0; $i < $levels; $i++) {
                    $priority = $this->deescalatePriority($priority);
                }
            }
        }

        $confidence = max($this->minConfidence, min($this->maxConfidence, $confidence));

        return [
            'priority' => $priority,
            'confidence' => $confidence,
        ];
    }

    /**
     * Escalate priority by one level (P4 -> P3 -> P2 -> P1).
     */
    private function escalatePriority(string $priority): string
    {
        return match ($priority) {
            'P4' => 'P3',
            'P3' => 'P2',
            'P2' => 'P1',
            default => 'P1',
        };
    }

    /**
     * De-escalate priority by one level (P1 -> P2 -> P3 -> P4).
     */
    private function deescalatePriority(string $priority): string
    {
        return match ($priority) {
            'P1' => 'P2',
            'P2' => 'P3',
            'P3' => 'P4',
            default => 'P4',
        };
    }
}
