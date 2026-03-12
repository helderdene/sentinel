<?php

namespace App\Http\Controllers;

use App\Contracts\SmsServiceInterface;
use App\Enums\IncidentChannel;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Services\BarangayLookupService;
use App\Services\SmsParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsWebhookController extends Controller
{
    public function __construct(
        private SmsParserService $smsParser,
        private BarangayLookupService $barangayLookup,
        private SmsServiceInterface $smsService,
    ) {}

    /**
     * Handle incoming SMS webhook payload and create an incident.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $parsed = $this->smsParser->parsePayload($request->all());

        if ($parsed['message'] === '') {
            return response()->json([
                'error' => 'The message field is required.',
            ], 422);
        }

        $classification = $this->smsParser->classify($parsed['message']);
        $location = $this->smsParser->extractLocation($parsed['message']);

        $incidentType = IncidentType::query()
            ->where('code', $classification['incident_type_code'])
            ->first();

        if (! $incidentType) {
            return response()->json([
                'error' => "Incident type not found for code: {$classification['incident_type_code']}",
            ], 422);
        }

        $incident = Incident::query()->create([
            'incident_type_id' => $incidentType->id,
            'priority' => $incidentType->default_priority,
            'status' => IncidentStatus::Pending,
            'channel' => IncidentChannel::Sms,
            'caller_contact' => $parsed['sender'],
            'location_text' => $location,
            'notes' => "SMS from {$parsed['sender']}: {$parsed['message']}",
            'raw_message' => $parsed['message'],
        ]);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_created',
            'event_data' => [
                'source' => 'sms',
                'sender' => $parsed['sender'],
                'matched_keyword' => $classification['matched_keyword'],
            ],
        ]);

        $incident->load('incidentType', 'barangay');
        IncidentCreated::dispatch($incident);

        $this->smsService->send(
            $parsed['sender'],
            "Your emergency report has been received. Incident #{$incident->incident_no}. Help is on the way.",
        );

        return response()->json([
            'incident_no' => $incident->incident_no,
        ]);
    }
}
