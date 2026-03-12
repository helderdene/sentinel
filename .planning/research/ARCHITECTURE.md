# Architecture Research

**Domain:** Emergency Incident Response Management System (CAD-style dispatch platform)
**Researched:** 2026-03-12
**Confidence:** HIGH

## Standard Architecture

### System Overview

The IRMS is a Computer-Aided Dispatch (CAD) system layered onto an existing Laravel 12 + Vue 3 + Inertia v2 monolith. The architecture preserves the Inertia page-driven model for standard CRUD flows (intake forms, admin, analytics) while introducing a parallel real-time subsystem (Reverb WebSocket + MapLibre GL JS) for the dispatch console and responder tracking. This dual-mode approach -- Inertia for page navigation, WebSocket for live state -- is the key architectural decision.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          CLIENT TIER                                    │
│                                                                         │
│  ┌─────────────────┐  ┌──────────────┐  ┌────────────────────────────┐ │
│  │  Dispatch        │  │  Intake UI   │  │  Responder Mobile Web      │ │
│  │  Console         │  │  (Inertia)   │  │  (Inertia + Echo)          │ │
│  │  (Inertia +      │  └──────┬───────┘  └────────────┬───────────────┘ │
│  │   Echo + MapLibre)│         │                       │                 │
│  └────────┬─────────┘         │                       │                 │
│           │                   │                       │                 │
│    WebSocket + HTTP      HTTP only            WebSocket + HTTP          │
└───────────┼───────────────────┼───────────────────────┼─────────────────┘
            │                   │                       │
┌───────────┼───────────────────┼───────────────────────┼─────────────────┐
│           │          APPLICATION TIER                  │                 │
│  ┌────────▼───────────────────▼───────────────────────▼──────────────┐  │
│  │                   Laravel 12 Application                          │  │
│  │                                                                   │  │
│  │  ┌───────────────┐  ┌──────────────┐  ┌────────────────────────┐ │  │
│  │  │ Inertia       │  │ REST API     │  │ Laravel Reverb         │ │  │
│  │  │ Controllers   │  │ Controllers  │  │ (WebSocket Server)     │ │  │
│  │  │ (pages)       │  │ (webhooks,   │  │ Channels:              │ │  │
│  │  │               │  │  GPS, mobile)│  │  incidents, units,     │ │  │
│  │  └───────┬───────┘  └──────┬───────┘  │  units.{id}.private    │ │  │
│  │          │                  │          └────────────┬───────────┘ │  │
│  │          │                  │                       │             │  │
│  │  ┌───────▼──────────────────▼───────────────────────▼──────────┐  │  │
│  │  │                   Domain Layer                              │  │  │
│  │  │  Actions (single-task business logic)                      │  │  │
│  │  │  Services (cross-cutting: Geocoding, Priority, ETA)        │  │  │
│  │  │  Events + Listeners (incident lifecycle, broadcasts)       │  │  │
│  │  └───────┬────────────────────────────────────────────────────┘  │  │
│  │          │                                                       │  │
│  │  ┌───────▼────────────────────────────────────────────────────┐  │  │
│  │  │  Infrastructure Layer                                      │  │  │
│  │  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌───────────┐ │  │  │
│  │  │  │ Queue    │  │ Scheduler│  │ Broadcast│  │ External  │ │  │  │
│  │  │  │ (Redis + │  │ (CRON)   │  │ (Reverb) │  │ API       │ │  │  │
│  │  │  │ Horizon) │  │          │  │          │  │ Connectors│ │  │  │
│  │  │  └──────────┘  └──────────┘  └──────────┘  └───────────┘ │  │  │
│  │  └────────────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────────────┘
            │
┌───────────▼──────────────────────────────────────────────────────────┐
│                          DATA TIER                                    │
│  ┌─────────────────────────┐   ┌──────────────────────────────────┐  │
│  │  PostgreSQL + PostGIS   │   │  Redis                           │  │
│  │  incidents, units,      │   │  Queue backend (Horizon)         │  │
│  │  barangays (GIST idx),  │   │  Cache (session, config)         │  │
│  │  timeline, messages,    │   │  Reverb pub/sub channel broker   │  │
│  │  users (roles)          │   │                                  │  │
│  └─────────────────────────┘   └──────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| **Intake Controller** | Receive incident reports from all channels, validate, classify priority, geocode, queue for dispatch | Inertia controller (desktop form) + API controllers (SMS webhook, IoT webhook) |
| **Dispatch Controller** | Serve the map console page, manage unit assignment, dispatch queue CRUD | Inertia controller for page; API endpoints for assignment actions |
| **Responder Controller** | Serve responder assignment pages, handle status transitions, scene data, closure | Inertia controller for pages; API endpoints for GPS updates and status changes |
| **Analytics Controller** | Serve KPI dashboard and heatmap pages, generate compliance reports | Inertia controller; PostGIS aggregate queries for spatial analytics |
| **Geocoding Service** | Convert address text to coordinates, reverse-geocode to barangay | Service class wrapping Mapbox API with PostGIS `ST_Contains()` fallback |
| **Priority Classifier** | Auto-suggest P1-P4 based on incident type keywords | Action class with keyword-to-priority mapping; dispatcher overrides allowed |
| **ETA Service** | Calculate estimated arrival time for units | Service class wrapping Mapbox Directions API |
| **Broadcast Events** | Push real-time state changes to WebSocket channels | Laravel Events implementing `ShouldBroadcast` dispatched to Reverb |
| **Map Composable** | Initialize MapLibre, manage GeoJSON sources, handle marker layers | Vue composable (`useDispatchMap`, `useResponderMap`) wrapping MapLibre GL JS |
| **Echo Composable** | Subscribe to WebSocket channels, route events to map and UI state | Vue composable (`useIncidentChannel`, `useUnitChannel`) wrapping Laravel Echo |
| **Integration Connectors** | Stubbed adapters for external APIs (Semaphore, PAGASA, NDRRMC, BFP, PNP, hospital) | Service classes behind interfaces; swap stubs for real implementations later |
| **Report Generator** | Generate PDF reports on incident closure and scheduled compliance reports | Queued job using Dompdf; triggered by event listener on incident closure |

## Recommended Project Structure

The existing Laravel 12 structure must be extended, not reorganized. New IRMS domain code lives alongside existing auth/settings code.

```
app/
├── Actions/
│   ├── Fortify/                  # existing auth actions
│   ├── Incident/                 # CreateIncident, ClassifyPriority, CloseIncident
│   ├── Dispatch/                 # AssignUnit, ReassignUnit, LogMutualAid
│   └── Responder/               # AcknowledgeAssignment, UpdateStatus, SubmitOutcome
├── Concerns/                     # existing shared traits
├── Events/
│   ├── IncidentCreated.php
│   ├── IncidentStatusChanged.php
│   ├── UnitLocationUpdated.php
│   ├── UnitStatusChanged.php
│   ├── AssignmentPushed.php
│   ├── MessageSent.php
│   └── ResourceRequested.php
├── Http/
│   ├── Controllers/
│   │   ├── Settings/             # existing
│   │   ├── Intake/               # IntakeController, IoTWebhookController, SmsWebhookController
│   │   ├── Dispatch/             # DispatchController, AssignmentController, QueueController
│   │   ├── Responder/            # ResponderController, StatusController, SceneController
│   │   ├── Analytics/            # DashboardController, HeatmapController, ReportController
│   │   └── Api/                  # UnitLocationController, UnitStatusController
│   ├── Middleware/
│   │   ├── HandleInertiaRequests.php  # existing (extend with IRMS shared props)
│   │   ├── HandleAppearance.php       # existing
│   │   ├── EnsureRole.php             # new role-checking middleware
│   │   └── ValidateWebhookSignature.php  # HMAC validation for IoT/external webhooks
│   └── Requests/
│       ├── Settings/             # existing
│       ├── Incident/             # StoreIncidentRequest, UpdateIncidentRequest
│       ├── Dispatch/             # AssignUnitRequest
│       └── Responder/            # UpdateStatusRequest, SubmitOutcomeRequest
├── Listeners/
│   ├── BroadcastIncidentCreated.php
│   ├── StartAcknowledgementTimer.php
│   ├── GenerateIncidentReport.php
│   ├── UpdateDispatchMetrics.php
│   └── LogTimelineEntry.php
├── Models/
│   ├── User.php                  # existing (add role, agency, unit_id)
│   ├── Incident.php
│   ├── Unit.php
│   ├── Barangay.php
│   ├── IncidentTimeline.php
│   ├── IncidentMessage.php
│   └── Enums/
│       ├── IncidentPriority.php  # P1, P2, P3, P4
│       ├── IncidentStatus.php    # PENDING, DISPATCHED, ACKNOWLEDGED, etc.
│       ├── IncidentChannel.php   # SMS, APP, VOICE, IOT, WALKIN
│       ├── UnitStatus.php        # STANDBY, ACKNOWLEDGED, EN_ROUTE, etc.
│       ├── UnitType.php          # AMBULANCE, FIRE, RESCUE, POLICE
│       └── UserRole.php          # DISPATCHER, RESPONDER, SUPERVISOR, ADMIN
├── Policies/
│   ├── IncidentPolicy.php
│   └── UnitPolicy.php
├── Services/
│   ├── GeocodingService.php      # Mapbox geocode + PostGIS barangay lookup
│   ├── EtaService.php            # Mapbox Directions API wrapper
│   ├── PriorityClassifier.php    # Keyword-based auto-priority suggestion
│   ├── DispatchQueueService.php  # Priority-ordered queue management
│   └── Integrations/
│       ├── Contracts/
│       │   ├── SmsGateway.php    # interface
│       │   ├── WeatherProvider.php
│       │   └── HospitalNotifier.php
│       ├── SemaphoreGateway.php  # implements SmsGateway (stubbed)
│       ├── PagasaProvider.php    # implements WeatherProvider (stubbed)
│       └── FhirNotifier.php     # implements HospitalNotifier (stubbed)
└── Jobs/
    ├── GenerateIncidentPdf.php
    ├── ProcessGpsUpdate.php
    └── GenerateComplianceReport.php

resources/js/
├── pages/
│   ├── auth/                     # existing
│   ├── settings/                 # existing
│   ├── Dashboard.vue             # existing (becomes role-aware redirect)
│   ├── intake/
│   │   └── Index.vue             # Three-panel intake: channel monitor, triage form, queue
│   ├── dispatch/
│   │   └── Console.vue           # Full-screen map console with sidebar panels
│   ├── responder/
│   │   ├── Assignment.vue        # Active assignment with status-aware tabs
│   │   └── Standby.vue           # Waiting screen when no assignment
│   └── analytics/
│       ├── Dashboard.vue         # KPI metrics with charts
│       └── Heatmap.vue           # Choropleth map with filters
├── components/
│   ├── map/
│   │   ├── DispatchMap.vue       # MapLibre dispatch console (3D, incident + unit layers)
│   │   ├── ResponderMap.vue      # MapLibre mini-map for responder nav tab
│   │   ├── HeatmapMap.vue        # MapLibre choropleth for analytics
│   │   └── layers/               # Layer configuration objects (incident, unit, heatmap)
│   ├── dispatch/
│   │   ├── DispatchQueue.vue     # Priority-ordered incident queue sidebar
│   │   ├── UnitPanel.vue         # Available units list with proximity sort
│   │   ├── AssignmentModal.vue   # Unit selection + ETA display
│   │   └── SessionMetrics.vue    # Header metrics bar
│   ├── responder/
│   │   ├── InfoTab.vue
│   │   ├── NavTab.vue
│   │   ├── SceneTab.vue          # Checklist + vitals + assessment tags
│   │   ├── OutcomeTab.vue        # Closure form with hospital picker
│   │   └── CommsTab.vue          # Bi-directional messaging
│   ├── intake/
│   │   ├── ChannelMonitor.vue    # Live feed from all 5 channels
│   │   ├── TriageForm.vue        # Structured incident creation form
│   │   └── PriorityBadge.vue     # Color-coded priority indicator
│   └── shared/
│       ├── AudioAlert.vue        # Web Audio API alert system
│       └── NotificationToast.vue # Slide-in notification overlay
├── composables/
│   ├── useAppearance.ts          # existing
│   ├── useTwoFactorAuth.ts       # existing
│   ├── useDispatchMap.ts         # MapLibre init, layer management, click handlers
│   ├── useResponderMap.ts        # MapLibre mini-map with route polyline
│   ├── useIncidentChannel.ts     # Echo subscription to incidents channel
│   ├── useUnitChannel.ts         # Echo subscription to units channel
│   ├── usePrivateChannel.ts      # Echo subscription to private unit assignment channel
│   ├── useAudioAlert.ts          # Web Audio API tone generation per priority
│   ├── useGpsTracking.ts         # Geolocation API + periodic POST to backend
│   └── useAcknowledgementTimer.ts # 90-second countdown with escalation
├── types/
│   ├── incident.ts               # Incident, IncidentTimeline, IncidentMessage types
│   ├── unit.ts                   # Unit type with coordinates
│   ├── barangay.ts               # Barangay with GeoJSON boundary
│   └── map.ts                    # GeoJSON feature types for map sources
└── layouts/
    ├── AppLayout.vue             # existing (extend with role-aware nav)
    ├── DispatchLayout.vue        # Full-screen layout for dispatch console (no sidebar)
    └── ResponderLayout.vue       # Mobile-optimized layout with bottom nav

routes/
├── web.php                       # existing + role-based dashboard redirect
├── settings.php                  # existing
├── intake.php                    # Intake layer routes (dispatcher role)
├── dispatch.php                  # Dispatch layer routes (dispatcher role)
├── responder.php                 # Responder layer routes (responder role)
├── analytics.php                 # Analytics layer routes (supervisor role)
├── api.php                       # GPS updates, unit status, webhooks
└── channels.php                  # WebSocket channel authorization
```

### Structure Rationale

- **Actions/ by domain:** Each domain (Incident, Dispatch, Responder) gets its own action folder. Actions are single-purpose classes that encapsulate one business operation. This aligns with the existing Fortify action pattern already in the codebase.
- **Services/ for cross-cutting:** GeocodingService, EtaService, and PriorityClassifier are used by multiple controllers/actions. They wrap external APIs and complex logic.
- **Services/Integrations/ behind interfaces:** All external API connectors implement interfaces. Stubs are bound in the service container; swap to real implementations when API keys arrive. No controller code changes needed.
- **Events/ flat structure:** Seven broadcast events cover the entire real-time surface. Each implements `ShouldBroadcast` and defines its channel. Flat because the event count is manageable.
- **composables/ for WebSocket + Map:** The key frontend architectural decision. Vue composables encapsulate MapLibre and Echo subscriptions separately, then pages compose them together. This avoids coupling map rendering to WebSocket transport.
- **Route files per layer:** Each IRMS layer gets its own route file included from `web.php` or `bootstrap/app.php`. Keeps routes organized and allows layer-specific middleware groups.
- **Enums/ under Models:** PHP 8.1+ backed enums for all status, type, priority, and role values. Used in both migrations (check constraints) and application logic.

## Architectural Patterns

### Pattern 1: Inertia Pages + Echo Subscriptions (Hybrid Real-Time)

**What:** Standard Inertia pages load initial state via server props (full page data on load), then Vue composables subscribe to Reverb channels via Laravel Echo for incremental real-time updates. The page does not poll -- it receives pushed events.

**When to use:** Dispatch console, responder assignment view, intake channel monitor -- any page where data changes in real-time after initial load.

**Trade-offs:** PRO: Leverages Inertia for SSR/initial load speed, then WebSocket for live updates. CON: Two data paths to maintain (Inertia props for initial state, Echo events for updates). State can diverge if a WebSocket reconnect misses events.

**Example:**
```typescript
// composables/useIncidentChannel.ts
import Echo from 'laravel-echo';
import type { Incident } from '@/types/incident';

export function useIncidentChannel(
  onCreated: (incident: Incident) => void,
  onUpdated: (incident: Incident) => void,
) {
  const echo = window.Echo;

  echo.channel('incidents')
    .listen('IncidentCreated', (e: { incident: Incident }) => {
      onCreated(e.incident);
    })
    .listen('IncidentUpdated', (e: { incident: Incident }) => {
      onUpdated(e.incident);
    });

  return {
    leave: () => echo.leave('incidents'),
  };
}
```

```vue
<!-- pages/dispatch/Console.vue -->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useIncidentChannel } from '@/composables/useIncidentChannel';
import type { Incident } from '@/types/incident';

// Initial state from Inertia server props
const props = defineProps<{
  incidents: Incident[];
  units: Unit[];
}>();

const liveIncidents = ref([...props.incidents]);

// Subscribe to real-time updates after mount
const { leave } = useIncidentChannel(
  (incident) => liveIncidents.value.unshift(incident),
  (incident) => {
    const idx = liveIncidents.value.findIndex(i => i.id === incident.id);
    if (idx !== -1) liveIncidents.value[idx] = incident;
  },
);

onUnmounted(() => leave());
</script>
```

### Pattern 2: GeoJSON Source Update via WebSocket (Live Map)

**What:** MapLibre map uses GeoJSON sources for incident and unit markers. WebSocket events trigger `updateData()` (partial update with feature ID) on the GeoJSON source instead of `setData()` (full replacement). This is critical for performance when tracking 20+ units with 10-second GPS intervals.

**When to use:** Dispatch map console for live unit tracking and incident markers.

**Trade-offs:** PRO: `updateData()` avoids re-serializing the entire feature collection on every GPS tick. CON: Requires unique feature IDs on all GeoJSON features. Must handle feature add/remove separately from update.

**Example:**
```typescript
// composables/useDispatchMap.ts (simplified)
function handleUnitLocationUpdate(unitId: string, lat: number, lng: number) {
  const source = map.getSource('unit-pts') as maplibregl.GeoJSONSource;
  if (!source) return;

  // Partial update: only this feature's geometry changes
  source.updateData({
    update: [{
      id: unitId,
      newGeometry: { type: 'Point', coordinates: [lng, lat] },
    }],
  });
}

function handleNewIncident(incident: Incident) {
  const source = map.getSource('inc-pts') as maplibregl.GeoJSONSource;
  if (!source) return;

  source.updateData({
    add: [{
      type: 'Feature',
      id: incident.id,
      geometry: { type: 'Point', coordinates: [incident.longitude, incident.latitude] },
      properties: {
        r: priorityColorMap[incident.priority],
        priority: incident.priority,
        type: incident.type,
        sel: 0,
      },
    }],
  });
}
```

### Pattern 3: Event-Driven Incident Lifecycle (Server-Side)

**What:** Every incident state transition fires a Laravel event. Listeners handle side effects: broadcasting to WebSocket, logging timeline entries, triggering acknowledgement timers, generating reports. Controllers call Actions, Actions fire Events, Listeners handle consequences.

**When to use:** All incident status changes, unit assignments, and message sends.

**Trade-offs:** PRO: Decoupled -- adding a new side effect (e.g., SMS notification) means adding a listener, not modifying controller or action code. CON: Harder to trace the full flow; must document event-listener mappings.

**Example flow:**
```
Controller receives status update request
    → Action: UpdateIncidentStatus
        → Validates transition (PENDING → DISPATCHED → ACKNOWLEDGED → ...)
        → Updates model
        → Fires IncidentStatusChanged event
            → Listener: BroadcastToReverb (ShouldBroadcast)
            → Listener: LogTimelineEntry (writes to incident_timeline)
            → Listener: CheckAcknowledgementTimeout (if DISPATCHED, schedule 90s check)
            → Listener: NotifyReporter (if ON_SCENE or RESOLVED, queue SMS)
            → Listener: GenerateReport (if RESOLVED, queue PDF job)
```

```php
// app/Events/IncidentStatusChanged.php
class IncidentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('incidents'),
            new PrivateChannel("incidents.{$this->incident->id}"),
        ];
    }
}
```

### Pattern 4: Interface-Based Integration Connectors (Stub-First)

**What:** Every external API (Mapbox, Semaphore, PAGASA, NDRRMC, BFP, PNP, hospital FHIR) is wrapped in a service class implementing an interface. The service container binds stubs in development. Swap to real implementations per-environment via config.

**When to use:** All external integrations from day one. The interface must exist before the stub, so the contract is clear.

**Trade-offs:** PRO: Entire system testable without external dependencies. Real integrations plug in without touching business logic. CON: Requires upfront interface design; risk of designing interfaces that don't match real API semantics.

**Example:**
```php
// app/Services/Integrations/Contracts/SmsGateway.php
interface SmsGateway
{
    public function send(string $to, string $message): bool;
    public function parseInbound(array $payload): InboundSms;
}

// app/Services/Integrations/SemaphoreGateway.php (stub)
class SemaphoreGateway implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        Log::info("SMS stub: {$to} - {$message}");
        return true;
    }
}

// app/Providers/AppServiceProvider.php
$this->app->bind(SmsGateway::class, SemaphoreGateway::class);
```

## Data Flow

### Incident Lifecycle Flow (Primary)

```
[Report arrives]
    │
    ├── SMS Webhook ──► SmsWebhookController ──► ParseInbound ──┐
    ├── IoT Webhook ──► IoTWebhookController ──► ParseSensor ───┤
    ├── Desktop Form ─► IntakeController (Inertia) ─────────────┤
    │                                                            │
    ▼                                                            ▼
[CreateIncident Action]
    │
    ├── GeocodingService.geocode(address) → { lat, lng }
    ├── GeocodingService.findBarangay(lat, lng) → barangay_id (PostGIS ST_Contains)
    ├── PriorityClassifier.suggest(type, message) → P1-P4
    ├── Incident::create({...})
    │
    ▼
[Fire IncidentCreated Event]
    │
    ├── Broadcast → Reverb → Dispatch Console (new marker on map, queue item)
    ├── LogTimelineEntry → incident_timeline (CREATED entry)
    └── NotifyChannelOriginator → SMS ack to caller (via SmsGateway stub)
    │
    ▼
[Dispatcher views queue, selects incident, assigns unit(s)]
    │
    ▼
[AssignUnit Action]
    ├── EtaService.calculate(unit.coords, incident.coords) → ETA minutes
    ├── Incident.update(assigned_unit, dispatched_at, status: DISPATCHED)
    │
    ▼
[Fire AssignmentPushed Event]
    ├── Broadcast → Reverb → Private channel units.{id}.private (responder receives)
    ├── Schedule AcknowledgementTimeout job (90 seconds)
    │
    ▼
[Responder acknowledges → en route → on scene → resolving → resolved]
    │ (each transition fires IncidentStatusChanged)
    │
    ▼
[CloseIncident Action]
    ├── Incident.update(outcome, hospital, closure_notes, resolved_at)
    ├── Fire IncidentStatusChanged (→ RESOLVED)
    │   ├── Broadcast → all subscribers
    │   ├── Queue GenerateIncidentPdf job
    │   ├── Queue NotifyReporter (SMS: "incident resolved")
    │   └── LogTimelineEntry (RESOLVED)
    │
    ▼
[Incident archived with full timeline, vitals, report PDF URL]
```

### Real-Time GPS Tracking Flow

```
[Responder device]
    │
    ├── useGpsTracking composable
    │   └── navigator.geolocation.watchPosition()
    │       └── Every 10s (EN_ROUTE) or 60s (ON_SCENE):
    │           POST /api/units/{id}/location { lat, lng }
    │
    ▼
[UnitLocationController]
    │
    ├── Validate request (rate limit: 1 req / 5 sec per unit)
    ├── Unit.update(coordinates, location_at)
    │
    ▼
[Fire UnitLocationUpdated Event (ShouldBroadcastNow)]
    │
    ├── Broadcast → Reverb → 'units' channel
    │
    ▼
[Dispatch Console]
    │
    ├── useUnitChannel composable receives event
    ├── Calls map.getSource('unit-pts').updateData({...})
    ├── Unit marker moves on map (WebGL re-render, sub-frame)
    │
    ▼
[Supervisor Dashboard]
    │
    └── Unit status panel updates position display
```

**Why `ShouldBroadcastNow` for GPS:** GPS updates are time-sensitive. Queuing them adds latency. The payload is tiny (unit_id + lat/lng + timestamp). Broadcasting directly from the request lifecycle is acceptable here, unlike heavier events like PDF generation.

### WebSocket Channel Architecture

```
Public Channels:
  incidents          → All IncidentCreated, IncidentUpdated events
  units              → All UnitLocationUpdated events (GPS positions)

Private Channels (require auth):
  incidents.{id}     → IncidentStatusChanged, MessageSent, ResourceRequested for specific incident
  units.{id}.private → AssignmentPushed for specific unit (only that unit's responder)

Presence Channels (optional, future):
  dispatch.console   → Track which dispatchers are online (shift awareness)
```

### Key Data Flows

1. **Intake to Dispatch Queue:** Incident created via any channel -> geocoded + classified -> appears in dispatch queue (Inertia prop on initial load, WebSocket push for subsequent). The queue is a database query ordered by priority then created_at, not an in-memory structure.

2. **Dispatch Map State:** On page load, Inertia sends all active incidents + all active units as GeoJSON-ready props. After load, Echo subscriptions receive incremental updates via `updateData()`. On reconnect, Inertia partial reload (`router.reload({ only: ['incidents', 'units'] })`) resynchronizes full state.

3. **Responder Assignment Push:** Assignment event sent to private channel. If responder is on the page, Echo receives it immediately. If not (page closed/backgrounded), the assignment persists in the database. On next page load, Inertia props contain the active assignment. No data is lost if WebSocket is disconnected.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 1 dispatcher, 5-10 units | Monolith is fine. Single Reverb process. All queries on primary DB. This is the initial Butuan City deployment. |
| 3-5 dispatchers, 20-50 units | Add Redis caching for dispatch queue and unit positions. Reverb handles this easily. Monitor PostGIS query times on spatial indices. |
| Multi-LGU (10+ dispatchers, 100+ units) | Reverb horizontal scaling via Redis pub/sub. Read replica for analytics queries (heatmaps, reports). Horizon workers scaled to 4+. Consider separate queue for GPS updates vs. business events. |
| Province-wide (50+ dispatchers, 500+ units) | Out of current scope. Would need: Reverb cluster behind load balancer, database partitioning by LGU, dedicated analytics database, possibly extract GPS ingestion to separate microservice. |

### Scaling Priorities

1. **First bottleneck: PostGIS spatial queries under concurrent dispatch load.** The `ST_Contains()` for barangay lookup and `ST_DWithin()` for proximity search are the most expensive queries. Mitigation: GIST indices on all geometry columns (already in spec), and cache barangay boundary polygons in Redis (they change rarely).

2. **Second bottleneck: Reverb connection count.** Each dispatcher console and each responder maintains a persistent WebSocket. At 50 concurrent connections, Reverb handles this trivially. At 500+, use Reverb's Redis-based horizontal scaling to distribute across multiple Reverb processes.

3. **Third bottleneck: GPS update write throughput.** At 50 units updating every 10 seconds = 300 writes/minute. PostgreSQL handles this easily. At 500 units = 3,000 writes/minute, consider batching GPS updates or writing to Redis first and flushing to PostgreSQL periodically.

## Anti-Patterns

### Anti-Pattern 1: Polling Instead of WebSocket Push

**What people do:** Use Inertia polling (`useInterval` or `router.reload`) to refresh dispatch map state every 5 seconds.
**Why it's wrong:** Creates unnecessary server load (N dispatchers x every 5s = constant HTTP requests). Latency is 0-5s instead of sub-500ms. Does not scale.
**Do this instead:** Use Laravel Echo subscriptions for real-time events. Use Inertia `router.reload({ only: [...] })` only for reconnection recovery, not regular updates.

### Anti-Pattern 2: Storing Map State in Pinia/Global Store

**What people do:** Create a global Pinia store for all incidents and units, sync it from both Inertia props and WebSocket events.
**Why it's wrong:** Dual source of truth. Inertia already manages page state. Adding Pinia creates sync conflicts and makes SSR harder (Pinia needs hydration).
**Do this instead:** Use reactive `ref()` initialized from Inertia props, mutated by Echo event handlers. Keep state local to the page component. If multiple child components need it, provide/inject or pass as props. Pinia is out of scope per PROJECT.md.

### Anti-Pattern 3: Broadcasting Everything Synchronously

**What people do:** Use `ShouldBroadcastNow` on all events to avoid queue latency.
**Why it's wrong:** Heavy events (incident creation with geocoding, PDF generation triggers) block the HTTP response. Only GPS updates need synchronous broadcast.
**Do this instead:** Use `ShouldBroadcast` (queued) by default. Use `ShouldBroadcastNow` only for `UnitLocationUpdated` where sub-second delivery matters and payload is tiny.

### Anti-Pattern 4: HTML Marker Overlays on MapLibre

**What people do:** Use MapLibre `Marker` class (which creates HTML DOM elements) for incident and unit pins.
**Why it's wrong:** Each HTML marker is a separate DOM node. At 50+ markers with animations, DOM thrashing kills performance. The spec explicitly requires WebGL-rendered markers.
**Do this instead:** Use circle layers with GeoJSON sources. Define layer stacks (halo, border, pin, dot) as MapLibre style layers. Update positions via `source.updateData()`. All rendering happens on GPU.

### Anti-Pattern 5: Fat Controllers with Inline Business Logic

**What people do:** Put geocoding, priority classification, unit assignment, and broadcasting all inside the controller method.
**Why it's wrong:** Untestable, unreusable, unmaintainable. The same incident creation logic is needed from 3 entry points (form, SMS webhook, IoT webhook).
**Do this instead:** Controllers validate and delegate. Actions contain business logic. Services wrap external APIs. Events decouple side effects. Controller methods should be 5-15 lines.

### Anti-Pattern 6: Raw PostGIS SQL in Controllers

**What people do:** Write `DB::select("SELECT ST_Contains(boundary, ST_Point(?, ?))")` directly in controllers.
**Why it's wrong:** Bypasses Eloquent, not testable, SQL injection risk if not parameterized carefully, duplicated across controllers.
**Do this instead:** Use Laravel Magellan or `matanyadaev/laravel-eloquent-spatial` for Eloquent-integrated spatial queries. Or create dedicated service methods (e.g., `GeocodingService::findBarangay()`) that encapsulate the raw query in one place.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Mapbox Geocoding API | REST via GeocodingService | Forward geocode with PH country filter. Fallback to manual pin if API fails. Cache results (same address = same coords). |
| Mapbox Directions API | REST via EtaService | Calculate road-network ETA for unit-to-incident. Cache popular routes. Fallback to haversine straight-line estimate. |
| Mapbox Vector Tiles | MapLibre GL JS basemap style URL | Loaded client-side. No server involvement. Requires access token in frontend config. |
| Semaphore SMS API | REST via SmsGateway interface | Inbound: webhook parses SMS to incident. Outbound: acknowledgement + status updates. Stubbed initially. |
| PAGASA Weather API | REST via WeatherProvider interface | Periodic fetch (every 15 min via scheduler). Flood advisory overlay on map. Auto-escalate flood incidents. Stubbed initially. |
| Hospital HIMS (HL7 FHIR R4) | REST via HospitalNotifier interface | Pre-arrival notification triggered by "Transport to Hospital" outcome. Stubbed initially. |
| NDRRMC Reporting API | REST/XML via ReportConnector interface | Auto-submit SitRep on P1 closure. Fallback: PDF email. Stubbed initially. |
| BFP Fire Incident System | REST webhook (bidirectional) | Inbound: BFP fires appear in IRMS queue. Outbound: IRMS fires pushed to BFP. Stubbed initially. |
| PNP e-Blotter | REST via BlotterConnector interface | Auto-create blotter for criminal incidents. Requires dispatcher confirmation. Stubbed initially. |
| Laravel Reverb | WebSocket (Pusher protocol) | Self-hosted. Runs as separate process on port 6001. Managed by Supervisor in production. |
| Redis | TCP connection | Queue backend (Horizon), cache, Reverb pub/sub. Single managed instance handles all three roles at current scale. |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Intake -> Dispatch | Database (incident record) + Event broadcast | Intake creates incident; dispatch console receives via IncidentCreated event on WebSocket. No direct controller-to-controller calls. |
| Dispatch -> Responder | Event broadcast (private channel) + Database | AssignmentPushed event to responder's private channel. Assignment persisted in DB as source of truth. |
| Responder -> Dispatch | HTTP API (status update, GPS) + Event broadcast | Responder POSTs status changes; events broadcast back to dispatch console. Bi-directional messaging via MessageSent events. |
| Any Layer -> Integration | Service interface call (queued job) | Business logic calls interface methods. Actual HTTP to external APIs happens in queued jobs. Failures logged, retried, never block user flow. |
| Any Layer -> Analytics | Database reads (PostGIS aggregates) | Analytics queries the same database. No separate data pipeline needed at current scale. Heavy queries (heatmaps) should use database read replica when available. |

## Build Order Implications

Based on component dependencies, the suggested build order for IRMS layers:

1. **Foundation (RBAC + PostGIS + Data Models)** -- Build first because every layer depends on roles, permissions, and the incident/unit data model. Migrate to PostgreSQL + PostGIS. Seed barangays with boundary polygons.

2. **Intake Layer** -- Build second because dispatch needs incidents to exist. The triage form, geocoding service, and priority classifier are self-contained. This layer can be fully tested without dispatch or responder.

3. **Real-Time Infrastructure (Reverb + Echo)** -- Set up third because dispatch console cannot function without it. Configure Reverb, install Laravel Echo, define channels and authorization. Test with simple broadcast events before building the dispatch UI.

4. **Dispatch Layer (Map Console + Assignment)** -- Build fourth. Depends on: incidents (from intake), real-time events (from Reverb), spatial data (from PostGIS). This is the most complex UI component -- MapLibre GL JS with WebGL layers, GeoJSON sources, and Echo subscriptions.

5. **Responder Layer** -- Build fifth. Depends on: dispatch (assignments must exist), real-time events (status broadcasts), GPS tracking. Mobile-optimized Inertia pages with status-aware tabs.

6. **Integration Layer** -- Build sixth. Depends on: all other layers (integration connectors are called from intake, dispatch, and responder workflows). Interfaces designed early; stubs replaced with real implementations.

7. **Analytics Layer** -- Build last. Depends on: historical data from all other layers. PostGIS aggregate queries for heatmaps. KPI calculations from incident timestamps. Report generation via queued jobs.

**Critical dependency chain:** PostGIS + Data Models -> Intake -> Reverb setup -> Dispatch -> Responder -> Integration -> Analytics. No layer can be built out of this order without stub scaffolding.

## Sources

- [Laravel Reverb official documentation](https://laravel.com/docs/12.x/reverb) -- HIGH confidence
- [Laravel Broadcasting documentation](https://laravel.com/docs/12.x/broadcasting) -- HIGH confidence
- [Laravel Events documentation](https://laravel.com/docs/12.x/events) -- HIGH confidence
- [MapLibre GL JS GeoJSONSource API (updateData)](https://maplibre.org/maplibre-gl-js/docs/API/classes/GeoJSONSource/) -- HIGH confidence
- [MapLibre GL JS partial updates discussion](https://github.com/maplibre/maplibre-gl-js/issues/1236) -- MEDIUM confidence
- [Laravel Magellan PostGIS package](https://github.com/clickbar/laravel-magellan) -- MEDIUM confidence
- [matanyadaev/laravel-eloquent-spatial](https://packagist.org/packages/matanyadaev/laravel-eloquent-spatial) -- MEDIUM confidence
- [vue-maplibre-gl Vue 3 plugin](https://github.com/indoorequal/vue-maplibre-gl) -- MEDIUM confidence
- [Computer-Aided Dispatch architecture (Wikipedia)](https://en.wikipedia.org/wiki/Computer-aided_dispatch) -- MEDIUM confidence
- [NHTSA CAD Interoperability Strategies](https://www.911.gov/assets/NHTSA-CAD-Strategies-for-the-Future_Mar-2023_Final.pdf) -- MEDIUM confidence
- IRMS Technical Specification Document (`docs/IRMS-Specification.md`) -- HIGH confidence (project-specific)

---
*Architecture research for: Emergency Incident Response Management System (IRMS)*
*Researched: 2026-03-12*
