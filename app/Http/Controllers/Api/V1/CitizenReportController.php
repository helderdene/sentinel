<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IncidentChannel;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCitizenReportRequest;
use App\Http\Resources\V1\CitizenBarangayResource;
use App\Http\Resources\V1\CitizenIncidentTypeResource;
use App\Http\Resources\V1\CitizenReportResource;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Services\BarangayLookupService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CitizenReportController extends Controller
{
    public function __construct(
        private BarangayLookupService $barangayLookup,
    ) {}

    /**
     * Return incident types visible in the citizen app.
     * Always includes "Other Emergency" regardless of show_in_public_app flag.
     */
    public function incidentTypes(): AnonymousResourceCollection
    {
        $types = IncidentType::active()
            ->where(function (Builder $q) {
                $q->where('show_in_public_app', true)
                    ->orWhere('code', 'OTHER_EMERGENCY');
            })
            ->orderBy('sort_order')
            ->get();

        return CitizenIncidentTypeResource::collection($types);
    }

    /**
     * Return barangay id and name list (no geometry).
     */
    public function barangays(): AnonymousResourceCollection
    {
        $barangays = Barangay::query()
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        return CitizenBarangayResource::collection($barangays);
    }

    /**
     * Create a new citizen emergency report.
     */
    public function store(StoreCitizenReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $incidentType = IncidentType::query()->findOrFail($validated['incident_type_id']);

        $data = [
            'incident_type_id' => $incidentType->id,
            'priority' => $incidentType->default_priority,
            'status' => IncidentStatus::Pending,
            'channel' => IncidentChannel::App,
            'caller_name' => $validated['caller_name'] ?? null,
            'caller_contact' => $validated['caller_contact'],
            'location_text' => $validated['location_text'] ?? null,
            'notes' => $validated['description'],
            'tracking_token' => Incident::generateTrackingToken(),
        ];

        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        if ($latitude !== null && $longitude !== null) {
            $data['coordinates'] = Point::makeGeodetic((float) $latitude, (float) $longitude);

            $barangay = $this->barangayLookup->findByCoordinates((float) $latitude, (float) $longitude);

            if ($barangay) {
                $data['barangay_id'] = $barangay->id;
            }
        } elseif (isset($validated['barangay_id'])) {
            $data['barangay_id'] = $validated['barangay_id'];
        }

        $incident = Incident::query()->create($data);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_created',
            'event_data' => [
                'source' => 'citizen_app',
                'tracking_token' => $incident->tracking_token,
            ],
        ]);

        $incident->load('incidentType', 'barangay');

        IncidentCreated::dispatch($incident);

        return (new CitizenReportResource($incident))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Look up a citizen report by tracking token.
     */
    public function show(string $token): CitizenReportResource
    {
        $incident = Incident::query()
            ->where('tracking_token', $token)
            ->with('incidentType', 'barangay')
            ->firstOrFail();

        return new CitizenReportResource($incident);
    }
}
