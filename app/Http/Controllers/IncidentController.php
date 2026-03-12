<?php

namespace App\Http\Controllers;

use App\Contracts\GeocodingServiceInterface;
use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Http\Requests\StoreIncidentRequest;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\User;
use App\Services\BarangayLookupService;
use App\Services\PrioritySuggestionService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function __construct(
        private BarangayLookupService $barangayLookup,
        private PrioritySuggestionService $prioritySuggestion,
        private GeocodingServiceInterface $geocoding,
    ) {}

    /**
     * Display the dispatch queue with TRIAGED incidents ordered by priority then FIFO.
     */
    public function queue(): Response
    {
        $incidents = Incident::query()
            ->with('incidentType', 'barangay')
            ->where('status', IncidentStatus::Triaged)
            ->orderByRaw("CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 END")
            ->orderBy('created_at', 'asc')
            ->get();

        $channelCounts = Incident::query()
            ->where('status', IncidentStatus::Triaged)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel');

        return Inertia::render('incidents/Queue', [
            'incidents' => $incidents,
            'channelCounts' => $channelCounts,
        ]);
    }

    /**
     * Show the triage form for creating a new incident.
     */
    public function create(): Response
    {
        $incidentTypes = IncidentType::query()
            ->active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return Inertia::render('incidents/Create', [
            'incidentTypes' => $incidentTypes,
            'channels' => IncidentChannel::cases(),
            'priorities' => IncidentPriority::cases(),
            'priorityConfig' => config('priority'),
        ]);
    }

    /**
     * Store a newly created incident from the triage form.
     */
    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $data = [
            'incident_type_id' => $validated['incident_type_id'],
            'priority' => $validated['priority'],
            'channel' => $validated['channel'],
            'location_text' => $validated['location_text'],
            'caller_name' => $validated['caller_name'] ?? null,
            'caller_contact' => $validated['caller_contact'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => IncidentStatus::Pending,
            'created_by' => $request->user()->id,
        ];

        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        if ($latitude !== null && $longitude !== null) {
            $data['coordinates'] = Point::makeGeodetic((float) $latitude, (float) $longitude);
        }

        if (isset($validated['barangay_id'])) {
            $data['barangay_id'] = $validated['barangay_id'];
        } elseif ($latitude !== null && $longitude !== null) {
            $barangay = $this->barangayLookup->findByCoordinates((float) $latitude, (float) $longitude);

            if ($barangay) {
                $data['barangay_id'] = $barangay->id;
            }
        }

        $incident = Incident::query()->create($data);

        $incident->load('incidentType', 'barangay');
        IncidentCreated::dispatch($incident);

        $incidentType = IncidentType::find($validated['incident_type_id']);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_created',
            'event_data' => [
                'channel' => $incident->channel->value,
                'priority' => $incident->priority->value,
                'incident_type' => $incidentType?->name,
                'location' => $incident->location_text,
                'caller_name' => $incident->caller_name,
            ],
            'actor_type' => User::class,
            'actor_id' => $request->user()->id,
            'notes' => "Incident reported via {$incident->channel->value} channel",
        ]);

        $this->logPriorityOverrideIfNeeded($incident, $validated);

        return redirect()->route('incidents.queue')
            ->with('success', "Incident {$incident->incident_no} created successfully.");
    }

    /**
     * Display a listing of all incidents with optional status filter.
     */
    public function index(Request $request): Response
    {
        $query = Incident::query()
            ->with('incidentType', 'barangay')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $incidents = $query->cursorPaginate(25);

        return Inertia::render('incidents/Index', [
            'incidents' => $incidents,
        ]);
    }

    /**
     * Display the incident detail with timeline and messages.
     */
    public function show(Incident $incident): Response
    {
        $incident->load('incidentType', 'barangay', 'createdBy', 'timeline.actor', 'messages');

        return Inertia::render('incidents/Show', [
            'incident' => $incident,
        ]);
    }

    /**
     * Return priority suggestion as JSON for frontend live preview.
     */
    public function suggestPriority(Request $request): JsonResponse
    {
        $request->validate([
            'incident_type_id' => ['required', 'exists:incident_types,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $type = IncidentType::findOrFail($request->input('incident_type_id'));
        $suggestion = $this->prioritySuggestion->suggest(
            $type->default_priority,
            $request->input('notes', ''),
        );

        return response()->json($suggestion);
    }

    /**
     * Return geocoding results as JSON for frontend location autocomplete.
     */
    public function geocodingSearch(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $results = $this->geocoding->forward($request->input('query'));

        return response()->json($results);
    }

    /**
     * Log a priority override to the incident timeline if the selected priority
     * differs from what the suggestion service would have recommended.
     */
    private function logPriorityOverrideIfNeeded(Incident $incident, array $validated): void
    {
        $type = IncidentType::find($validated['incident_type_id']);

        if (! $type) {
            return;
        }

        $suggestion = $this->prioritySuggestion->suggest(
            $type->default_priority,
            $validated['notes'] ?? '',
        );

        if ($suggestion['priority'] !== $validated['priority']) {
            IncidentTimeline::query()->create([
                'incident_id' => $incident->id,
                'event_type' => 'priority_override',
                'event_data' => [
                    'suggested' => $suggestion['priority'],
                    'selected' => $validated['priority'],
                    'confidence' => $suggestion['confidence'],
                ],
                'actor_type' => User::class,
                'actor_id' => $incident->created_by,
                'notes' => "Priority overridden from suggested {$suggestion['priority']} to {$validated['priority']}",
            ]);
        }
    }
}
