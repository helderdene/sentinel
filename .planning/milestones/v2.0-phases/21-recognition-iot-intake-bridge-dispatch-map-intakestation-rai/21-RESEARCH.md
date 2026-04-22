# Phase 21: Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail - Research

**Researched:** 2026-04-22
**Domain:** Laravel service extraction + Reverb broadcasting + Mapbox feature-state animation + Inertia/Echo hydration + Pest broadcast testing
**Confidence:** HIGH for all focus areas (verified against installed versions, official docs, and shipping IRMS code)

## Summary

Phase 21 is a well-scoped bridge phase — 27 implementation decisions were locked in CONTEXT.md. This research covers the eight HOW-to-implement focus areas the planner needs: (1) Mapbox feature-state pulse animation, (2) `@laravel/echo-vue` composable shape, (3) Redis `Cache::add` atomic semantics, (4) Inertia prop + Echo hydration merge, (5) `ShouldDispatchAfterCommit` ordering guarantees, (6) Pest broadcast testing strategy, (7) Wayfinder regeneration for optional form fields, and (8) SC6 load-test approach.

Every decision is compatible with IRMS's installed stack: Laravel 13, Reverb 1.10, `@laravel/echo-vue` 2.3.1 (latest 2.3.4), `mapbox-gl` 3.20.0 (latest 3.22.0), `predis/predis` 3.4, Pest 4.6. The `cameras` GeoJSON source in `useDispatchMap.ts` ALREADY sets `promoteId: 'id'` — this is the load-bearing detail that makes `setFeatureState` work with UUID camera IDs out of the box (verified at line 349).

**Primary recommendation:** Ship Phase 21 as a pure-additive layer. The factory, event, channel, composable, and rail are all new files/exports. The only modifications to shipped code are: (a) `IoTWebhookController` constructor swap (service injection), (b) `useDispatchMap.ts` adds two paint case-expressions + one export, (c) `RecognitionHandler::handle()` appends one factory call, (d) `IntakeStationController::overridePriority()` accepts one optional `trigger` field, (e) `ChannelFeed.vue` + `ChBadge.vue` extend a 5-element union to 6. No rewrites.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01 through D-27 are locked.** Summary (full text in `21-CONTEXT.md`):

- **Trigger rules (D-01..D-05):** Block + missing + lost_child categories create incidents; `allow` never does. IncidentType `person_of_interest` (seeded). `incident.notes` pre-formatted by factory. Coordinates copied from `camera.location`; no re-lookup. Severity × category → priority map in `config/fras.php` (Critical × block/missing → P2, Critical × lost_child → P1).
- **Factory shape (D-06..D-10):** `App\Services\FrasIncidentFactory` with two methods (`createFromSensor`, `createFromRecognition`). Gate order: severity → confidence → category → dedup → write. `Cache::add` for dedup, 60s TTL. Controller becomes thin delegate. Factory owns both Incident + `IncidentCreated` dispatch for both paths.
- **Map pulse + channel (D-11..D-16):** New `fras.alerts` private channel (operator/dispatcher/supervisor/admin). `RecognitionAlertReceived` event with full denorm payload (10 fields). `useFrasAlerts.ts` composable subscribes and triggers `pulseCamera()`. Mapbox `feature-state` + paint `case` expression. Pulse duration 3s, re-trigger resets timer. Info severity never broadcasts.
- **IntakeStation + escalate (D-17..D-23):** 6th FRAS rail (not a replacement). Rail data: Critical + Warning RecognitionEvents. Click routes to Incident detail if `incident_id` set, else opens read-only modal. Escalate-to-P1 button reuses `intake.override-priority` with optional `trigger='fras_escalate_button'`. Gate unchanged (supervisor + admin).
- **Text amendments (D-25..D-27):** ROADMAP SC1 "block-list" → "block/missing/lost_child". REQUIREMENTS RECOGNITION-02 same. ROADMAP SC6 + INTEGRATION-03 "4th rail" → "6th rail".

### Claude's Discretion

- Icon design for `IntakeIconFras.vue` (mirror existing icon style)
- CSS token `--t-ch-fras` color — **UI-SPEC already picked** `#0e7490` (light) / `#22d3ee` (dark)
- Pulse animation visual tuning — **UI-SPEC already specified** icon-size 0.55→0.88, halo radius 18→32, opacity 0.15→0.35
- Rail card visual layout — **UI-SPEC already specified** 40×40 thumbnail, `h-16` card, accent stripe, content layout
- Face thumbnail URL strategy (D-20) — **UI-SPEC recommends** (a) signed route now (Phase 20 D-22 pattern), 5-min TTL
- `FrasIncidentFactory` IncidentType caching strategy — planner picks (recommend per-request memoization)
- Ring-buffer size for FRAS rail — default 50 events
- Modal location — **UI-SPEC picks** `components/intake/FrasEventDetailModal.vue`
- Whether `OverridePriorityRequest` extends inline or new request class — **CONTEXT leans inline** per D-22

### Deferred Ideas (OUT OF SCOPE)

- `/fras/alerts` live feed, ACK/dismiss UX, audio cues, 100-alert ring buffer — Phase 22
- `/fras/events` searchable history, replay badges, manual promote-to-incident — Phase 22
- SceneTab "Person of Interest" accordion on responder view — Phase 22
- `fras_access_log` DPA audit table, signed 5-min recognition-image URLs, retention purge — Phase 22
- Stranger-detection `Snap` topic — milestone out-of-scope
- `allow`-category recognition events creating Incidents — explicitly excluded
- Changes to `useDispatchFeed` — locked unchanged by INTEGRATION-04
- New `IncidentChannel` enum case — reuse `IncidentChannel::IoT`
- Dispatcher gate widening for Escalate-to-P1 — Phase 22+
- Load-test SC6 automation tooling — planner picks (see §Validation Architecture below)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RECOGNITION-01 | Every MQTT RecPush persists to `recognition_events` regardless of severity | `RecognitionHandler::handle()` already persists (Phase 19 shipped). Phase 21 appends factory call AFTER persist; persistence is unconditional per handler current shape [VERIFIED: `app/Mqtt/Handlers/RecognitionHandler.php` line 77] |
| RECOGNITION-02 | `FrasIncidentFactory::createFromRecognition()` creates Incident from Critical recognition with `channel=IoT`, `priority=P2`, `event_data.source='fras_recognition'`, sets `recognition_events.incident_id` | CONTEXT D-06/D-07. Factory write-path step 5: persist incident → write timeline → round-trip `$event->incident_id = $incident->id` |
| RECOGNITION-03 | `IoTWebhookController` refactored to delegate to `FrasIncidentFactory::createFromSensor()` — shared adapter for sensor + recognition flows | CONTEXT D-06/D-09. Factor lines 56–92 verbatim [VERIFIED: `app/Http/Controllers/IoTWebhookController.php` lines 26–98]. Constructor swap: `BarangayLookupService` → `FrasIncidentFactory` (factory calls lookup internally for sensor path) |
| RECOGNITION-04 | Escalate-to-P1 button on FRAS-created Incidents; click updates priority + writes audit entry | CONTEXT D-21/D-22. Reuses `intake.override-priority` route with `trigger='fras_escalate_button'` |
| RECOGNITION-05 | Warning-severity events broadcast on `fras.alerts` for operator awareness; never auto-create Incidents | CONTEXT D-07 step 1 (severity gate): Warning → broadcast → return null. D-24 confirms same event class for both severities |
| RECOGNITION-06 | Duplicate `(camera_id, personnel_id)` within configurable window (default 60s) does NOT create second Incident; still persists to `recognition_events` | CONTEXT D-08. `Cache::add` atomic, returns false if key exists. Verified: [CITED: laravel.com/docs/13.x/cache#storing-items-in-the-cache] — "The `add` method is an atomic operation" |
| RECOGNITION-07 | Events below confidence threshold (default 0.75) → Info, never surface beyond history | CONTEXT D-07 step 2 (confidence gate): below threshold → return null (no broadcast, no Incident) |
| RECOGNITION-08 | All severity/dedup/confidence thresholds live in `config/fras.php` — field-tunable without deploy | CONTEXT D-05. Section: `config/fras.php#recognition` with `confidence_threshold`, `dedup_window_seconds`, `pulse_duration_seconds`, `priority_map`. All env-overridable |
| INTEGRATION-01 | Dispatch console cameras layer already toggleable (Phase 20); Phase 21 adds pulse animation triggered by `RecognitionAlertReceived` on matched marker | CONTEXT D-13/D-14/D-15. Mapbox `setFeatureState` + paint case-expression. No new source, no new layer. Existing `cameras` source has `promoteId: 'id'` [VERIFIED: `resources/js/composables/useDispatchMap.ts` line 349] — UUID setFeatureState works out of box |
| INTEGRATION-03 | IntakeStation 6th channel rail (FRAS); operators triage FRAS alongside existing 5 rails | CONTEXT D-17. Extend `ChannelFeed.vue` `channelRows` from 5 to 6. No refactor. Text amendment D-27 (4th→6th) |
| INTEGRATION-04 | `useDispatchFeed` remains unchanged — recognition Incidents flow via existing `IncidentCreated` broadcast | CONTEXT D-10/D-13. Factory dispatches `IncidentCreated` (same payload shape, byte-identical). `useFrasAlerts` is a sibling composable, not a fork of `useDispatchFeed` |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- **PHP formatting:** MUST run `vendor/bin/pint --dirty --format agent` after touching PHP files
- **PHP style:** curly braces always, constructor property promotion, explicit return types, PHPDoc over inline comments
- **TypeScript:** strict mode, `type` imports enforced, import ordering (builtin → external → internal → parent → sibling → index)
- **Vue:** essential rules, `1tbs` brace style, single-root-element constraint
- **Tests mandatory:** Every change MUST be programmatically tested (Pest 4, `RefreshDatabase`, SQLite in-memory for non-FRAS feature tests). FRAS-specific tests MUST use PostgreSQL (FRAMEWORK-05 gate)
- **Skills to activate:** `laravel-best-practices`, `wayfinder-development`, `pest-testing`, `inertia-vue-development`, `echo-vue-development`, `echo-development`, `tailwindcss-development`
- **Do NOT modify:** auto-generated dirs (`resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/`, `resources/js/components/ui/`) — regenerated on every build
- **Search docs first:** Use Laravel Boost `search-docs` tool before API/config changes

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Recognition → Incident creation | API / Backend (Service) | Database | `FrasIncidentFactory` is a PHP service; writes to Postgres inside a DB transaction. Dispatch of `IncidentCreated` + `RecognitionAlertReceived` happens post-commit |
| Redis dedup | API / Backend (Cache) | — | `Cache::add` atomic operation; no DB trip. Runs inline in factory gate chain |
| `fras.alerts` broadcast delivery | API → Reverb → Browser | — | Reverb is the WebSocket tier (already running per Phase 17/19). Event uses Pusher protocol; `@laravel/echo-vue` subscribes client-side |
| Dispatch map pulse animation | Browser (GPU via Mapbox) | — | `setFeatureState` + paint case-expression runs in WebGL. Zero API/DB interaction after the initial broadcast arrival |
| IntakeStation rail SSR seed | Frontend Server (Inertia) | API / Backend | `IntakeStationController::show()` loads top-50 events via Eloquent; passes as Inertia prop. Browser receives hydrated state on page load |
| IntakeStation rail live updates | Browser (Echo composable) | — | `useFrasRail` (or `useFrasAlerts`) subscribes to `fras.alerts`; maintains ring buffer client-side. No AJAX |
| Escalate-to-P1 form submit | Browser (Inertia form) → API | Database | Inertia `useForm().post(intake.overridePriority(incident).url)`; backend validates, updates, writes timeline, broadcasts `IncidentStatusChanged` |
| Face image signed URL (if D-20 option a chosen) | API / Backend (`URL::temporarySignedRoute`) | Browser | Same pattern as Phase 20 D-22. URL expires; controller streams file |

## Standard Stack

### Core

All already installed and in use in IRMS. No new dependencies.

| Library | Version (installed / latest) | Purpose | Why Standard |
|---------|-----------|---------|--------------|
| `laravel/framework` | ^13.0 | MVC + Eloquent + Cache + Broadcasting | v1.0 upgrade completed Phase 17 |
| `laravel/reverb` | ^1.10 | WebSocket server (Pusher protocol) | Ships with Laravel; Phase 17 already uses it for 6 broadcast events |
| `laravel/horizon` | ^5.45.6 | Queue supervisor | Already configured; Phase 19 added `fras-supervisor` queue |
| `predis/predis` | ^3.4 | Redis client for cache | Already cache default; used by Phase 20 AckHandler + `Cache::add` dedup |
| `inertiajs/inertia-laravel` | ^2.0.24 | SSR bridge | Inertia v2; all admin/dispatch pages use it |
| `laravel/wayfinder` | ^0.1.14 | PHP → TypeScript route generation | Regenerated on every build; optional form fields detected automatically [CITED: github.com/laravel/wayfinder] |
| `pestphp/pest` | ^4.6 | Test framework | `RefreshDatabase` + feature tests; broadcast assertions via `Event::fake()` |
| `@laravel/echo-vue` | ^2.3.1 (latest 2.3.4) | Vue composables for Echo | `useEcho` handles subscribe + cleanup automatically [CITED: laravel.com/docs/13.x/broadcasting] |
| `laravel-echo` | ^2.3.1 (latest 2.3.4) | Echo client | Pusher-protocol client; paired with Reverb |
| `mapbox-gl` | ^3.20.0 (latest 3.22.0) | WebGL map | Retained per v2.0 Scope Decision; camera layer shipped Phase 20 |

**Version verification (2026-04-22, npm registry):**
- `@laravel/echo-vue`: `2.3.4` (installed `2.3.1` via `^2.3.1` — OK)
- `mapbox-gl`: `3.22.0` (installed `3.20.0` via `^3.20.0` — OK, both support `setFeatureState` and `promoteId`)
- `laravel-echo`: `2.3.4` (aligned with echo-vue)

No version bump required for Phase 21. [VERIFIED: npm view commands run 2026-04-22]

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `clickbar/laravel-magellan` | ^2.1 | PostGIS geometry | Factory copies `camera.location` Point verbatim into `incident.coordinates` — no migration |
| `@vueuse/core` | ^12.8.2 | Vue composable utilities | `useRelativeTime` equivalent (if not already present) for rail card timestamps |
| `lucide-vue-next` | ^0.468.0 | Icon library | `lucide:TrendingUp` on Escalate-to-P1 button per UI-SPEC |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `Cache::add` (atomic operation) | `Cache::lock()` + explicit acquire/release | `Cache::add` is simpler, single call, no lock token to manage. Same atomicity guarantees on Redis driver. `lock()` is overkill for dedup |
| Mapbox `feature-state` + paint case | DOM overlay + CSS animation | DOM overlays don't stay attached under zoom/pan; not GPU-accelerated; would break at 50 events/sec |
| New composable `useFrasAlerts.ts` | Extending `useDispatchFeed.ts` | INTEGRATION-04 locks `useDispatchFeed` unchanged. Separation of concerns also cleaner |
| `useFrasRail.ts` (separate composable) | Extending `useIntakeFeed.ts` | Planner discretion per CONTEXT. Separate is cleaner; `useIntakeFeed` currently operates on Incidents, rail operates on RecognitionEvents (different shape) |
| Laravel `Event::fake()` for broadcast tests | Third-party `laravel-broadcast-testing` | `Event::fake()` + `Event::assertDispatched()` covers our needs; `broadcastOn` + `broadcastWith` assertions are direct method calls on the event instance. No new dep needed |

**Installation:** No new packages. Phase 21 uses only what IRMS already has.

## Architecture Patterns

### System Architecture Diagram

```
MQTT Broker (Mosquitto)
        │  RecPush topic
        ▼
┌──────────────────────────┐
│  irms:mqtt-listen proc   │  (Supervisor, not Horizon)
│  TopicRouter             │
│  RecognitionHandler      │
│   1. Parse payload       │
│   2. Camera lookup       │
│   3. RecognitionEvent::  │──► INSERT (UNIQUE gate)
│      create (inline)     │
│   4. Persist images      │──► storage/app/private/fras_events/{date}/
│                          │
│ === NEW IN PHASE 21 ===  │
│   5. FrasIncidentFactory │
│      ::createFromRecog.  │
└──────────┬───────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────┐
│  FrasIncidentFactory::createFromRecognition($event)      │
│                                                           │
│  Gate 1 (severity):                                       │
│    Critical → proceed                                     │
│    Warning  → broadcast RecognitionAlertReceived, return  │
│    Info     → return (silent)                             │
│                                                           │
│  Gate 2 (confidence):                                     │
│    < threshold → return                                   │
│                                                           │
│  Gate 3 (personnel category):                             │
│    null / allow → return                                  │
│    block / missing / lost_child → proceed                 │
│                                                           │
│  Gate 4 (dedup — Redis atomic):                           │
│    Cache::add("fras:incident-dedup:{cam}:{person}", ...)  │
│    returns false → return                                 │
│                                                           │
│  Write path (DB transaction):                             │
│    - IncidentType lookup (cached)                         │
│    - Resolve priority from D-05 map                       │
│    - Incident::create(channel=IoT, priority=P1|P2, ...)   │
│    - IncidentTimeline::create(event_data.source=fras_rec) │
│    - $event->incident_id = $incident->id; $event->save()  │
│    - IncidentCreated::dispatch($incident)    [ShouldDispatchAfterCommit]
│    - RecognitionAlertReceived::dispatch($event, $incident)[ShouldDispatchAfterCommit]
└──────────┬───────────────────────────────────────────────┘
           │
           │ (both events deferred to after-commit)
           ▼
    ┌──────────────┐       ┌──────────────┐
    │ IncidentCrtd │       │ RecAlertRcvd │
    │ (existing)   │       │ (new)        │
    └──────┬───────┘       └──────┬───────┘
           │                      │
           │ dispatch.incidents   │ fras.alerts (private)
           ▼                      ▼
    ┌─────────────────────────────────────┐
    │  Reverb WebSocket server            │
    └─────────┬───────────────────┬───────┘
              │                   │
              ▼                   ▼
     ┌─────────────────┐   ┌────────────────────────────┐
     │ DispatchConsole │   │ IntakeStation              │
     │ useDispatchFeed │   │ useFrasRail / useFrasAlerts│
     │ (UNCHANGED —    │   │                            │
     │  Incident flows │   │ - Prepends ring buffer     │
     │  through here)  │   │ - Updates CREATED INCIDENT │
     │                 │   │   pill if incident_id set  │
     │ useFrasAlerts   │   └────────────────────────────┘
     │ (NEW — pulses   │
     │  map feature)   │   ┌────────────────────────────┐
     │  ↓              │   │ (future) /fras/alerts page │
     │ setFeatureState │   │ Phase 22                   │
     │  on cameras     │   └────────────────────────────┘
     │  source         │
     └─────────────────┘
```

**Key flow distinction from Phase 20:**
- Phase 20's `EnrollmentProgressed` broadcasts the personnel-side status change.
- Phase 21's `RecognitionAlertReceived` broadcasts an *event-centric* payload (no persistence ack loop, no correlation cache).

### Recommended Project Structure

```
app/
├── Services/
│   └── FrasIncidentFactory.php          # NEW (D-06)
├── Events/
│   └── RecognitionAlertReceived.php      # NEW (D-12)
├── Http/Controllers/
│   ├── IoTWebhookController.php          # MOD (D-09, thin delegate)
│   └── IntakeStationController.php       # MOD (D-18 prop, D-22 trigger field)
├── Mqtt/Handlers/
│   └── RecognitionHandler.php            # MOD (D-07, append factory call)
database/seeders/
└── PersonOfInterestIncidentTypeSeeder.php # NEW (D-02)
config/
└── fras.php                              # MOD (D-05, recognition section)
routes/
└── channels.php                          # MOD (D-11, fras.alerts)
resources/js/
├── composables/
│   ├── useDispatchMap.ts                 # MOD (D-14, pulseCamera export)
│   ├── useFrasAlerts.ts                  # NEW (D-13)
│   └── useFrasRail.ts                    # NEW (D-18) — or extend useIntakeFeed
├── components/
│   ├── intake/
│   │   ├── ChannelFeed.vue               # MOD (D-17, 6th rail)
│   │   ├── ChBadge.vue                   # MOD (D-17, ChannelKey union)
│   │   ├── FrasRailCard.vue              # NEW (UI-SPEC)
│   │   ├── FrasEventDetailModal.vue      # NEW (D-19)
│   │   └── icons/IntakeIconFras.vue      # NEW (D-17)
│   ├── fras/
│   │   └── FrasSeverityBadge.vue         # NEW (UI-SPEC)
│   └── incidents/
│       └── EscalateToP1Button.vue        # NEW (D-21)
└── pages/incidents/
    └── Show.vue                          # MOD (D-21, mount button)
tests/Feature/Fras/
├── FrasIncidentFactoryTest.php           # NEW (5 gates, 2 methods)
├── RecognitionAlertReceivedBroadcastTest.php  # NEW (payload + channel)
├── EscalateToP1Test.php                  # NEW (render conditions + audit)
├── IntakeStationFrasRailTest.php         # NEW (prop + Echo wiring + rail count)
└── RecognitionHandlerTest.php            # MOD (extend with factory call)
```

### Pattern 1: FrasIncidentFactory — gate-chain service

**What:** A single service class with two public methods. `createFromRecognition()` is a 5-gate sequential chain; each gate decides broadcast + return-null behavior.

**When to use:** Whenever business logic has multiple "reject / proceed" decision points and the reject path has side effects (e.g., broadcast warning, log, cache). Mirrors the v1.0 `AdminUnitController` thin-controller / service pattern.

**Example:**
```php
// app/Services/FrasIncidentFactory.php (shape — planner fills in)
namespace App\Services;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\IncidentCreated;
use App\Events\RecognitionAlertReceived;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Services\BarangayLookupService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class FrasIncidentFactory
{
    private ?IncidentType $personOfInterestType = null; // per-instance memoize

    public function __construct(private BarangayLookupService $barangayLookup) {}

    public function createFromSensor(array $validated, array $mapping, IncidentType $incidentType): Incident
    {
        // Factored verbatim from IoTWebhookController lines 56–92 (D-06):
        // Point construction, BarangayLookup, Incident::create, IncidentTimeline,
        // IncidentCreated::dispatch. Caller still owns validation + 422s.
        return DB::transaction(function () use ($validated, $mapping, $incidentType) {
            // ... existing body verbatim ...
        });
    }

    public function createFromRecognition(RecognitionEvent $event): ?Incident
    {
        // Gate 1: severity
        if ($event->severity !== RecognitionSeverity::Critical) {
            if ($event->severity === RecognitionSeverity::Warning) {
                RecognitionAlertReceived::dispatch($event, null); // null incident
            }
            return null; // Info: silent
        }

        // Gate 2: confidence
        $threshold = (float) config('fras.recognition.confidence_threshold', 0.75);
        if ($event->similarity < $threshold) {
            return null; // no broadcast (D-07 step 2)
        }

        // Gate 3: personnel category
        $personnel = $event->personnel_id ? Personnel::find($event->personnel_id) : null;
        if (! $personnel || $personnel->category === PersonnelCategory::Allow) {
            return null;
        }

        // Gate 4: dedup (atomic, Redis SETNX)
        $dedupKey = "fras:incident-dedup:{$event->camera_id}:{$event->personnel_id}";
        $ttl = (int) config('fras.recognition.dedup_window_seconds', 60);
        if (! Cache::add($dedupKey, true, $ttl)) {
            return null; // duplicate within window
        }

        // Write path inside DB::transaction — ShouldDispatchAfterCommit defers broadcasts
        $incident = DB::transaction(function () use ($event, $personnel) {
            $type = $this->personOfInterestType ??= IncidentType::where('code', 'person_of_interest')->firstOrFail();
            $priority = $this->resolvePriority($event->severity, $personnel->category);

            $camera = $event->camera; // assumed loaded; factory can eager-load
            $incident = Incident::create([
                'incident_type_id' => $type->id,
                'priority' => $priority,
                'status' => IncidentStatus::Pending,
                'channel' => IncidentChannel::IoT,
                'coordinates' => $camera->location, // PostGIS Point verbatim (D-04)
                'barangay_id' => $camera->barangay_id,
                'location_text' => $camera->name ?? $camera->camera_id_display,
                'notes' => $this->formatNotes($event, $personnel, $camera),
                'raw_message' => json_encode($event->raw_payload),
            ]);

            IncidentTimeline::create([
                'incident_id' => $incident->id,
                'event_type' => 'incident_created',
                'event_data' => [
                    'source' => 'fras_recognition',
                    'recognition_event_id' => $event->id,
                    'camera_id' => $event->camera_id,
                    'personnel_id' => $event->personnel_id,
                    'personnel_category' => $personnel->category->value,
                    'confidence' => $event->similarity,
                    'captured_at' => $event->captured_at->toIso8601String(),
                ],
            ]);

            $event->incident_id = $incident->id;
            $event->save();

            $incident->load('incidentType', 'barangay');
            IncidentCreated::dispatch($incident);
            RecognitionAlertReceived::dispatch($event, $incident);

            return $incident;
        });

        return $incident;
    }

    private function resolvePriority(RecognitionSeverity $severity, PersonnelCategory $category): IncidentPriority
    {
        $map = config('fras.recognition.priority_map', []);
        $code = $map[$severity->value][$category->value] ?? 'P2';
        return IncidentPriority::from($code);
    }

    private function formatNotes(RecognitionEvent $event, Personnel $personnel, \App\Models\Camera $camera): string
    {
        $labels = ['block' => 'Block-list match', 'missing' => 'Missing person sighting', 'lost_child' => 'Lost child sighting'];
        $label = $labels[$personnel->category->value] ?? 'Recognition match';
        $confidence = number_format($event->similarity * 100, 1);
        return "FRAS Alert: {$label} — {$personnel->name} matched on {$camera->camera_id_display} at {$confidence}% confidence";
    }
}
```

Sources: [CITED: laravel.com/docs/13.x/cache#storing-items-in-the-cache] (atomic `add`), CONTEXT D-06..D-08.

### Pattern 2: `RecognitionAlertReceived` event — ShouldDispatchAfterCommit

**What:** Broadcast event fired INSIDE the `DB::transaction` closure but deferred via `ShouldDispatchAfterCommit` — guarantees clients never receive an event before the row is visible in the DB.

**When to use:** Any broadcast event whose payload references a just-inserted row. This is the IRMS v1.0 convention (all 6 Phase 17 broadcast events implement `ShouldDispatchAfterCommit`).

**Example:**
```php
// app/Events/RecognitionAlertReceived.php
namespace App\Events;

use App\Models\Incident;
use App\Models\RecognitionEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RecognitionAlertReceived implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RecognitionEvent $event,
        public ?Incident $incident = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('fras.alerts')];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $camera = $this->event->camera;
        $personnel = $this->event->personnel;
        $coords = $camera->location;

        return [
            'event_id' => $this->event->id,
            'camera_id' => $this->event->camera_id,
            'camera_id_display' => $camera->camera_id_display,
            'camera_location' => $coords ? [$coords->getLongitude(), $coords->getLatitude()] : null,
            'severity' => $this->event->severity->value,
            'personnel_id' => $this->event->personnel_id,
            'personnel_name' => $personnel?->name,
            'personnel_category' => $personnel?->category?->value,
            'confidence' => $this->event->similarity,
            'captured_at' => $this->event->captured_at->toIso8601String(),
            'incident_id' => $this->incident?->id,
        ];
    }
}
```

**Ordering guarantee research:** When multiple `ShouldDispatchAfterCommit` events fire inside the same transaction, Laravel queues them and dispatches in original order once `COMMIT` succeeds. [CITED: laravel.com/docs/13.x/events] — "When multiple events are dispatched within a transaction, they are all queued and dispatched in the order they were dispatched once the transaction commits." This means `IncidentCreated` (dispatched first in the factory) fires before `RecognitionAlertReceived`, but both carry the final `incident_id` because they serialize the model AFTER the commit (the model instance captured at dispatch time is queried/refreshed when `broadcastWith` runs).

**Critical detail:** `SerializesModels` trait re-queries the model at the time of broadcasting, not at the time of dispatching. So both events see the persisted Incident row. This is why the factory can dispatch both inside the transaction without race.

### Pattern 3: Mapbox feature-state pulse

**What:** Call `map.setFeatureState({source: 'cameras', id: cameraId}, {pulsing: true, pulse_severity: severity})`. Paint expressions on `camera-body` + `camera-halo` layers use `['feature-state', 'pulsing']` and `['feature-state', 'pulse_severity']` in `case` expressions.

**When to use:** Any time a GeoJSON feature needs fast, runtime-variable styling without re-parsing geometry. GPU-accelerated; 60fps at bursts.

**Critical detail — UUID compatibility:** Mapbox GL JS natively requires numeric IDs for `setFeatureState`. STRING IDs (like IRMS camera UUIDs) are silently dropped unless the source uses `promoteId`. IRMS already does this correctly:

```javascript
// resources/js/composables/useDispatchMap.ts:346-350 (SHIPPED)
map.value.addSource('cameras', {
    type: 'geojson',
    data: currentCameraData,
    promoteId: 'id',  // ← THE LOAD-BEARING DETAIL
});
```

[CITED: github.com/mapbox/mapbox-gl-js/pull/8987] — "promoteId allows using a feature property as an ID for feature state, enabling reference to features by meaningful keys rather than just numeric IDs." [CITED: github.com/mapbox/mapbox-gl-js/issues/7986] — String UUIDs work only when `promoteId` is set.

**Example (planner integrates into existing `useDispatchMap.ts`):**

```typescript
// Paint expressions — modify EXISTING camera-body + camera-halo layer paint
// (lines 394-420 area). Keep existing structure, replace literal values with
// case expressions.

// camera-body layer — icon-size:
'icon-size': [
    'case',
    ['boolean', ['feature-state', 'pulsing'], false],
    0.88,   // pulsing (UI-SPEC: ~60% larger than default)
    0.55,   // default (shipped Phase 20)
],

// camera-halo layer — circle-radius, circle-color, circle-opacity:
'circle-radius': [
    'case',
    ['boolean', ['feature-state', 'pulsing'], false],
    32,     // pulsing
    18,     // default (shipped Phase 20)
],
'circle-color': [
    'case',
    ['==', ['feature-state', 'pulse_severity'], 'critical'], '#A32D2D',
    ['==', ['feature-state', 'pulse_severity'], 'warning'],  '#EF9F27',
    // fallback to existing status color match (Phase 20 CAMERA_STATUS_COLORS):
    ['match', ['get', 'status'],
        'online', '#1D9E75',
        'degraded', '#EF9F27',
        'offline', '#6B7280',
        '#6B7280'],
],
'circle-opacity': [
    'case',
    ['boolean', ['feature-state', 'pulsing'], false],
    0.35,
    0.15,  // shipped Phase 20
],

// New exported function:
const pulseTimeouts = new Map<string, number>();

function pulseCamera(cameraId: string, severity: 'critical' | 'warning'): void {
    if (!map.value || !map.value.getSource('cameras')) return;

    // Reset any prior pulse for this camera (D-15: re-trigger resets timer)
    const prior = pulseTimeouts.get(cameraId);
    if (prior !== undefined) {
        window.clearTimeout(prior);
    }

    map.value.setFeatureState(
        { source: 'cameras', id: cameraId },
        { pulsing: true, pulse_severity: severity },
    );

    const durationMs = frasPulseDurationSeconds.value * 1000;
    const timeoutId = window.setTimeout(() => {
        map.value?.setFeatureState(
            { source: 'cameras', id: cameraId },
            { pulsing: false, pulse_severity: null },
        );
        pulseTimeouts.delete(cameraId);
    }, durationMs);

    pulseTimeouts.set(cameraId, timeoutId);
}

// Export in the return object:
return {
    // ... existing exports ...
    setCameraData,
    pulseCamera,  // NEW
};
```

**Handling alerts arriving for unloaded cameras:** Guard with `map.getSource('cameras')` + optional `map.querySourceFeatures({source: 'cameras'})` to verify the specific feature exists. If the feature is absent (camera loaded after the alert), `setFeatureState` silently no-ops — acceptable behavior per CONTEXT (CDRRMO scale: ≤8 cameras, all present on page load in practice). No queue-and-replay needed. [CITED: github.com/mapbox/mapbox-gl-js/issues/7758] — `setFeatureState` does not error on missing IDs when `promoteId` is configured correctly; it is a no-op.

Sources: [CITED: docs.mapbox.com/mapbox-gl-js/style-spec/expressions/] (case/feature-state expression), [CITED: blog.mapbox.com/going-live-with-electoral-maps-a-guide-to-feature-state] (performance rationale).

### Pattern 4: `useFrasAlerts` composable (Echo private channel)

**What:** A thin Vue composable that subscribes to `fras.alerts`, cleans up on unmount automatically, and invokes `pulseCamera` on received events.

**When to use:** On DispatchConsole page only. For other surfaces (IntakeStation rail), write a sibling composable (`useFrasRail`) that binds the same channel to a different consumer.

**Example:**
```typescript
// resources/js/composables/useFrasAlerts.ts
import { useEcho } from '@laravel/echo-vue';
import type { useDispatchMap } from '@/composables/useDispatchMap';
import type { RecognitionAlertPayload } from '@/types/fras';

export function useFrasAlerts(mapRef: ReturnType<typeof useDispatchMap>): void {
    // Respect prefers-reduced-motion for pulse duration (UI-SPEC accessibility)
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    // Manual cleanup NOT needed — useEcho handles unmount automatically
    useEcho<RecognitionAlertPayload>(
        'fras.alerts',
        'RecognitionAlertReceived',
        (payload) => {
            if (payload.severity === 'critical' || payload.severity === 'warning') {
                mapRef.pulseCamera(payload.camera_id, payload.severity);
            }
            // Info severity never arrives here (D-16 — factory does not dispatch)
        },
    );
}
```

**Key API features of `useEcho` (2.3.4):**
- Auto-subscribe on mount, auto-leave on unmount [CITED: laravel.com/docs/13.x/broadcasting]
- Generic typing: `useEcho<PayloadT>('channel', 'EventName', handler)`
- Returns `{ channel, leaveChannel, leave, stopListening, listen }` for manual control (rarely needed)
- Private channel auth happens via Laravel's broadcasting auth endpoint automatically
- Reconnection is automatic; observable via `useConnectionStatus()`
- The event name in the second argument matches the PHP class short-name (`RecognitionAlertReceived`) unless you override with `broadcastAs()`

**Connection status (if Phase 22 needs a health banner — not Phase 21 scope per UI-SPEC):**
```typescript
import { useConnectionStatus } from '@laravel/echo-vue';
const status = useConnectionStatus(); // 'connected' | 'connecting' | 'reconnecting' | 'disconnected' | 'failed'
```

Source: [CITED: laravel.com/docs/13.x/broadcasting — useEcho section], [CITED: npmjs.com/package/@laravel/echo-vue], `resources/js/composables/useEnrollmentProgress.ts` (shipped Phase 20 reference).

### Pattern 5: Inertia prop + Echo hydration merge (ring buffer)

**What:** SSR-seed the rail from `recentFrasEvents` Inertia prop on page load; then `useEcho` prepends new events to a client-side ring buffer capped at 50.

**When to use:** Any operator-facing rail / feed / ticker that needs both "recent history on arrival" and "live updates while viewing." Matches v1.0 `DispatchConsole.vue` + Phase 20 `EnrollmentProgressPanel.vue` idiom.

**Example shape:**
```typescript
// resources/js/composables/useFrasRail.ts
import { useEcho } from '@laravel/echo-vue';
import { ref, type Ref } from 'vue';
import type { FrasRailEvent, RecognitionAlertPayload } from '@/types/fras';

const MAX_RAIL_EVENTS = 50;

export function useFrasRail(initialEvents: FrasRailEvent[]): {
    events: Ref<FrasRailEvent[]>;
    frasCount: Ref<number>;
} {
    const events = ref<FrasRailEvent[]>([...initialEvents]);

    useEcho<RecognitionAlertPayload>(
        'fras.alerts',
        'RecognitionAlertReceived',
        (payload) => {
            // Find existing event — factory dispatches RecognitionAlertReceived
            // with incident_id populated AFTER the Incident create. If the rail
            // already has the card (e.g., from SSR seed), just update incident_id.
            const existingIdx = events.value.findIndex((e) => e.event_id === payload.event_id);
            if (existingIdx !== -1) {
                events.value[existingIdx] = {
                    ...events.value[existingIdx],
                    incident_id: payload.incident_id,
                };
                return;
            }

            // Otherwise prepend new event (ring buffer)
            events.value.unshift({
                event_id: payload.event_id,
                severity: payload.severity,
                camera_label: payload.camera_id_display,
                personnel_name: payload.personnel_name,
                personnel_category: payload.personnel_category,
                confidence: payload.confidence,
                captured_at: payload.captured_at,
                incident_id: payload.incident_id,
                face_image_path: null,  // face_image_path arrives only in SSR prop; broadcast payload does not ship it (Phase 22 signed-URL scope)
            });

            // Evict tail if over cap
            if (events.value.length > MAX_RAIL_EVENTS) {
                events.value.length = MAX_RAIL_EVENTS;
            }
        },
    );

    return {
        events,
        frasCount: ref(events.value.length),  // computed() in real impl for reactivity
    };
}
```

**Why this pattern:** The SSR prop delivers the last 50 events instantly — operator doesn't see an empty rail on first paint. Echo then keeps the state live. No AJAX pagination needed because 50 events covers several hours at CDRRMO cadence. Phase 22 `/fras/events` handles full-history search.

**Face thumbnail caveat:** The SSR prop carries `face_image_path` (disk path); the controller-side signed-URL helper (per UI-SPEC D-20 recommendation — Phase 20 D-22 pattern) converts these to 5-min TTL URLs. Echo payloads do NOT carry `face_image_path` (URL TTL too short for the 50-event buffer). New-arrival rail cards render the `IntakeIconFras` placeholder until operator clicks through or page refreshes (acceptable degradation for live events; Phase 22 polish can add a per-click signed-URL lazy-fetch if needed).

### Pattern 6: Pest broadcast testing

**What:** Use Laravel's built-in `Event::fake([RecognitionAlertReceived::class])` to prevent real WebSocket broadcast in tests, then assert dispatch count + payload shape via `Event::assertDispatched()`.

**When to use:** Every broadcast event test. Matches v1.0 Pest patterns; no third-party package needed.

**Example:**
```php
// tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php
<?php

use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\RecognitionAlertReceived;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Services\FrasIncidentFactory;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

it('broadcasts on fras.alerts private channel with full denorm payload', function () {
    Event::fake([RecognitionAlertReceived::class]);

    $camera = Camera::factory()->create();
    $personnel = Personnel::factory()->create(['category' => PersonnelCategory::Block]);
    $event = RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Critical,
        'similarity' => 0.85,
    ]);

    app(FrasIncidentFactory::class)->createFromRecognition($event);

    Event::assertDispatched(RecognitionAlertReceived::class, function (RecognitionAlertReceived $e) use ($event, $camera, $personnel) {
        // Channel assertion
        $channels = $e->broadcastOn();
        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('private-fras.alerts');

        // Payload assertion
        $payload = $e->broadcastWith();
        return $payload['event_id'] === $event->id
            && $payload['camera_id'] === $camera->id
            && $payload['camera_id_display'] === $camera->camera_id_display
            && $payload['severity'] === 'critical'
            && $payload['personnel_id'] === $personnel->id
            && $payload['personnel_name'] === $personnel->name
            && $payload['personnel_category'] === 'block'
            && $payload['confidence'] === 0.85
            && is_string($payload['captured_at'])
            && $payload['incident_id'] !== null; // created on Critical path
    });
});

it('broadcasts Warning severity with null incident_id', function () {
    Event::fake([RecognitionAlertReceived::class]);

    $event = RecognitionEvent::factory()->create([
        'severity' => RecognitionSeverity::Warning,
        'similarity' => 0.80,
    ]);

    app(FrasIncidentFactory::class)->createFromRecognition($event);

    Event::assertDispatched(RecognitionAlertReceived::class, fn ($e) =>
        $e->broadcastWith()['severity'] === 'warning'
        && $e->broadcastWith()['incident_id'] === null
    );
});

it('does NOT broadcast Info severity', function () {
    Event::fake([RecognitionAlertReceived::class]);

    $event = RecognitionEvent::factory()->create([
        'severity' => RecognitionSeverity::Info,
    ]);

    app(FrasIncidentFactory::class)->createFromRecognition($event);

    Event::assertNotDispatched(RecognitionAlertReceived::class);
});
```

**Channel auth testing** — verify private-channel gate:
```php
// tests/Feature/Fras/FrasAlertsChannelAuthTest.php
it('authorizes operators, dispatchers, supervisors, admins on fras.alerts', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $authorized = Broadcast::channel('fras.alerts', fn () => true); // route definition check
    $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
        'channel_name' => 'private-fras.alerts',
        'socket_id' => '123.456',
    ]);

    expect($response->status())->toBe(200);
})->with([UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin]);

it('rejects responders on fras.alerts', function () {
    $user = User::factory()->create(['role' => UserRole::Responder]);
    $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
        'channel_name' => 'private-fras.alerts',
        'socket_id' => '123.456',
    ]);
    expect($response->status())->toBe(403);
});
```

Sources: [CITED: laravel.com/docs/12.x/broadcasting], `tests/Feature/Fras/` Phase 20 precedents.

### Anti-Patterns to Avoid

- **Calling `Event::fake()` without args** — fakes ALL events and silently prevents `IncidentCreated` broadcast, which may hide factory bugs. Always pass `[RecognitionAlertReceived::class]` explicitly when asserting that specific event.
- **Using `Cache::lock()` for dedup** — overkill. `Cache::add` is already atomic; a lock requires explicit `->get()`/`->release()` and adds complexity.
- **Polling camera presence before `setFeatureState`** — `setFeatureState` on a missing feature-id is a silent no-op. Trying to "wait for load" adds timing bugs. Just call and accept the no-op for cameras loaded after page paint (CONTEXT accepts this tradeoff).
- **Broadcasting from inside `RecognitionHandler::handle()` directly** — violates the factory-owns-broadcast contract (D-10). The factory is the single source of truth for `IncidentCreated` AND `RecognitionAlertReceived`.
- **Forgetting `promoteId: 'id'`** — WOULD break pulse entirely. IRMS's Phase 20 code already has this; do not remove it.
- **Using `feature.id` before setting `promoteId`** — Mapbox silently drops string UUIDs from the numeric ID slot. The `promoteId` tells Mapbox to read feature.properties.id and use it as the state lookup key.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Atomic dedup across processes | Custom Redis SETNX wrapper, DB advisory lock, or composite UNIQUE constraint + try/catch | `Cache::add($key, true, $ttl)` | Already atomic via Redis SET NX; one call; returns bool; no cleanup job |
| Broadcast event DB-commit ordering | `register_shutdown_function`, custom post-commit hook, manual `DB::afterCommit` | `implements ShouldDispatchAfterCommit` on the event class | First-party Laravel; handles rollback (event never fires) + ordering |
| Echo subscription cleanup on unmount | Manual `onUnmounted(() => channel.leave())` | `useEcho` composable | Auto-cleanup; also handles hot-reload correctly |
| Map feature pulse animation | DOM overlay + CSS keyframes + position recalc on pan/zoom | Mapbox `feature-state` + paint case expression | GPU-accelerated; attached to feature; survives zoom/pan/tilt |
| UUID-keyed feature state | Manual string→int hashing, numeric ID column on cameras | Mapbox GeoJSON `promoteId: 'id'` source option | First-party Mapbox; already configured Phase 20 |
| Wayfinder regeneration after schema change | Hand-editing `resources/js/actions/*` | `npm run build` (Vite plugin runs `wayfinder:generate` automatically) | Ships as `@laravel/vite-plugin-wayfinder` |
| TypeScript typing of Inertia pages | Custom type declarations for every prop | Wayfinder `actions` typing + page-level `defineProps<{}>()` | Inertia + Wayfinder already synced |
| Load testing recognition burst | Raw MQTT publisher + ad-hoc Python script | `mosquitto_pub` in a loop (or `pytest-mqtt`) + Mapbox built-in FPS overlay (`?debug=tileboundaries` or browser DevTools → Performance → FPS meter) | Standard tooling; no new dep |

**Key insight:** Every HOW-to-implement concern in Phase 21 has a first-party Laravel or Mapbox mechanism. The planner should NOT add new libraries. Every custom mechanism (race-safe dedup, post-commit dispatch, feature styling) already exists.

## Common Pitfalls

### Pitfall 1: `setFeatureState` silently fails for UUID features without `promoteId`

**What goes wrong:** Pulse animation never fires. Console shows no error. Feature-state never applies.

**Why it happens:** Mapbox GL JS's vector-tile spec requires feature IDs to be numeric (or numeric-castable strings). UUID strings like `0198f3a2-b7b2-7c8e-8b3a-abcdef012345` are dropped during GeoJSON parsing — the feature still renders, but `feature.id` is undefined. `setFeatureState({id: uuid})` then has no target to apply state to.

**How to avoid:** Confirm the `cameras` source has `promoteId: 'id'` (IRMS already does at line 349 of `useDispatchMap.ts`). If you ever change the source definition, preserve this option. Add a Pest-equivalent guard: a Dusk/Vitest test that verifies `map.getFeatureState({source: 'cameras', id: <uuid>})` returns the expected state after `setFeatureState`.

**Warning signs:** `setFeatureState` returns `undefined` (normal — it's a void function, but the side effect doesn't take). Paint doesn't change. Reloading does not help. `querySourceFeatures` shows features but with `feature.id === undefined`.

Source: [CITED: github.com/mapbox/mapbox-gl-js/issues/7986]

### Pitfall 2: Events dispatched inside transaction before commit

**What goes wrong:** A subscriber receives `IncidentCreated` and queries `Incident::find($id)` → returns null because the transaction hasn't committed yet.

**Why it happens:** Without `ShouldDispatchAfterCommit`, events fire immediately on `dispatch()`. Inside a transaction, the row exists in the transaction-local snapshot but is invisible to other connections/processes.

**How to avoid:** All broadcast events in Phase 21 MUST `implements ShouldDispatchAfterCommit`. Both shipped examples (`IncidentCreated.php`, `CameraStatusChanged.php`) already do. The factory dispatches both inside a `DB::transaction()` and relies on deferred dispatch. If the transaction rolls back, neither event fires — exactly the desired behavior.

**Warning signs:** Intermittent test failures where a broadcast subscriber queries the DB and gets stale data. Reverb serves an event that references an ID that 404s on the HTTP API moments later.

Source: [CITED: laravel.com/docs/13.x/events], [VERIFIED: `app/Events/IncidentCreated.php` line 13]

### Pitfall 3: `@laravel/echo-vue` event name mismatch

**What goes wrong:** Channel subscribes, payload never arrives. No errors in console.

**Why it happens:** Laravel broadcasts events under the fully-qualified class name by default (`App\\Events\\RecognitionAlertReceived`), but `useEcho`'s `EventName` argument uses the short name. If you use one convention on the backend (`broadcastAs('RecognitionAlertReceived')`) and the other on the frontend (`'App.Events.RecognitionAlertReceived'`), the subscription never matches. Echo auto-strips leading backslashes and the App namespace so short-names usually work, but `broadcastAs()` overrides this.

**How to avoid:** Do NOT define `broadcastAs()` on `RecognitionAlertReceived`. Use the default Laravel behavior: backend dispatches as `RecognitionAlertReceived`, frontend listens for `'RecognitionAlertReceived'`. Verify by running Reverb with debug logging enabled and tailing `storage/logs/laravel.log` for "broadcast" entries.

**Warning signs:** Channel subscription succeeds (auth endpoint returns 200), but the callback never fires. `window.Echo.connector.channels[channelName].subscription.bindings` shows no matching binding.

Source: [CITED: npmjs.com/package/@laravel/echo-vue]

### Pitfall 4: Cache::add TTL not respected on cache driver mismatch

**What goes wrong:** Dedup window effectively becomes "forever" or "until cache flush." A repeat event 2 minutes later is still rejected.

**Why it happens:** `Cache::add` on the `file` or `array` driver does not respect TTL semantics as reliably as Redis. IRMS uses Redis (verified `predis/predis` in composer.json), but in CI/local test environments with SQLite + in-memory cache, TTL can surprise. Also, `CACHE_STORE=database` or `CACHE_STORE=array` skipped over in testing.

**How to avoid:** Tests that exercise the dedup path MUST use Redis (test Redis instance or mock). `FrasIncidentFactoryTest` should set `Cache::store('redis')` explicitly if the default isn't Redis in test env. Alternatively, use `Cache::shouldReceive('add')->andReturn(true/false)` for unit-level assertions on gate flow.

**Warning signs:** Feature test "second event within window" fails inconsistently across local/CI. Cache inspector shows key with no TTL.

### Pitfall 5: Wayfinder not regenerating after controller signature change

**What goes wrong:** TypeScript `overridePriority(incident).form()` call lacks the new `trigger` field. ESLint fails in CI.

**Why it happens:** Wayfinder regeneration runs via the Vite plugin (`@laravel/vite-plugin-wayfinder`) on `npm run build` + `npm run dev`. If the planner only runs `php artisan test` (which doesn't build Vite assets), Wayfinder output stays stale. Also, optional fields added via `['sometimes', 'in:...']` ARE picked up by Wayfinder automatically — it reads FormRequest rules + controller `$request->validate()` calls.

**How to avoid:** After modifying `IntakeStationController::overridePriority()`:
1. Run `php artisan wayfinder:generate` explicitly, OR
2. Run `npm run build` (CI flow, runs generator as plugin)
3. Verify `resources/js/actions/App/Http/Controllers/IntakeStationController.ts` shows the new `trigger?: string` form-field type.

Because `resources/js/actions/` is in ESLint ignore list (per `.eslintrc` / project config: "ESLint ignores auto-generated dirs: `resources/js/actions/`"), regeneration won't fail lint. The Escalate button's form binding will pick up the new field the moment TypeScript types refresh.

**Warning signs:** `vue-tsc --noEmit` fails with "Property 'trigger' does not exist on type...". Escalate form still sends only `priority`, no `trigger`.

Source: [CITED: github.com/laravel/wayfinder]

### Pitfall 6: Reverb unconfigured for burst load (if SC6 stress-tests)

**What goes wrong:** At 50 events/sec during a load test, Reverb drops WebSocket messages or `@laravel/echo-vue` client reports `reconnecting`.

**Why it happens:** Reverb does NOT have built-in rate limiting for WebSocket messages (confirmed per laravel/reverb#307). Under bursty load, PHP worker CPU becomes the bottleneck, not the WebSocket protocol. Default Reverb runs single-process; needs scaling to multi-process via `--workers` or horizontal scaling via Redis pub/sub coordinator.

**How to avoid:** For SC6 50-events/sec validation:
1. Start Reverb with `php artisan reverb:start --workers=4` (or env: `REVERB_SCALING_ENABLED=true` with Redis) for the test run.
2. Use `config/reverb.php` → `scaling` block if production expects sustained burst load.
3. The test itself is at a small scale — 50/sec is well within single-worker Reverb on a modern CPU. It's a worth-measuring baseline, not a stress test.

**Warning signs:** Echo reconnect events in console. Some pulse animations skipped. Reverb CPU saturates one core.

Source: [CITED: laravel.com/docs/13.x/reverb], [CITED: github.com/laravel/reverb/issues/307]

### Pitfall 7: `IncidentCreated` payload shape drift

**What goes wrong:** Dispatch console's existing `useDispatchFeed.ts` breaks when a recognition-originated Incident arrives — the payload has a new field or different shape.

**Why it happens:** If the factory accidentally loads extra relationships that `IncidentCreated::broadcastWith()` doesn't account for, or modifies `IncidentCreated` itself, the Phase 17 broadcast snapshot breaks. FRAMEWORK-02 gates on this.

**How to avoid:** Do NOT modify `app/Events/IncidentCreated.php`. The factory calls `$incident->load('incidentType', 'barangay')` BEFORE dispatch (matching v1.0 convention at IoTWebhookController line 91). `broadcastWith` uses only these relationships and scalar columns — verified byte-identical output.

**Warning signs:** `FrasIncidentFactoryTest` passes but `useDispatchFeed` unit test fails, or v1.0 IoT webhook tests regression.

### Pitfall 8: Dedup key collision across camera/personnel pairs

**What goes wrong:** Two different personnel matched at the same camera within 60s — only the first creates an incident.

**Why it happens:** If dedup key uses only `camera_id` (not both IDs), unrelated recognitions collide. Reading D-08 carefully: key is `fras:incident-dedup:{camera_id}:{personnel_id}` — both IDs.

**How to avoid:** Literal key structure per CONTEXT D-08. Test case: two different personnel_ids at same camera within 60s → both create incidents.

**Warning signs:** Test "different personnel at same camera within window" fails and reports only 1 incident.

## Code Examples

### Channel authorization (additive to `routes/channels.php`)

```php
// routes/channels.php — ADD after line 19 (fras.cameras)
Broadcast::channel('fras.alerts', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});
```

Rationale: `$dispatchRoles` is already declared at the top of the file as `[UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin]` — exactly the roles CONTEXT D-11 specifies. Responders are excluded because they're not in `$dispatchRoles` [VERIFIED: `routes/channels.php` lines 7, 17].

### Config addition (additive to `config/fras.php`)

```php
// config/fras.php — ADD a new 'recognition' section
return [
    // ... existing sections (mqtt, cameras, enrollment, photo, events) ...

    'recognition' => [
        'confidence_threshold' => (float) env('FRAS_CONFIDENCE_THRESHOLD', 0.75),
        'dedup_window_seconds' => (int) env('FRAS_DEDUP_WINDOW_SECONDS', 60),
        'pulse_duration_seconds' => (int) env('FRAS_PULSE_DURATION_SECONDS', 3),
        'priority_map' => [
            'critical' => [
                'block' => env('FRAS_PRIORITY_CRITICAL_BLOCK', 'P2'),
                'missing' => env('FRAS_PRIORITY_CRITICAL_MISSING', 'P2'),
                'lost_child' => env('FRAS_PRIORITY_CRITICAL_LOST_CHILD', 'P1'),
            ],
        ],
    ],
];
```

### Inertia shared prop for `pulseDurationSeconds`

```php
// app/Http/Middleware/HandleInertiaRequests.php — extend share() array
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // ... existing props ...
        'frasConfig' => [
            'pulseDurationSeconds' => (int) config('fras.recognition.pulse_duration_seconds', 3),
        ],
    ]);
}
```

Then in `DispatchConsole.vue`:
```typescript
import { usePage } from '@inertiajs/vue3';
const page = usePage<{ frasConfig: { pulseDurationSeconds: number } }>();
const pulseDurationSeconds = computed(() => page.props.frasConfig.pulseDurationSeconds);
```

### Escalate button form submission (Inertia + Wayfinder)

```vue
<!-- resources/js/components/incidents/EscalateToP1Button.vue -->
<script setup lang="ts">
import { TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import IntakeStationController from '@/actions/App/Http/Controllers/IntakeStationController';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { Incident } from '@/types/incident';

const props = defineProps<{ incident: Incident }>();

const showButton = computed(() =>
    props.incident.timeline?.[0]?.event_data?.source === 'fras_recognition'
    && props.incident.priority !== 'P1',
);

const form = useForm({
    priority: 'P1' as const,
    trigger: 'fras_escalate_button' as const,
});

function escalate(): void {
    form.post(IntakeStationController.overridePriority(props.incident.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Tooltip v-if="showButton">
        <TooltipTrigger as-child>
            <Button
                variant="destructive"
                :disabled="form.processing"
                aria-label="Escalate incident to priority P1"
                @click="escalate"
            >
                <TrendingUp class="mr-2 size-4" />
                {{ form.processing ? 'Escalating…' : 'Escalate to P1' }}
            </Button>
        </TooltipTrigger>
        <TooltipContent>
            Raise priority to P1 and notify dispatcher. Required for supervisor approval.
        </TooltipContent>
    </Tooltip>
</template>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `Event::dispatch()` immediately on model save | `ShouldDispatchAfterCommit` interface | Laravel 10.30 (Oct 2023) | Removes stale-read race; available first-party in L13 |
| Mapbox DOM marker overlays | Symbol layer + `feature-state` | Mapbox GL JS 0.50+ (2018+) | GPU-accelerated; 60fps at scale; survives zoom/pan |
| UUID → `feature.id` requires custom int hashing | `promoteId: 'id'` source option | mapbox-gl-js 1.10+ (2020) | No hashing; works out-of-box with string IDs |
| Laravel Echo Vue with manual `onMounted`/`onUnmounted` subscribe/leave | `@laravel/echo-vue` with `useEcho` composable | Echo 2.1 (Jul 2025) | Auto-cleanup; less boilerplate; TypeScript generics |
| `mapbox-gl` → `maplibre-gl` community fork migration | Retained `mapbox-gl` 3.x | IRMS v2.0 milestone (2026-04-21) | Keeps Phase 20+ camera layer + Studio style + geocoding API |

**Deprecated/outdated:**
- `vue-echo-laravel` package (npm) — pre-Echo 2.1 era, superseded by `@laravel/echo-vue`
- Manual DB advisory locks for dedup — `Cache::add` does it atomically without DB trip
- Third-party `laravel-broadcast-testing` package — Laravel's native `Event::fake` is sufficient for payload + channel assertions

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `Cache::add` uses Redis `SET NX EX` under the hood when the driver is `redis` | Pattern 1 Gate 4 | Low — even `SETNX` alone plus a subsequent `EXPIRE` would be atomic enough given 60s-granularity dedup. Verification: inspect `vendor/illuminate/cache/RedisStore.php::add()` implementation if needed. [CITED: laravel.com/docs/13.x/cache — confirmed atomic; SETNX/SET NX implementation detail not explicitly documented but implied] |
| A2 | Reverb single-worker handles 50 events/sec without backpressure | Pitfall 6 | Low — 50 events/sec is ~20ms/event CPU budget, well within PHP worker throughput. Measured baseline would confirm. |
| A3 | `@laravel/echo-vue` v2.3.1 installed is feature-compatible with v2.3.4 docs | Pattern 4 | Very low — patch version only; useEcho shape stable since 2.1 |
| A4 | Inertia partial reload after Escalate-to-P1 correctly re-renders `incidents/Show.vue` with new priority, removing the button via its `computed()` condition | Pattern 6 / UI-SPEC | Low — standard Inertia v2 idiom used throughout v1.0; no known regressions |
| A5 | `RecognitionEvent::factory` exists or will be created for FRAS tests | Pattern 6 test examples | Low — Phase 18 D-61 mentions `RecognitionEventFactory`; planner should verify it emits both `personName` + `persionName` spellings and can set `severity`, `similarity` fields |
| A6 | Mapbox `setFeatureState` is a no-op (not a throw) when the feature id is not present in the source | Pitfall 1 / Pattern 3 | Low — confirmed via issue #7758 thread; matches shipped Phase 20 camera toggle behavior |

**All other claims in this document are VERIFIED against installed code, npm registry, or official Laravel 13 / Mapbox GL JS / @laravel/echo-vue docs.**

## Open Questions

1. **Does `RecognitionEvent::factory` already emit realistic `similarity` values?**
   - What we know: Phase 18 D-61 created the factory to emit both firmware spellings of `personName`.
   - What's unclear: Whether the factory has states for `similarity=0.8` vs `0.3`, or if tests must `->state([...])` each case.
   - Recommendation: Planner verifies; if missing, add `highConfidence()` / `lowConfidence()` states as part of `FrasIncidentFactoryTest` setup.

2. **Face image signed-URL strategy for rail thumbnails (D-20)**
   - What we know: CONTEXT recommends option (a): signed route now per Phase 20 D-22 pattern. UI-SPEC ratifies this.
   - What's unclear: Whether the route already exists (Phase 20 D-22 created `admin.personnel.photo` for personnel avatars — a DIFFERENT use case than per-RecognitionEvent face crops).
   - Recommendation: Planner creates a NEW signed route `fras.event.face` for RecognitionEvent face crops — parallel to the personnel photo route — served by a NEW `FrasEventFaceController`. Phase 21 version emits a signed URL; Phase 22 wires `fras_access_log` on the same controller. This avoids Phase 22 retrofitting around an image-rendering rail.

3. **Does `IntakeStationController::show()` currently render the 6th FRAS rail even when `recentFrasEvents` is empty (e.g., pre-FRAS deployment or all-cameras-offline period)?**
   - What we know: UI-SPEC declares an empty-state card ("No FRAS events...").
   - What's unclear: Whether the empty-state counts as 0 in `channelCounts['FRAS']` and whether the rail's bar-fill animation handles zero gracefully.
   - Recommendation: Test case in `IntakeStationFrasRailTest`: rail renders with 0 events, `channelCounts.FRAS === 0`, empty-state card appears, click behavior still works.

4. **Reverb scaling settings for production**
   - What we know: Single-worker Reverb is default; `REVERB_SCALING_ENABLED=true` uses Redis pub/sub.
   - What's unclear: Whether CDRRMO deployment has Redis scaling configured or runs single-worker.
   - Recommendation: Phase 21 does NOT change this. Load-test on dev machine first (single-worker should handle 50/sec easily); if CDRRMO's production reports backpressure during a burst, Phase 22 or an operations-only issue enables scaling.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Laravel 13 | ✓ | 8.4 | — |
| Laravel 13 | All new code | ✓ | ^13.0 | — |
| Redis (Predis) | `Cache::add` dedup | ✓ | predis/predis ^3.4 | None — required |
| Reverb | Broadcast delivery | ✓ | ^1.10 | None — required |
| Horizon | Queue (fras-supervisor already registered) | ✓ | ^5.45.6 | — |
| Mosquitto broker | Recognition ingestion (dev + test) | ✓ assumed (Phase 19 SC6) | 2.0.x | None — dev prereq |
| mapbox-gl | Dispatch map pulse | ✓ | ^3.20.0 | None — locked v2.0 milestone |
| @laravel/echo-vue | Rail + map composable subscriptions | ✓ | ^2.3.1 | None — required |
| PostgreSQL + PostGIS | FRAS tests (FRAMEWORK-05) | ✓ assumed per Phase 17 CI | — | Tests fail if only SQLite; fail-loud |

**Missing dependencies with no fallback:** None. All infrastructure shipped in Phase 17–20.

**Missing dependencies with fallback:** None.

## Validation Architecture

**Nyquist validation enabled** (no config override found; default is enabled).

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4.6 (pestphp/pest-plugin-laravel 4.1) |
| Config file | `phpunit.xml` (Pest uses it for database config) |
| Quick run command | `php artisan test --compact --filter={test}` |
| Full suite command | `php artisan test --compact` |
| FRAS-specific (Postgres required) | Tests in `tests/Feature/Fras/` run against Postgres per FRAMEWORK-05 |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| RECOGNITION-01 | Every RecPush persists to `recognition_events` regardless of severity | Feature | `php artisan test --compact --filter=RecognitionHandlerTest` | Wave 0: extend existing (Phase 19 shipped) |
| RECOGNITION-02 | Critical recognition → Incident with correct channel/priority/timeline | Feature | `php artisan test --compact --filter=FrasIncidentFactoryTest::it_creates_incident_on_critical_block_match` | ❌ Wave 0 |
| RECOGNITION-03 | IoTWebhookController refactor preserves existing IoT sensor tests | Feature | `php artisan test --compact --filter=IoTWebhookControllerTest` | Wave 0: must pass UNCHANGED (gate) |
| RECOGNITION-04 | Escalate-to-P1 button renders + submits + audits | Feature + component | `php artisan test --compact --filter=EscalateToP1Test` | ❌ Wave 0 |
| RECOGNITION-05 | Warning severity broadcasts but no Incident | Feature | `php artisan test --compact --filter=FrasIncidentFactoryTest::it_broadcasts_warning_without_creating_incident` | ❌ Wave 0 |
| RECOGNITION-06 | Dedup within 60s for same (camera,personnel) pair | Feature | `php artisan test --compact --filter=FrasIncidentFactoryTest::it_dedups_within_window` | ❌ Wave 0 |
| RECOGNITION-07 | Below-threshold confidence → no broadcast, no Incident | Feature | `php artisan test --compact --filter=FrasIncidentFactoryTest::it_skips_below_threshold` | ❌ Wave 0 |
| RECOGNITION-08 | Thresholds read from config (no hardcoded values) | Feature | `php artisan test --compact --filter=FrasIncidentFactoryTest::it_respects_config_overrides` | ❌ Wave 0 |
| INTEGRATION-01 | Pulse triggered via Echo event (mock map) | Vitest/Vue | `npm run test -- useFrasAlerts.spec.ts` — OR manual UAT + Cypress (planner picks) | ❌ Wave 0 |
| INTEGRATION-01 (backend-only portion) | `RecognitionAlertReceived` payload + channel | Feature | `php artisan test --compact --filter=RecognitionAlertReceivedBroadcastTest` | ❌ Wave 0 |
| INTEGRATION-03 | IntakeStation 6th rail renders + Echo-hydrates | Feature | `php artisan test --compact --filter=IntakeStationFrasRailTest` | ❌ Wave 0 |
| INTEGRATION-04 | `useDispatchFeed.ts` unchanged (file identity) | Diff check | `git diff main -- resources/js/composables/useDispatchMap.ts resources/js/composables/useDispatchFeed.ts` (expect zero diff on useDispatchFeed) | — (git check) |

### Success Criteria → Validation Map

| SC | Behavior | Validation Approach |
|----|----------|--------------------|
| SC1 | RecPush against BOLO personnel at ≥0.75 creates P2 Incident (or P1 for lost_child), pulse on map, rail card with "CREATED INCIDENT" pill, escalate-to-P1 flow works end-to-end | Integration test via `php artisan test --compact --filter=Fras` + manual UAT with mosquitto_pub |
| SC2 | Dedup: second (camera, personnel) event within 60s does NOT create second Incident but DOES persist to `recognition_events` | `FrasIncidentFactoryTest::it_dedups_within_window` + DB row count assert |
| SC3 | Existing `tests/Feature/IoTWebhookControllerTest.php` passes unchanged | Run the file directly; zero modifications |
| SC4 | Lost-child category recognition → P1 directly (no escalate needed) | `FrasIncidentFactoryTest::it_creates_p1_for_lost_child` |
| SC5 | Warning-severity broadcast + pulse map but NO Incident created | `FrasIncidentFactoryTest::it_broadcasts_warning_without_creating_incident` + mock `setFeatureState` assertion |
| SC6 | Dispatch console map + Reverb sustain 50 events/sec/camera without frame drops | **Planner picks:** either (a) synthetic MQTT publisher script + Chrome DevTools Performance panel FPS meter, or (b) manual UAT + documented baseline, or (c) defer automation to Phase 22 ops-verification. **Recommendation:** (a) as a one-off script at Phase 21 merge; not part of CI. |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=Fras` (runs only FRAS suite — ~fast)
- **Per wave merge:** `php artisan test --compact` (full suite; ~slower)
- **Phase gate:** Full suite green + `vendor/bin/pint --test --format agent` + `npm run types:check` + `npm run lint:check` before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Fras/FrasIncidentFactoryTest.php` — 5 gates × 2 methods, plus full payload assertions
- [ ] `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` — payload shape + channel + severity paths
- [ ] `tests/Feature/Fras/EscalateToP1Test.php` — render conditions + route reuse + audit trigger + gate enforcement
- [ ] `tests/Feature/Fras/IntakeStationFrasRailTest.php` — prop shape + Echo wiring + 6th rail render + empty state
- [ ] Extend `tests/Feature/Fras/RecognitionHandlerTest.php` (Phase 19 exists) — add assertion that factory is called after persist
- [ ] **Optional:** `resources/js/composables/useFrasAlerts.spec.ts` (Vitest) — pulseCamera invocation on critical/warning payload arrival. Planner decides whether to add Vitest setup if not already present; otherwise cover via manual UAT matrix.
- [ ] **Optional SC6 stress-test script:** `scripts/fras-burst.sh` — `mosquitto_pub` in a loop emitting 50 recognitions/sec for 30s. Not CI; developer tool.

## Security Domain

Security enforcement: assumed enabled (no config override verified).

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no | Phase 21 reuses existing Fortify sessions — no new auth surface |
| V3 Session Management | no | Reuses Laravel session + Fortify |
| V4 Access Control | yes | Channel authorization (`fras.alerts` private gate); Gate check on `override-priority` (reused); responders explicitly excluded |
| V5 Input Validation | yes | `$request->validate(['priority' => ..., 'trigger' => 'sometimes|in:manual_override,fras_escalate_button'])` — strict allow-list on trigger |
| V6 Cryptography | no | No new crypto introduced. Face image signed URLs (if D-20 option a) reuse Laravel's `URL::temporarySignedRoute` — HMAC-SHA256 signing |
| V7 Error Handling | yes | Factory returns `?Incident` gracefully on all gate failures; no exceptions leak to MQTT listener; logs warnings via `Log::channel('mqtt')` |
| V8 Data Protection | yes | Recognition images MUST be served via signed URL with role gate (DPA-03; Phase 22 wires audit log) |
| V9 Communications | no | Reverb over HTTPS/WSS already configured Phase 17 |
| V11 Business Logic | yes | Severity × category → priority map prevents auto-P1 spam (priority floors in config) |

### Known Threat Patterns for Laravel + Reverb + Mapbox

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Unauthorized Echo subscription to `fras.alerts` | Information Disclosure | Private channel auth via `Broadcast::channel('fras.alerts', fn ($user) => in_array($user->role, $dispatchRoles))` [VERIFIED: routes/channels.php pattern shipped] |
| Escalate-to-P1 CSRF | Tampering | Inertia `useForm().post()` sends X-XSRF-TOKEN automatically |
| Unvalidated `trigger` field injection | Tampering / Audit integrity | `'trigger' => ['sometimes', 'in:manual_override,fras_escalate_button']` — strict allow-list (D-22) |
| Payload denorm leaking PII to unauthorized role | Information Disclosure | Responders not in `fras.alerts` role set; face images served via signed URL with separate role gate (Phase 22 enforces) |
| Broadcast event flood (DoS) | DoS | Reverb single-worker handles 50/sec; scaling via Redis if needed. Not a user-facing DoS vector in Phase 21 (MQTT ingress rate-limited by camera hardware + field scale) |
| Cache-based dedup bypass via cache flush | Tampering | Accepted tradeoff per D-08 — Redis flushes are rare + planned; double-Incident on rare flush is operational noise, not security issue |

## Sources

### Primary (HIGH confidence)

- [Laravel Docs — Cache storing items](https://laravel.com/docs/13.x/cache#storing-items-in-the-cache) — `Cache::add` atomic operation
- [Laravel Docs — Events (v13)](https://laravel.com/docs/13.x/events) — `ShouldDispatchAfterCommit` ordering within transaction
- [Laravel Docs — Broadcasting (v12/13)](https://laravel.com/docs/13.x/broadcasting) — `useEcho` API, `broadcastOn`, `broadcastWith`, private channel auth
- [Laravel Docs — Reverb (v13)](https://laravel.com/docs/13.x/reverb) — scaling config, Pusher protocol compatibility
- [@laravel/echo-vue on npm](https://www.npmjs.com/package/@laravel/echo-vue) — useEcho composable, auto-cleanup, connection status
- [Mapbox GL JS Style Spec — Expressions](https://docs.mapbox.com/mapbox-gl-js/style-spec/expressions/) — `feature-state`, `case`, `match`
- [Mapbox blog: Guide to Feature State](https://blog.mapbox.com/going-live-with-electoral-maps-a-guide-to-feature-state-b520e91a22d) — performance rationale, runtime styling without re-parsing geometry
- [Mapbox GL JS PR #8987: promoteId](https://github.com/mapbox/mapbox-gl-js/pull/8987) — promoteId unlocks string IDs for setFeatureState
- [Laravel Wayfinder repo](https://github.com/laravel/wayfinder) — auto-regeneration, form variants, optional field handling
- [Laravel News: Atomic Cache Locks](https://laravel-news.com/atomic-cache-locks) — Cache::add vs Cache::lock comparison
- IRMS shipped code (VERIFIED in-session): `app/Events/IncidentCreated.php`, `app/Events/CameraStatusChanged.php`, `app/Mqtt/Handlers/RecognitionHandler.php`, `app/Http/Controllers/IoTWebhookController.php`, `app/Http/Controllers/IntakeStationController.php`, `routes/channels.php`, `resources/js/composables/useDispatchMap.ts`, `resources/js/composables/useIntakeFeed.ts`, `resources/js/composables/useEnrollmentProgress.ts`, `package.json`, `composer.json`

### Secondary (MEDIUM confidence)

- [Laravel News: Laravel 10.30 ShouldDispatchAfterCommit](https://laravel-news.com/laravel-10-30-0) — historical context
- [Laravel Reverb Issue #307 — rate limiting](https://github.com/laravel/reverb/issues/307) — Reverb has no built-in message rate limiting (accepted tradeoff)
- [Mapbox GL JS Issue #7986 — string IDs](https://github.com/mapbox/mapbox-gl-js/issues/7986) — string IDs only work via promoteId
- [Mapbox GL JS Issue #7758 — feature-state error](https://github.com/mapbox/mapbox-gl-js/issues/7758) — setFeatureState silent no-op on missing ID
- [Laravel News: Wayfinder public beta](https://laravel-news.com/laravel-wayfinder-public-beta) — generate command, form variants

### Tertiary (LOW confidence — not load-bearing)

- [Twilio: Laravel Atomic Locks tutorial](https://www.twilio.com/en-us/blog/developers/tutorials/prevent-race-conditions-laravel-atomic-locks) — general Cache::lock context (Phase 21 uses `add`, not `lock`)
- [Medium: Broadcasting with Echo & Vue](https://medium.com/@danielalvidrez/broadcasting-with-laravel-echo-vue-js-1dc0fb488e54) — general patterns, superseded by official docs

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — installed versions verified via `npm view` + composer.json reads
- Architecture: HIGH — patterns verified against 3 shipped broadcast events + existing `useDispatchMap` (Phase 20) + existing `useEnrollmentProgress` (Phase 20)
- Pitfalls: HIGH — each pitfall cross-verified against shipped IRMS code or Mapbox/Laravel official issue threads
- Pulse mechanism: HIGH — `promoteId: 'id'` already shipped Phase 20, verified at line 349 of useDispatchMap.ts
- Dedup atomicity: HIGH — Laravel docs explicit + matches Phase 20 AckHandler pattern
- Broadcast testing: HIGH — `Event::fake` + `broadcastOn`/`broadcastWith` direct access is shipped Laravel idiom
- Validation architecture: HIGH — follows existing Phase 19/20 test layout; Wave 0 gaps match CONTEXT integration points list

**Research date:** 2026-04-22
**Valid until:** 2026-05-22 (stable Laravel 13 / Mapbox 3.x / Echo 2.3 APIs; update if Laravel 14 ships or IRMS upgrades Mapbox major)
