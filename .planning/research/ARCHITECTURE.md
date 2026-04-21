# Architecture Research — FRAS Integration into IRMS v2.0

**Domain:** Embedding HDSystem's Face Recognition Alert System (MQTT ingestion, camera management, personnel enrollment, recognition alerting) into the existing IRMS Laravel 12 + Vue 3 + Inertia v2 codebase.
**Researched:** 2026-04-21
**Confidence:** HIGH (both codebases inspected directly: `/Users/helderdene/IRMS` and `/Users/helderdene/fras`)

## Standard Architecture

### System Overview — FRAS layer bolted onto existing IRMS layers

```
┌────────────────────────────────────────────────────────────────────────────┐
│                 EXISTING IRMS INTAKE LAYER (v1.0, unchanged)               │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌────────────────┐   │
│  │ Phone   │  │  SMS    │  │  App    │  │  IoT    │  │  NEW: MQTT     │   │
│  │ intake  │  │ webhook │  │ citizen │  │ webhook │  │  recognition   │   │
│  │         │  │         │  │   SPA   │  │ (HMAC)  │  │  pipeline      │   │
│  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘  └───────┬────────┘   │
│       │            │            │            │               │            │
│       └────────────┴────────────┴────────────┴───────────────┘            │
│                              ↓                                             │
│              FrasIncidentFactory (shared Incident-creation seam)           │
├────────────────────────────────────────────────────────────────────────────┤
│                    EXISTING DISPATCH LAYER (v1.0, extended)                │
│  ┌───────────────────┐  ┌──────────────────┐  ┌────────────────────────┐  │
│  │ Dispatch Console  │  │   useDispatchFeed│  │ NEW: useFrasFeed       │  │
│  │ (MapLibre +       │  │   (incidents +   │  │   (cameras +           │  │
│  │  incidents/units) │  │    units)        │  │    recognition alerts) │  │
│  │  + NEW camera     │  │                  │  │                        │  │
│  │    marker layer   │  │                  │  │                        │  │
│  └──────────┬────────┘  └─────────┬────────┘  └───────────┬────────────┘  │
│             │                     │                       │               │
├─────────────┴─────────────────────┴───────────────────────┴───────────────┤
│                          LARAVEL REVERB (v1.0)                             │
│  dispatch.incidents │ dispatch.units │ NEW: fras.alerts │ NEW: fras.cameras│
│  incident.{id}      │ user.{id}      │ NEW: fras.enrollments              │
├────────────────────────────────────────────────────────────────────────────┤
│                          PROCESSES (long-running)                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐   │
│  │ php artisan │  │ php artisan │  │ php artisan │  │ NEW: php artisan│   │
│  │ serve       │  │ horizon     │  │ reverb:start│  │ fras:mqtt-listen│   │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────────┘   │
│    (Nginx/PHP-FPM prod)   (queues)   (WebSocket)   (Mosquitto subscriber) │
├────────────────────────────────────────────────────────────────────────────┤
│         PostgreSQL + PostGIS       │         Local storage disk            │
│  incidents, units, barangays, …    │  face crops, scene images             │
│  NEW: cameras, personnel,          │  recognition/{date}/{faces,scenes}    │
│       recognition_events,          │                                       │
│       camera_enrollments           │                                       │
└────────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities — new FRAS components mapped onto IRMS conventions

| Component | Responsibility | Implementation (IRMS convention match) |
|-----------|----------------|----------------------------------------|
| **MQTT Listener** | Long-running subscriber to Mosquitto; no business logic | NEW artisan command `App\Console\Commands\FrasMqttListenCommand` (mirrors FRAS's `fras:mqtt-listen`). Added as a 6th process in `composer run dev` and as a new Supervisor block in production |
| **TopicRouter** | Regex-dispatches MQTT topic → handler class | NEW `App\Mqtt\TopicRouter` (ported verbatim from `/Users/helderdene/fras/app/Mqtt/TopicRouter.php`) |
| **MQTT Handlers** | Parse payload, persist event, trigger side effects | NEW `App\Mqtt\Handlers\{RecognitionHandler, AckHandler, HeartbeatHandler, OnlineOfflineHandler}` — each implements `App\Mqtt\Contracts\MqttHandler` |
| **Recognition → Incident adapter** | Bridge MQTT recognition events to the existing IoT intake channel so they become Incidents | NEW `App\Services\FrasIncidentFactory` implementing `App\Contracts\FrasIncidentFactoryInterface` (follows existing IRMS Service + Contracts pattern used by `DirectionsServiceInterface`, `GeocodingServiceInterface`, etc.) |
| **Camera enrollment service** | Build MQTT EditPersonsNew payloads, cache ACK correlation keys, publish via MQTT publisher connection | NEW `App\Services\CameraEnrollmentService` (ported from FRAS with minimal changes) |
| **Photo processor** | Resize + compress personnel photos; compute MD5 hash | NEW `App\Services\FrasPhotoProcessor` (ported from FRAS's `PhotoProcessor`; renamed to avoid ambiguity with generic IRMS photo handling) |
| **FRAS Reverb events** | Broadcast recognition, camera-status, enrollment events on private channels | NEW `App\Events\{RecognitionAlertReceived, CameraStatusChanged, EnrollmentProgressed}` — mirrors existing `IncidentCreated` / `UnitStatusChanged` pattern (ShouldBroadcast + ShouldDispatchAfterCommit + broadcastWith) |
| **Camera / Personnel / Recognition Event models** | Eloquent models with PostGIS point on cameras, JSONB `raw_payload` on events | NEW `App\Models\{Camera, Personnel, RecognitionEvent, CameraEnrollment}`. Camera uses Magellan `Point` cast (same as `Unit`, `Incident`) |
| **FRAS admin controllers** | CRUD for cameras and personnel, alert acknowledge/dismiss | NEW `App\Http\Controllers\Admin\{AdminCameraController, AdminPersonnelController}` and `App\Http\Controllers\Fras\{AlertController, EventHistoryController, EnrollmentController}` — matches existing `Admin/AdminUnitController` and top-level `DispatchConsoleController` conventions |
| **Form Requests** | Validate camera + personnel payloads | NEW `App\Http\Requests\Admin\{StoreCameraRequest, UpdateCameraRequest, StorePersonnelRequest, UpdatePersonnelRequest}` — array-style rules per IRMS convention (see existing `app/Http/Requests/Settings/`) |
| **Dispatch map camera layer** | Render cameras as a MapLibre WebGL source/layer on the existing console | MOD `resources/js/composables/useDispatchMap.ts` — adds camera marker layer alongside incidents + units |
| **FRAS feed composable** | Subscribe to FRAS Reverb channels, merge state into dispatch UI | NEW `resources/js/composables/useFrasFeed.ts` — mirrors `useDispatchFeed.ts` signature and `useEcho` pattern |
| **Retention cleanup** | Delete face crops after 90d, scene images after 30d | NEW `App\Console\Commands\FrasCleanupRetentionCommand` — scheduled in `routes/console.php` via existing scheduler |
| **Enrollment timeout watchdog** | Expire stale EditPersonsNew cache keys, mark enrollments failed | NEW `App\Console\Commands\FrasCheckEnrollmentTimeoutsCommand` — scheduled every minute |
| **Offline camera watchdog** | Mark cameras offline when last_seen_at > 90s | NEW `App\Console\Commands\FrasCheckOfflineCamerasCommand` — scheduled every 30s |

## Recommended Project Structure — new + modified paths

```
app/
├── Console/Commands/
│   ├── FrasMqttListenCommand.php              [NEW] long-running MQTT subscriber
│   ├── FrasCleanupRetentionCommand.php        [NEW] scheduled retention deletes
│   ├── FrasCheckEnrollmentTimeoutsCommand.php [NEW] scheduled every minute
│   └── FrasCheckOfflineCamerasCommand.php     [NEW] scheduled every 30s
├── Contracts/
│   └── FrasIncidentFactoryInterface.php       [NEW] bridge contract (IoT intake)
├── Enums/
│   ├── AlertSeverity.php                      [NEW] critical | warning | info
│   ├── CameraStatus.php                       [NEW] online | offline | unknown
│   ├── EnrollmentStatus.php                   [NEW] pending | syncing | ok | failed | timeout
│   ├── PersonType.php                         [NEW] allow | block | guest
│   └── IncidentChannel.php                    (KEEP AS-IS — reuse existing IoT case; no new enum value)
├── Events/
│   ├── RecognitionAlertReceived.php           [NEW] fras.alerts private channel
│   ├── CameraStatusChanged.php                [NEW] fras.cameras private channel
│   └── EnrollmentProgressed.php               [NEW] fras.enrollments private channel
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── AdminCameraController.php      [NEW] cameras CRUD
│   │   │   └── AdminPersonnelController.php   [NEW] personnel CRUD + enrollment triggers
│   │   ├── Fras/
│   │   │   ├── AlertController.php            [NEW] alert feed, acknowledge/dismiss, image serving
│   │   │   ├── EventHistoryController.php     [NEW] searchable recognition log
│   │   │   └── EnrollmentController.php       [NEW] retry / resync endpoints
│   │   └── IoTWebhookController.php           [MOD] delegate Incident creation to FrasIncidentFactory
│   └── Requests/
│       └── Admin/
│           ├── StoreCameraRequest.php         [NEW]
│           ├── UpdateCameraRequest.php        [NEW]
│           ├── StorePersonnelRequest.php      [NEW]
│           └── UpdatePersonnelRequest.php     [NEW]
├── Jobs/
│   └── EnrollPersonnelBatch.php               [NEW] WithoutOverlapping('enrollment-camera-{id}')
├── Models/
│   ├── Camera.php                             [NEW] belongsTo Barangay; Magellan Point cast
│   ├── Personnel.php                          [NEW] hasMany CameraEnrollment, RecognitionEvent
│   ├── CameraEnrollment.php                   [NEW] pivot-ish: camera_id × personnel_id × status
│   ├── RecognitionEvent.php                   [NEW] belongsTo Camera, Personnel, nullable Incident
│   └── Incident.php                           [MOD] hasMany RecognitionEvent (nullable FK)
├── Mqtt/
│   ├── Contracts/
│   │   └── MqttHandler.php                    [NEW] handle(topic, message) interface
│   ├── Handlers/
│   │   ├── RecognitionHandler.php             [NEW] RecPush → RecognitionEvent → Incident adapter
│   │   ├── AckHandler.php                     [NEW] EditPersonsNew-Ack correlation
│   │   ├── HeartbeatHandler.php               [NEW] update cameras.last_seen_at
│   │   └── OnlineOfflineHandler.php           [NEW] explicit status transitions
│   └── TopicRouter.php                        [NEW] regex pattern → handler dispatch
├── Providers/
│   └── AppServiceProvider.php                 [MOD] bind FrasIncidentFactoryInterface → FrasIncidentFactory
└── Services/
    ├── CameraEnrollmentService.php            [NEW] build + publish EditPersonsNew / DeletePersons
    ├── FrasIncidentFactory.php                [NEW] recognition event → Incident (IoT channel)
    └── FrasPhotoProcessor.php                 [NEW] resize + compress + MD5

config/
├── fras.php                                   [NEW] port of fras/config/hds.php (renamed)
├── horizon.php                                [MOD] add 'fras-supervisor' block for fras queue
└── mqtt-client.php                            [NEW] two connections: default (subscriber) + publisher

database/
├── migrations/
│   ├── YYYY_MM_DD_create_cameras_table.php              [NEW] PostGIS point column
│   ├── YYYY_MM_DD_create_personnel_table.php            [NEW]
│   ├── YYYY_MM_DD_create_camera_enrollments_table.php   [NEW]
│   └── YYYY_MM_DD_create_recognition_events_table.php   [NEW] nullable incident_id FK
├── factories/                                            [NEW] Camera/Personnel/RecognitionEvent
└── seeders/                                              [NEW] dev seed cameras + personnel

resources/js/
├── composables/
│   ├── useFrasFeed.ts                         [NEW] subscribes fras.alerts + fras.cameras + fras.enrollments
│   └── useDispatchMap.ts                      [MOD] add camera WebGL source/layer
├── pages/
│   ├── admin/
│   │   ├── cameras/                           [NEW] Index.vue, Create.vue, Edit.vue, Show.vue
│   │   └── personnel/                         [NEW] Index.vue, Create.vue, Edit.vue, Show.vue
│   ├── fras/
│   │   ├── Alerts.vue                         [NEW] alert feed + acknowledge/dismiss
│   │   └── Events.vue                         [NEW] searchable event history
│   └── dispatch/
│       └── Console.vue                        [MOD] integrate useFrasFeed; add camera rail
└── types/
    └── fras.ts                                [NEW] TS types for alerts, cameras, enrollments

routes/
├── channels.php                               [MOD] add fras.alerts, fras.cameras, fras.enrollments
├── console.php                                [MOD] schedule 3 new watchdog/cleanup commands
└── web.php                                    [MOD] admin + fras route groups

tests/Feature/Fras/                            [NEW] mirrors tests/Feature/{Intake,Dispatch}
└── {RecognitionIngestionTest, CameraEnrollmentTest, AlertFeedTest, …}.php

docs/
└── IRMS-Specification.md                      [MOD] add FRAS section alongside the 5 layers
```

### Structure Rationale

- **`app/Mqtt/` as a sibling of `app/Http/` and `app/Services/`.** MQTT is a second ingress surface alongside HTTP. Placing it next to `Http/` (not inside `Services/`) mirrors how Laravel treats `Console/` — an ingress channel, not a domain service. This is exactly how FRAS structured it, and it slots into IRMS without polluting the existing service layer.
- **`app/Http/Controllers/Admin/`** already holds admin CRUD (`AdminUnitController`, `AdminBarangayController`, `AdminUserController`); cameras and personnel join it with matching naming.
- **`app/Http/Controllers/Fras/`** for operational (non-admin) routes (alerts, events, enrollment retry). Mirrors the existing top-level `DispatchConsoleController`/`ResponderController` style but scoped to the FRAS feature, since alerts are consumed by operator/dispatcher roles, not admin-only.
- **`config/fras.php`** renamed from `config/hds.php` because inside IRMS "HDS" is the vendor, not the feature — matching IRMS conventions (`config/services.php` for externals, feature-named configs otherwise).
- **`App\Contracts\FrasIncidentFactoryInterface`** enforces the rule that recognition events become Incidents via a documented seam; the IoT webhook and MQTT handler both depend on the abstraction. Mirrors the existing Contracts pattern (`DirectionsServiceInterface` → bound in `AppServiceProvider`).

## Architectural Patterns

### Pattern 1: IoT-intake adapter — recognition events become Incidents via a shared factory

**What:** Both `IoTWebhookController` (existing HMAC webhook) and `Mqtt\Handlers\RecognitionHandler` (new) call a single `FrasIncidentFactory` that creates Incidents and dispatches `IncidentCreated` exactly as the current IoT controller does. No "new channel" — recognition events are tagged `IncidentChannel::IoT` to satisfy the v2.0 constraint ("Recognition events ingested through existing IoT intake channel").

**When to use:** Every time an MQTT `RecPush` message with `AlertSeverity::Critical` (block-list) lands, or optionally `Warning` (refused). Info-level events stay in `recognition_events` without spawning an Incident.

**Trade-offs:**
- + Reuses the full intake → triage → dispatch pipeline (auto-priority, barangay lookup, Reverb broadcast) with zero new infrastructure
- + Keeps the `IncidentChannel` enum unchanged; no UI changes needed in existing intake station
- − Sensor-style IoT webhook and face-recognition events share the "IoT" label (fine — they really are the same intake category from CDRRMO's POV, per spec)
- − Factory must be idempotent: an ACK-retry MQTT message must not create a duplicate Incident (guard on `recognition_events.(camera_id, record_id)` uniqueness)

**Example — the factory extraction:**
```php
// NEW: app/Services/FrasIncidentFactory.php
class FrasIncidentFactory implements FrasIncidentFactoryInterface
{
    public function __construct(
        private BarangayLookupService $barangayLookup,
    ) {}

    public function createFromRecognition(RecognitionEvent $event): ?Incident
    {
        if (! $event->severity->shouldCreateIncident()) {
            return null; // info-level: log only, no Incident
        }

        if ($event->incident_id !== null) {
            return $event->incident; // idempotent guard
        }

        $camera = $event->camera;
        $incidentType = IncidentType::query()
            ->where('code', 'security_bolo_match')
            ->firstOrFail();

        $incident = Incident::query()->create([
            'incident_type_id' => $incidentType->id,
            'priority' => $event->severity->toIncidentPriority(),  // critical → P1
            'status' => IncidentStatus::Pending,
            'channel' => IncidentChannel::IoT,                     // existing channel, reused
            'location_text' => $camera->location_text,
            'notes' => "Recognition match: {$event->name_from_camera} "
                    . "({$event->severity->label()}) at {$camera->name}",
            'raw_message' => json_encode($event->raw_payload),
            'coordinates' => $camera->coordinates,                 // Magellan Point passthrough
            'barangay_id' => $camera->barangay_id,                 // cameras are pre-geocoded
        ]);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_created',
            'event_data' => [
                'source' => 'fras_recognition',       // distinguishes from 'iot_sensor'
                'camera_id' => $camera->id,
                'recognition_event_id' => $event->id,
                'severity' => $event->severity->value,
            ],
        ]);

        $event->update(['incident_id' => $incident->id]);

        $incident->load('incidentType', 'barangay');
        IncidentCreated::dispatch($incident);  // EXISTING Reverb event — dispatch console lights up

        return $incident;
    }
}
```

Then `IoTWebhookController::__invoke()` is refactored to delegate its body to `$factory->createFromSensor($validated)`, guaranteeing one intake → Incident code path.

### Pattern 2: MQTT handler + service layer (no business logic in handlers)

**What:** Handlers do three things only: (1) parse the payload, (2) persist the raw event, (3) delegate to a service or factory. This keeps handlers diff-free against firmware quirks (per FRAS Appendix C) and puts all IRMS-side logic behind `Services/`.

**When to use:** Always. Never let handlers broadcast Reverb events directly or call `Incident::create` inline.

**Trade-offs:**
- + Testable: handlers get unit-tested with stubbed services; services get feature-tested end-to-end
- + Matches existing IRMS service layer (`BarangayLookupService`, `ProximityRankingService`, `PrioritySuggestionService`)
- − One extra indirection per message (negligible at MQTT volumes of ≤8 cameras)

**Example — handler shape:**
```php
// NEW: app/Mqtt/Handlers/RecognitionHandler.php
class RecognitionHandler implements MqttHandler
{
    public function __construct(
        private FrasIncidentFactoryInterface $incidentFactory,
    ) {}

    public function handle(string $topic, string $message): void
    {
        $data = json_decode($message, true);
        if (! $data || ($data['operator'] ?? null) !== 'RecPush') { return; }

        // ... parse deviceId, lookup Camera, parsePayload, saveImage ...
        $event = RecognitionEvent::query()->create([/* persisted with severity */]);

        if ($event->severity->shouldBroadcast() && $parsed['is_real_time']) {
            RecognitionAlertReceived::dispatch($event);           // Reverb to operators
            $this->incidentFactory->createFromRecognition($event); // IoT adapter → IRMS Incident
        }
    }
}
```

### Pattern 3: Reverb channel naming — `fras.*` as a new namespace alongside `dispatch.*`

**What:** Add three new private channels (`fras.alerts`, `fras.cameras`, `fras.enrollments`) instead of reusing `dispatch.incidents`. Incidents created by recognition events still broadcast on `dispatch.incidents` (via `IncidentCreated` — Pattern 1), so dispatchers see them in the existing feed. But the *raw alert feed* (including info-level, non-Incident recognitions for situational awareness) rides its own channel.

**When to use:** Any time a FRAS-specific consumer (alert rail, camera map layer, enrollment progress bar) needs a stream that doesn't warrant polluting the dispatch channels.

**Trade-offs:**
- + Mirrors the existing `dispatch.*` / `incident.{id}.*` namespacing convention
- + Role-based auth on `fras.*` can differ from `dispatch.*` (e.g., `fras.enrollments` may be admin-only)
- − Frontend subscribes to more channels (5 → 8) — but `useEcho` handles that fine
- − Must add 3 new `Broadcast::channel()` callbacks in `routes/channels.php`

**Example — channel registration:**
```php
// MOD: routes/channels.php
$frasOperatorRoles = [UserRole::Operator, UserRole::Dispatcher,
                      UserRole::Supervisor, UserRole::Admin];
$frasAdminRoles    = [UserRole::Supervisor, UserRole::Admin];

Broadcast::channel('fras.alerts', fn (User $u): bool =>
    in_array($u->role, $frasOperatorRoles));

Broadcast::channel('fras.cameras', fn (User $u): bool =>
    in_array($u->role, $frasOperatorRoles));

Broadcast::channel('fras.enrollments', fn (User $u): bool =>
    in_array($u->role, $frasAdminRoles));
```

### Pattern 4: Composable hub mirroring `useDispatchFeed`

**What:** `useFrasFeed.ts` subscribes to the three `fras.*` channels, merges payloads into local reactive state, and exposes `cameras`, `recentAlerts`, `unreadCriticalCount` computed refs to `Console.vue`. Uses the existing `@laravel/echo-vue` `useEcho()` helper that `useDispatchFeed` already uses.

**When to use:** On any page that renders camera status or recognition alerts (dispatch console, dedicated FRAS alerts page, dashboard widget).

**Trade-offs:**
- + Code style identical to `useDispatchFeed` (same file shape, same `useEcho` pattern, same local-copy-of-Inertia-props technique)
- + No Pinia needed (per IRMS v1.0 decision: "Pinia out of scope")
- − One more composable to keep in lock-step with its server payload shape; mitigate with `types/fras.ts` sharing

**Example — composable signature:**
```ts
// NEW: resources/js/composables/useFrasFeed.ts
import { useEcho } from '@laravel/echo-vue';
import { ref, computed, type Ref } from 'vue';
import type { Camera, RecognitionAlertPayload, EnrollmentProgress } from '@/types/fras';

export function useFrasFeed(
    localCameras: Ref<Camera[]>,
    currentUserId: number,
) {
    const recentAlerts = ref<RecognitionAlertPayload[]>([]);
    const enrollmentProgress = ref<Record<number, EnrollmentProgress>>({});

    useEcho('fras.alerts', 'RecognitionAlertReceived', (p: RecognitionAlertPayload) => {
        recentAlerts.value = [p, ...recentAlerts.value].slice(0, 50);
    });

    useEcho('fras.cameras', 'CameraStatusChanged', (p) => {
        const c = localCameras.value.find(x => x.id === p.id);
        if (c) { c.status = p.status; c.last_seen_at = p.last_seen_at; }
    });

    useEcho('fras.enrollments', 'EnrollmentProgressed', (p: EnrollmentProgress) => {
        enrollmentProgress.value[p.camera_id] = p;
    });

    const unreadCriticalCount = computed(() =>
        recentAlerts.value.filter(a => a.severity === 'critical' && !a.acknowledged).length);

    return { recentAlerts, enrollmentProgress, unreadCriticalCount };
}
```

### Pattern 5: MapLibre camera layer reusing the existing dispatch map

**What:** The dispatch console already has a MapLibre instance with WebGL sources for incidents and units (`useDispatchMap.ts`). Cameras become a 4th layer: a `GeoJSON` source whose features are built from the Inertia-prop-backed `localCameras` reactive array. `useFrasFeed` updates the source on `CameraStatusChanged` by calling `mapRef.updateCameraStatus(id, status)`.

**When to use:** Always — v2.0 spec explicitly mandates "cameras rendered as a layer on the dispatch MapLibre map."

**Trade-offs:**
- + Zero new map libraries; no separate Mapbox instance. FRAS uses Mapbox; IRMS uses MapLibre — one wins, IRMS wins since the dispatch console is the target surface
- + Camera markers use the same WebGL layer pattern as units (no HTML overlays — IRMS rule)
- − Camera icon styling must be added to the existing sprite sheet / symbol layer config
- − Mapbox-specific FRAS styles (HelderDene custom dark/light) don't carry over; IRMS already has its own Mapbox style (see commit `ea52f22 feat(dispatch): switch dark-mode map to custom Mapbox style`) — reuse it

### Pattern 6: WithoutOverlapping per-camera + Horizon queue isolation

**What:** `EnrollPersonnelBatch` job uses `WithoutOverlapping('enrollment-camera-'.$camera->id)` so only one batch flies per camera at a time (mandated by camera firmware: one batch in-flight). A new Horizon supervisor block routes `fras` queue jobs separately from the default queue, so a flood of enrollments doesn't starve dispatch-related jobs.

**When to use:** All FRAS-originated jobs (`EnrollPersonnelBatch`, future photo-reprocessing jobs) dispatch to `->onQueue('fras')`.

**Trade-offs:**
- + Isolation: dispatch ops (incident broadcasts, notification webhooks) never blocked behind 200-personnel enrollment batches
- + Horizon dashboard shows `fras` queue metrics separately
- − One extra supervisor block in `config/horizon.php`
- − Must document the queue name convention in `CLAUDE.md` FRAS section

## Data Flow

### Request Flow — recognition event end to end

```
 Camera device (MQTT publish)
   topic: mqtt/face/{device_id}/Rec
   payload: RecPush JSON
        │
        ▼
 Mosquitto broker
        │
        ▼
 php artisan fras:mqtt-listen  (long-running, Supervisor-managed in prod)
        │
        ▼ MQTT::connection()->subscribe callback
 App\Mqtt\TopicRouter::dispatch($topic, $message)
        │ regex match: #mqtt/face/[^/]+/Rec$#
        ▼
 App\Mqtt\Handlers\RecognitionHandler::handle()
        │ 1. json_decode, validate operator=RecPush
        │ 2. Camera::where('device_id', $deviceId)->first()
        │ 3. parsePayload() — handles firmware quirks
        │ 4. RecognitionEvent::create([...])  — persisted with severity
        │ 5. saveImage() — Storage::disk('local')->put() under recognition/{date}/…
        ▼
 If event->severity->shouldBroadcast() && is_real_time:
        │
        ├──► RecognitionAlertReceived::dispatch($event)
        │      ▼
        │    Reverb → private-fras.alerts → useFrasFeed (dispatch console + alerts page)
        │
        └──► FrasIncidentFactoryInterface::createFromRecognition($event)
               │ if severity->shouldCreateIncident() (critical / block-list):
               ▼
             Incident::create([
               channel: IncidentChannel::IoT,               // ← existing IoT channel reused
               coordinates: $camera->coordinates,           // ← from cameras table
               barangay_id: $camera->barangay_id,           // ← pre-geocoded
               ...
             ])
               ▼
             IncidentCreated::dispatch($incident)           // ← EXISTING Reverb event
               ▼
             Reverb → private-dispatch.incidents → useDispatchFeed
               ▼
             Dispatcher sees incident on map + intake queue, triages normally
```

### Request Flow — enrollment (personnel push to cameras)

```
 Admin creates Personnel (AdminPersonnelController::store)
        │
        ▼
 CameraEnrollmentService::enrollPersonnel($personnel)
        │ for each Camera:
        │   - CameraEnrollment::updateOrCreate(status=pending)
        │   - if camera->is_online:
        │       EnrollPersonnelBatch::dispatch($camera, [$personnel->id])
        │         ->onQueue('fras')
        ▼
 Horizon (fras queue worker)
        │
        ▼
 EnrollPersonnelBatch::handle()
   uses middleware: WithoutOverlapping('enrollment-camera-'.$id)
        │
        ▼
 CameraEnrollmentService::upsertBatch()
   - build EditPersonsNew payload
   - Cache::put("enrollment-ack:{camera_id}:{messageId}", ...)
   - MQTT::connection('publisher')->publish(topic, payload)
        │
        ▼
 Camera device receives, processes, publishes Ack topic
        │
        ▼
 fras:mqtt-listen → TopicRouter → AckHandler
   - Cache::pull the correlation key
   - CameraEnrollment updated to STATUS_OK or STATUS_FAILED
   - EnrollmentProgressed::dispatch(...)
        ▼
 Reverb → private-fras.enrollments → admin UI progress indicator
```

### State Management

```
 PostgreSQL (source of truth)
   cameras, personnel, recognition_events, camera_enrollments
        ▲
        │ CRUD via Form Requests + Eloquent
        │
 Inertia controllers (Admin\AdminCameraController, Fras\AlertController, ...)
        │ Inertia::render('admin/cameras/Index', ['cameras' => ...])
        ▼
 Vue page props (initial state)
        │
        │ ref() wrap for reactivity (IRMS convention — see localIncidents/localUnits)
        ▼
 Composable (useFrasFeed) subscribes to Reverb
        │ mutations: push to recentAlerts, update localCameras[i].status
        ▼
 Vue components re-render via Vue reactivity
```

### Key Data Flows

1. **MQTT → Recognition Event → Incident (Pattern 1 adapter):** Only critical (block-list) matches create Incidents; warning/info go to `recognition_events` + `fras.alerts` only. This prevents dispatcher channel flood from info-level recognitions.
2. **Personnel CRUD → per-camera enrollment fan-out:** One DB write triggers N queue jobs (one per online camera). Offline cameras get `pending` rows that are picked up when they come online via the `OnlineOfflineHandler`.
3. **Heartbeat → camera status → map refresh:** Every heartbeat updates `cameras.last_seen_at`. `FrasCheckOfflineCamerasCommand` (scheduled every 30s) flips stale cameras to offline and dispatches `CameraStatusChanged` so the map layer visually updates.
4. **Retention cleanup:** Scheduled command scans `recognition_events.face_image_path` for rows older than 90 days and deletes files (rows are kept indefinitely — spec requirement). Scene images deleted after 30 days the same way.
5. **Image serving:** Face crops served via authenticated controller routes (`alerts/{event}/face`, `alerts/{event}/scene`) — files live on `Storage::disk('local')`, not `public`, to enforce role auth.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 1–8 cameras, ≤200 personnel (v2.0 target, matches FRAS v1.0) | Single `fras:mqtt-listen` process, single Horizon worker on `fras` queue, local disk storage. No changes needed. |
| 20–50 cameras, 500–2000 personnel | Still a single listener process (MQTT subscriber is I/O-bound, not CPU-bound). Add a second Horizon supervisor with `minProcesses=2` on `fras` queue for enrollment parallelism (still bounded by `WithoutOverlapping` per camera). Move storage to S3-compatible object store (DigitalOcean Spaces) behind a signed-URL controller. |
| 100+ cameras, 10k+ personnel | Shard the MQTT listener by topic (per `topic_prefix` or per camera group) as separate `fras:mqtt-listen --group=X` commands. Split `recognition_events` into time-partitioned tables (Postgres native partitioning). Consider moving MQTT handlers themselves to queued jobs (Recognition topic → handler persists minimal row → `HandleRecognitionJob::dispatch()`) to decouple MQTT throughput from DB write latency. |

### Scaling Priorities

1. **First bottleneck at scale:** MQTT listener becomes a single point of failure (crashes = no ingestion). **Fix:** Supervisor `autorestart=true` + a health-check endpoint that the watchdog pings (`php artisan fras:mqtt-health` that checks `last_mqtt_message_at` cache key set by any handler).
2. **Second bottleneck:** `recognition_events` table growth — a busy facility can produce thousands of rows/day. **Fix:** Index on `(camera_id, captured_at DESC)`, consider Postgres partitioning by month once table hits ~50M rows. Image file growth is already capped by retention commands.
3. **Third bottleneck:** Storage disk space on single-server deploy. **Fix:** Move to DigitalOcean Spaces (S3-compatible) — `Storage::disk('spaces')` — and tighten retention.

## Anti-Patterns

### Anti-Pattern 1: Putting the MQTT loop inside a queue worker

**What people do:** "Laravel has queues, let's have a job subscribe to MQTT and re-queue itself." They try to fit the long-running subscriber into the Horizon worker model.
**Why it's wrong:** Queue workers are designed to process-and-exit or process-in-loop with short-lived jobs. An MQTT loop blocks the worker indefinitely. Memory leaks, orphaned connections, and worker restarts breaking the subscription follow.
**Do this instead:** Use `php artisan fras:mqtt-listen` as its own Supervisor-managed process (exactly how FRAS does it). Keep Horizon for *downstream* jobs like `EnrollPersonnelBatch`.

### Anti-Pattern 2: Broadcasting Reverb events from inside MQTT handlers without ShouldDispatchAfterCommit

**What people do:** `event(new RecognitionAlertReceived($event))` fires immediately, before the DB transaction holding the `RecognitionEvent` insert has committed. Subscribers on the frontend receive the event ID, try to fetch details, and get 404.
**Why it's wrong:** Classic race — MQTT handlers do heavy DB work; Reverb is faster than Postgres commit.
**Do this instead:** Always mark FRAS events `implements ShouldDispatchAfterCommit` (matches existing `IncidentCreated` in IRMS). Safe and boring.

### Anti-Pattern 3: Creating a new IncidentChannel for recognition events

**What people do:** "FRAS recognitions aren't really IoT — let's add `IncidentChannel::Recognition`." They think of the new channel as the clean abstraction.
**Why it's wrong:** The v2.0 spec explicitly mandates reuse of the existing IoT intake channel. A new enum value forces updates to every intake filter, channel count badge, analytics breakdown, and report generator — for no operational benefit to CDRRMO (the dispatcher's workflow is identical).
**Do this instead:** Tag recognition-created Incidents as `IncidentChannel::IoT` and distinguish them in `IncidentTimeline.event_data.source = 'fras_recognition'` (same pattern already used for sensor events: `source = 'iot_sensor'`). The timeline entry is the identifier; the channel stays IoT.

### Anti-Pattern 4: Building the camera map layer with HTML marker overlays

**What people do:** They add `<div class="camera-marker">` children to the MapLibre container because it's familiar.
**Why it's wrong:** IRMS v1.0 established WebGL-only markers as a performance rule (Constraints section of PROJECT.md — "all markers as WebGL layers, no HTML overlays"). HTML overlays tank performance past ~50 markers and break map interaction.
**Do this instead:** Add a `GeoJSON` source + `symbol` layer to `useDispatchMap.ts` for cameras, using a sprite-sheet icon. Same pattern as incidents + units.

### Anti-Pattern 5: Extending UserRole with FRAS-specific roles

**What people do:** Add `UserRole::FrasOperator`, `UserRole::CameraAdmin`, etc.
**Why it's wrong:** IRMS v1.0 settled on 5 roles and 9 gates; adding role variants explodes the gate matrix. Per-feature roles signal missing authorization primitives, not missing roles.
**Do this instead:** Reuse existing roles. Map responsibilities like this:
- Alert feed + camera map view → `operator, dispatcher, supervisor, admin`
- Camera CRUD → `supervisor, admin`
- Personnel CRUD → `supervisor, admin`
- Enrollment retry/resync → `admin` only

Add specific Gates (`view-fras-alerts`, `manage-cameras`, `manage-personnel`, `trigger-enrollment-retry`) in `AppServiceProvider` rather than new roles. This mirrors how v1.0 Phase 8 added the `operator` role with targeted gates, not a new role taxonomy.

### Anti-Pattern 6: Publishing Reverb broadcasts *and* writing to DB inside the same MQTT handler call

**What people do:** The handler does DB work, then dispatches the Reverb event, then continues to persist images — a long path inside a single subscriber callback.
**Why it's wrong:** MQTT loop throughput is bounded by the slowest handler. If the subscriber blocks for 500ms per event on image writes, burst rates drop below camera output.
**Do this instead (if throughput becomes an issue at scale):** Handler persists a minimal `recognition_events` row (no image decoding), dispatches `ProcessRecognitionEventJob::dispatch($event->id)` to the `fras` queue; the job decodes images, runs the IoT adapter, and fires Reverb events. At v2.0 target volume (≤8 cameras, low event rate) the inline path is fine — note as a Pattern 6 flag rather than a required refactor.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Mosquitto MQTT broker | `php-mqtt/laravel-client` — two connections configured (`default` for subscriber, `publisher` for ACK-less publishes). Config: `config/mqtt-client.php` | Plain MQTT v3.1.1, QoS 0, internal subnet only. TLS deferred (matches FRAS v1.0 out-of-scope). Keepalive 30s, reconnect 5s, max attempts 10. |
| AI IP cameras | MQTT publish/subscribe over broker; photos fetched via HTTP URL from Laravel | Firmware quirks handled in `RecognitionHandler::parsePayload()` — `personName` vs `persionName`, string-numeric fields, missing `scene` field (all per FRAS spec Appendix C). Cameras must reach the Laravel HTTP server for photo download (`picURI` must be network-reachable). |
| Mapbox (maps + geocoding) | Already integrated for IRMS dispatch console | FRAS's separate Mapbox styles are not ported; IRMS's existing style and `MapboxDirectionsService` stay. Cameras use Mapbox reverse-geocoding on create (if lat/lng entered without barangay, fall back to PostGIS `BarangayLookupService`). |
| Laravel Reverb | Existing installation + 6 events | Add 3 events, 3 channels. Same Pusher-protocol adapter. |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| MQTT handler ↔ FrasIncidentFactory | Direct PHP call via DI container | Factory is bound as singleton in `AppServiceProvider` → matches existing IRMS service-binding convention |
| FrasIncidentFactory ↔ existing Incident domain | Reuses `Incident::create`, `IncidentTimeline::create`, `IncidentCreated` event | One-way — FRAS writes into IRMS; IRMS does not read FRAS tables (until later integrations like incident detail page showing recognition thumbnails) |
| Incident ↔ RecognitionEvent | `recognition_events.incident_id` nullable FK | Most recognitions won't create Incidents; FK is nullable; `Incident::recognitionEvents()` relation added but non-critical |
| Dispatch console Vue page ↔ useFrasFeed | Vue composable + Inertia props | `Console.vue` receives `cameras` in Inertia prop alongside existing `incidents`/`units`, wraps in `localCameras = ref(props.cameras)`, passes to `useFrasFeed` |
| Admin camera CRUD ↔ Reverb (on status change) | Controller fires `CameraStatusChanged` directly after status update (not via observer) | Matches existing `DispatchConsoleController` pattern of explicit event dispatch |
| Personnel photo storage ↔ camera HTTP fetch | Signed URL (short TTL) generated by `Personnel::photo_url` accessor | IRMS constraint: public-readable photos are OK for the camera subnet. Use signed URLs (not public disk) to add at least a modest barrier. |
| Scheduler ↔ FRAS watchdogs | `routes/console.php` adds three scheduled commands | `fras:cleanup-retention` daily, `fras:check-enrollment-timeouts` every minute, `fras:check-offline-cameras` every 30 seconds |

## Process Orchestration — dev and production

### Dev — extend `composer run dev`

The current script runs 5 concurrent processes (server, reverb, horizon, logs, vite). Add a 6th:

```bash
# MOD: composer.json "dev" script
npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#f472b6" \
  "php artisan serve" \
  "php artisan reverb:start" \
  "php artisan horizon" \
  "php artisan pail --timeout=0" \
  "npm run dev" \
  "php artisan fras:mqtt-listen" \
  --names=server,reverb,horizon,logs,vite,mqtt --kill-others
```

Developers with no local Mosquitto can set `MQTT_HOST=skip` and have the listener exit cleanly with `return self::SUCCESS` when that sentinel is set — small addition to `FrasMqttListenCommand::handle()`.

### Production — Supervisor block

Add to production Supervisor config (alongside existing horizon + reverb blocks):

```ini
[program:fras-mqtt-listener]
process_name=%(program_name)s
command=php /var/www/irms/artisan fras:mqtt-listen
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/fras-mqtt-listener.log
stopwaitsecs=10
```

Horizon `config/horizon.php` adds a supervisor block for the `fras` queue:

```php
'fras-supervisor' => [
    'connection' => 'redis',
    'queue' => ['fras'],
    'balance' => 'simple',
    'minProcesses' => 1,
    'maxProcesses' => 3,
    'tries' => 3,
    'timeout' => 120,
],
```

## Suggested Build Order

Build in dependency order. The first group is strictly sequential; later groups can parallelize in two tracks.

### Sequential foundation (must finish before anything else)

1. **Laravel 12 → 13 upgrade** — first because every subsequent composer require may pin against Laravel 13. Verify all v1.0 Pest tests still pass. (Pattern: install, run tests, update deprecations, run tests again; document breaking changes touched in `CLAUDE.md`.)
2. **Package installs** — `php-mqtt/laravel-client`, `intervention/image` v3 — add composer requires, publish configs, write smoke tests for package bindings.
3. **Config + Enums** — port `config/fras.php`, add `AlertSeverity`, `CameraStatus`, `EnrollmentStatus`, `PersonType` enums. These are leaves in the dependency graph; land early.
4. **Migrations + Models + Factories** — cameras (PostGIS Point + barangay_id FK), personnel, camera_enrollments, recognition_events (nullable incident_id FK). Seeders for dev. Pest unit tests on model relationships.
5. **MQTT listener skeleton** — `FrasMqttListenCommand`, `TopicRouter`, `MqttHandler` contract, minimal `HeartbeatHandler` only. Verify end-to-end MQTT → DB with Mosquitto test client. Update `composer run dev`. No broadcasts yet.

### Track A (after step 5) — Ingestion + Alerts

6. **RecognitionHandler + image storage** — parse payload, save images to `Storage::disk('local')` under date-partitioned paths. No broadcast, no Incident creation yet.
7. **`fras.alerts` Reverb channel + RecognitionAlertReceived event** — broadcast after commit. Frontend: alerts page only (Console integration waits for track B).
8. **FrasIncidentFactory + IoT-intake adapter (Pattern 1)** — the critical bridge. Factor shared Incident creation out of `IoTWebhookController` into `FrasIncidentFactory::createFromSensor` + `createFromRecognition`. Test: a block-list recognition creates an Incident visible to `useDispatchFeed` with `channel=iot`.
9. **Alert acknowledge/dismiss + event history page** — admin UI + Fras controllers for alerts and history.
10. **Retention cleanup command + scheduling** — `FrasCleanupRetentionCommand`.

### Track B (can start after step 5, in parallel with track A) — Camera + Personnel Management

6b. **Camera CRUD (Admin)** — `AdminCameraController`, Form Requests, migrations, `cameras/{Index,Create,Edit,Show}.vue`. Validates map + auth integration early.
7b. **Camera liveness (HeartbeatHandler + OnlineOfflineHandler + `FrasCheckOfflineCamerasCommand`)** — camera status management without enrollment.
8b. **`fras.cameras` Reverb channel + CameraStatusChanged event**.
9b. **Personnel CRUD (Admin)** — photo processor service, photo validation, per-person CRUD. No enrollment yet.

### Track A/B merge point — Enrollment

11. **CameraEnrollmentService + EnrollPersonnelBatch job + AckHandler** — the enrollment pipeline. Requires cameras, personnel, and MQTT publisher connection. Use `WithoutOverlapping` middleware.
12. **Enrollment progress broadcast** — `fras.enrollments` channel + `EnrollmentProgressed` event.
13. **FrasCheckEnrollmentTimeoutsCommand** — scheduled timeout sweeper.
14. **Horizon `fras` supervisor block**.

### Integration Phase — Dispatch Console

15. **useFrasFeed composable** — the frontend hub; mirrors `useDispatchFeed`.
16. **useDispatchMap.ts camera layer** — WebGL source + symbol layer for cameras; status-driven styling (online/offline/alert pulse).
17. **Console.vue integration** — wire Inertia `cameras` prop, `localCameras` ref, pass to useFrasFeed, render camera rail and map layer.

### Polish Phase

18. **Gates + routing cleanup** — add `view-fras-alerts`, `manage-cameras`, `manage-personnel`, `trigger-enrollment-retry` Gates. Audit route groups in `web.php`.
19. **Navigation + menu items** — sidebar entries for admin/cameras, admin/personnel, fras/alerts, fras/events.
20. **Supervisor config docs** — production deployment notes in `docs/`.
21. **Requirements trace + Pest convention guards** — ensure all v2.0 features have tests; add convention guards if new (e.g., "all MQTT handlers must implement `MqttHandler` contract" via a reflection test).
22. **`docs/IRMS-Specification.md` update** — add FRAS section.

**Parallelization heuristic:** Track A and Track B are orthogonal until step 11 because ingestion (alerts) and management (cameras/personnel) touch different tables and controllers. Two developers — or two agent loops — can work concurrently. The bridge is the `FrasIncidentFactory` (step 8, Track A) which must land before any real recognition events create Incidents; but cameras + personnel management (Track B through step 9b) needs no coordination with Track A.

## Risk Flags for Roadmap

| Flag | Why it matters | Mitigation |
|------|----------------|------------|
| **Laravel 12 → 13 upgrade regressions** | 16 phases of v1.0 functionality must keep passing. Reverb, Horizon, Magellan, Fortify, Wayfinder, Inertia v2 all have major-version Laravel sensitivities. | Dedicate a phase to the upgrade; run full Pest suite pre- and post-upgrade; mark regressions as the first thing to fix before any FRAS code lands. |
| **MySQL → Postgres schema port** | FRAS used MySQL JSON columns and MySQL-specific types; IRMS uses Postgres + PostGIS. | Port schema carefully: JSON → JSONB, MySQL enums → Postgres check constraints or proper Laravel enum casts, DATETIME → TIMESTAMP. The `recognition_events.raw_payload` must become `JSONB`, not `JSON`. |
| **Mapbox vs MapLibre split** | FRAS uses Mapbox GL JS; IRMS uses MapLibre. Camera markers must use the IRMS map. | Drop the FRAS Mapbox integration entirely. Use the IRMS MapLibre instance; port only the camera marker styling to the MapLibre sprite sheet. |
| **MQTT listener reliability** | Single process; if it crashes ingestion halts. | Supervisor `autorestart`, health-check cache key, and a dispatcher-visible "FRAS ingestion healthy" indicator in the dispatch console status bar. |
| **Idempotency of recognition → Incident** | A duplicate RecPush or ACK replay must not create duplicate Incidents. | Unique index on `recognition_events.(camera_id, record_id)`; factory checks for existing `incident_id` before creating. |
| **Photo URL reachability from camera subnet** | Cameras must HTTP-GET photo URLs; signed URLs with short TTL can fail if clocks drift or TTL is too short. | Make photo URL TTL configurable in `config/fras.php`; test end-to-end with actual hardware early (when personnel management lands). |

## Sources

Direct code inspection of both repos as primary source:

- `/Users/helderdene/IRMS/app/Http/Controllers/IoTWebhookController.php` — existing IoT intake pattern the adapter extracts from
- `/Users/helderdene/IRMS/app/Events/IncidentCreated.php` — existing broadcast event pattern (ShouldDispatchAfterCommit + broadcastWith)
- `/Users/helderdene/IRMS/routes/channels.php` — existing private channel auth pattern (`dispatch.incidents`, `incident.{id}`, `user.{id}`)
- `/Users/helderdene/IRMS/routes/web.php` — existing route-group-by-role pattern
- `/Users/helderdene/IRMS/resources/js/composables/useDispatchFeed.ts` — composable hub pattern to mirror in `useFrasFeed.ts`
- `/Users/helderdene/IRMS/composer.json` — existing `dev` script with 5 processes; add 6th for MQTT
- `/Users/helderdene/IRMS/app/Enums/{UserRole,IncidentChannel}.php` — existing roles and channels to reuse (5 roles, 5 channels)
- `/Users/helderdene/IRMS/app/Services/BarangayLookupService.php` and siblings — existing service-layer + Contracts binding convention
- `/Users/helderdene/fras/app/Mqtt/{TopicRouter.php,Handlers/*.php,Contracts/MqttHandler.php}` — to be ported with handler enhancements
- `/Users/helderdene/fras/app/Console/Commands/FrasMqttListenCommand.php` — long-running subscriber template
- `/Users/helderdene/fras/app/Jobs/EnrollPersonnelBatch.php` — WithoutOverlapping middleware pattern
- `/Users/helderdene/fras/app/Services/CameraEnrollmentService.php` — enrollment service ported
- `/Users/helderdene/fras/config/hds.php` — config file renamed `config/fras.php` in IRMS
- `/Users/helderdene/IRMS/.planning/PROJECT.md` — v2.0 target features and constraints (canonical)
- `/Users/helderdene/fras/.planning/PROJECT.md` — FRAS v1.0 validated requirements (canonical)

---
*Architecture research for: FRAS integration into IRMS v2.0*
*Researched: 2026-04-21*
