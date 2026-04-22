<?php

namespace App\Http\Controllers;

use App\Enums\IncidentPriority;
use App\Enums\RecognitionSeverity;
use App\Http\Requests\Fras\PromoteRecognitionEventRequest;
use App\Models\Camera;
use App\Models\RecognitionEvent;
use App\Models\User;
use App\Services\FrasIncidentFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

final class FrasEventHistoryController extends Controller
{
    public function __construct(
        private readonly FrasIncidentFactory $factory,
    ) {}

    /**
     * Render the /fras/events paginated history page.
     *
     * Filter query params: severity[] (critical|warning|info), camera_id
     * (uuid), q (substring ILIKE over personnel.name + camera.camera_id_display
     * + camera.name), from + to (ISO date), page.
     *
     * Replay-count hydration uses the two-query group-by pattern recommended
     * in RESEARCH §2 — one paginator query + one aggregate COUNT(*)
     * keyed by (camera_id, personnel_id) over the last 24h. Emitted as a
     * `{camera_id}:{personnel_id}` → int map that the Vue layer looks up
     * against the row's camera + personnel IDs.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'severity' => ['sometimes', 'array'],
            'severity.*' => ['string', 'in:critical,warning,info'],
            'camera_id' => ['sometimes', 'nullable', 'uuid'],
            'q' => ['sometimes', 'nullable', 'string', 'max:64'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $severity = $validated['severity'] ?? null;
        $cameraId = $validated['camera_id'] ?? null;
        $q = $validated['q'] ?? null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        $paginator = RecognitionEvent::query()
            ->with([
                'camera:id,camera_id_display,name',
                'personnel:id,name,category',
                'incident:id,incident_no,priority,status',
                'acknowledgedBy:id,name',
                'dismissedBy:id,name',
            ])
            ->when($severity, fn ($query, $s) => $query->whereIn('severity', $s))
            ->when($cameraId, fn ($query, $id) => $query->where('camera_id', $id))
            ->when($from, fn ($query, $d) => $query->where('captured_at', '>=', $d))
            ->when($to, fn ($query, $d) => $query->where('captured_at', '<=', $d))
            ->when($q, fn ($query, $term) => $query->where(fn ($w) => $w
                ->whereHas('personnel', fn ($p) => $p->where('name', 'ilike', "%{$term}%"))
                ->orWhereHas('camera', fn ($c) => $c
                    ->where('camera_id_display', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                )
            ))
            ->orderByDesc('captured_at')
            ->paginate(25)
            ->withQueryString();

        $collection = $paginator->getCollection();

        $pairs = $collection
            ->filter(fn (RecognitionEvent $e) => $e->personnel_id !== null)
            ->map(fn (RecognitionEvent $e) => [$e->camera_id, $e->personnel_id])
            ->unique(fn ($pair) => $pair[0].'-'.$pair[1])
            ->values();

        $replayCounts = [];
        if ($pairs->isNotEmpty()) {
            $rows = RecognitionEvent::query()
                ->whereIn('camera_id', $pairs->pluck(0)->all())
                ->whereIn('personnel_id', $pairs->pluck(1)->all())
                ->where('captured_at', '>=', now()->subDay())
                ->selectRaw('camera_id, personnel_id, COUNT(*) as n')
                ->groupBy('camera_id', 'personnel_id')
                ->get();

            foreach ($rows as $row) {
                $replayCounts["{$row->camera_id}:{$row->personnel_id}"] = (int) $row->n;
            }
        }

        $user = $request->user();
        $paginator->setCollection($collection->map(
            fn (RecognitionEvent $event) => $this->mapEventRow($event, $user),
        ));

        $availableCameras = Camera::query()
            ->whereNull('decommissioned_at')
            ->get(['id', 'camera_id_display', 'name']);

        return Inertia::render('fras/Events', [
            'events' => $paginator,
            'filters' => [
                'severity' => $severity ?? [],
                'camera_id' => $cameraId,
                'q' => $q,
                'from' => $from,
                'to' => $to,
            ],
            'availableCameras' => $availableCameras,
            'replayCounts' => $replayCounts,
        ]);
    }

    /**
     * Manually promote a recognition event to an incident.
     *
     * Delegates to the factory's manual-promote entrypoint which bypasses
     * the automatic severity / confidence / dedup gate chain in favor of
     * the operator's explicit priority + reason (full audit trail lands in
     * the IncidentTimeline event_data blob with trigger
     * `fras_operator_promote` — see Plan 22-04).
     */
    public function promote(PromoteRecognitionEventRequest $request, RecognitionEvent $event): RedirectResponse
    {
        $incident = $this->factory->createFromRecognitionManual(
            $event,
            IncidentPriority::from($request->validated('priority')),
            $request->validated('reason'),
            $request->user(),
        );

        return redirect()->route('incidents.show', $incident);
    }

    /**
     * Map a RecognitionEvent model into the Inertia row shape per UI-SPEC §2.
     *
     * @return array<string, mixed>
     */
    private function mapEventRow(RecognitionEvent $event, ?User $user): array
    {
        $faceImageUrl = $event->face_image_path
            ? URL::temporarySignedRoute(
                'fras.event.face',
                now()->addMinutes(5),
                ['event' => $event->id],
            )
            : null;

        return [
            'id' => $event->id,
            'camera_id' => $event->camera_id,
            'personnel_id' => $event->personnel_id,
            'severity' => $event->severity->value,
            'personnel' => $event->personnel
                ? [
                    'id' => $event->personnel->id,
                    'name' => $event->personnel->name,
                    'category' => $event->personnel->category?->value,
                ]
                : null,
            'camera' => $event->camera
                ? [
                    'id' => $event->camera->id,
                    'camera_id_display' => $event->camera->camera_id_display,
                    'name' => $event->camera->name,
                ]
                : null,
            'captured_at' => $event->captured_at?->toIso8601String(),
            'face_image_url' => $faceImageUrl,
            'incident_id' => $event->incident_id,
            'incident' => $event->incident
                ? [
                    'id' => $event->incident->id,
                    'incident_no' => $event->incident->incident_no,
                    'priority' => $event->incident->priority?->value,
                    'status' => $event->incident->status?->value,
                ]
                : null,
            'acknowledged_at' => $event->acknowledged_at?->toIso8601String(),
            'acknowledged_by' => $event->acknowledgedBy
                ? ['id' => $event->acknowledgedBy->id, 'name' => $event->acknowledgedBy->name]
                : null,
            'dismissed_at' => $event->dismissed_at?->toIso8601String(),
            'dismissed_by' => $event->dismissedBy
                ? ['id' => $event->dismissedBy->id, 'name' => $event->dismissedBy->name]
                : null,
            'dismiss_reason' => $event->dismiss_reason?->value,
            'dismiss_reason_note' => $event->dismiss_reason_note,
            'can_promote' => $event->severity !== RecognitionSeverity::Critical
                && $event->incident_id === null
                && ($user?->can('view-fras-alerts') ?? false),
        ];
    }
}
