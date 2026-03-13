<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CitizenReportResource extends JsonResource
{
    /**
     * Mapping from internal IncidentStatus values to citizen-facing labels.
     *
     * @var array<string, string>
     */
    public const CITIZEN_STATUS_MAP = [
        'PENDING' => 'Received',
        'TRIAGED' => 'Verified',
        'DISPATCHED' => 'Dispatched',
        'ACKNOWLEDGED' => 'Dispatched',
        'EN_ROUTE' => 'Dispatched',
        'ON_SCENE' => 'Dispatched',
        'RESOLVING' => 'Dispatched',
        'RESOLVED' => 'Resolved',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tracking_token' => $this->tracking_token,
            'type' => $this->incidentType?->name,
            'category' => $this->incidentType?->category,
            'priority' => $this->priority ? (int) substr($this->priority->value, 1) : null,
            'status' => self::CITIZEN_STATUS_MAP[$this->status->value] ?? 'Unknown',
            'barangay' => $this->barangay?->name,
            'location_text' => $this->location_text,
            'description' => $this->notes,
            'submitted_at' => $this->created_at?->toISOString(),
        ];
    }
}
