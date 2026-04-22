# Phase 21: Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail - Context

**Gathered:** 2026-04-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 21 delivers the **recognition-to-intake bridge** and the **operator-facing recognition surfaces** on dispatch + intake. Concretely:

- `App\Services\FrasIncidentFactory` with two public methods — `createFromSensor()` (factored from the existing `IoTWebhookController::__invoke` body) and `createFromRecognition(RecognitionEvent $event): ?Incident` — both writing `channel = IncidentChannel::IoT` Incidents with matching IncidentTimeline + `IncidentCreated` dispatch
- `IoTWebhookController` refactored to a thin validate-delegate-respond shape, delegating to `FrasIncidentFactory::createFromSensor()`; existing IoT sensor webhook Pest tests pass unchanged
- `RecognitionHandler` extended to always call `FrasIncidentFactory::createFromRecognition()` after persisting the `recognition_events` row; factory applies severity + confidence + category + dedup gates and returns null when any fails
- `App\Events\RecognitionAlertReceived` broadcasts on a new `fras.alerts` private channel for both the dispatch-map pulse trigger (Critical) and operator-awareness Warning events (RECOGNITION-05)
- Dispatch console: new `useFrasAlerts.ts` composable subscribes to `fras.alerts` and toggles mapbox `feature-state` on the existing cameras symbol layer (Phase 20 D-04); paint expression drives the pulse animation; `useDispatchFeed` is NOT modified (INTEGRATION-04)
- IntakeStation: 6th channel rail (FRAS) added to `ChannelFeed.vue` alongside Voice / SMS / App / IoT / Walk-in; rail shows recent Critical + Warning recognition events; click routes to Incident detail if `incident_id` is set, else opens a read-only event modal
- `incidents/Show.vue`: dedicated **Escalate to P1** button rendered only when the Incident's creation timeline entry has `event_data.source === 'fras_recognition'` and `priority !== 'P1'`; hits the existing `intake.override-priority` route; audit entry distinguished via `event_data.trigger = 'fras_escalate_button'`
- `config/fras.php` gains a `recognition` section: confidence cutoff (0.75), dedup window seconds (60), pulse duration seconds (3), and a `severity × category → priority` map (Critical × block/missing → P2, Critical × lost_child → P1)
- One IncidentType seeder: a single **Person of Interest** type; category label (`Block-list match` / `Missing person sighting` / `Lost child sighting`) lands in `incident.notes` and `IncidentTimeline.event_data.category`

**Out of scope (guardrails):**
- `/fras/alerts` live feed page with ACK/dismiss UX, severity-distinct audio cues, 100-alert ring buffer — Phase 22 (`useFrasFeed` consumes the `fras.alerts` channel Phase 21 authorizes)
- `/fras/events` searchable history page, replay badges, manual "promote to Incident" action — Phase 22
- SceneTab "Person of Interest" accordion on responder Incident view — Phase 22
- `fras_access_log` DPA audit table, signed 5-min recognition-image URLs, retention purge — Phase 22
- Stranger-detection `Snap` topic handling — REQUIREMENTS.md out-of-scope
- `allow`-category recognition events creating Incidents — explicitly excluded (D-01)
- Changes to `useDispatchFeed` — locked unchanged by INTEGRATION-04
- New `IncidentChannel` enum case — v2.0 milestone lock; reuse `IncidentChannel::IoT`

The deliverable gate: an MQTT RecPush from a registered camera against a block-list personnel at similarity ≥ 0.75 produces exactly one `channel=IoT, priority=P2` Incident (via `FrasIncidentFactory`), the matched camera pulses on the dispatch map within 500ms, a FRAS rail card appears on IntakeStation with a "CREATED INCIDENT" pill, and a supervisor clicking Escalate-to-P1 on the Incident detail updates priority to P1 with an audit timeline entry carrying `trigger='fras_escalate_button'`. A second event on the same `(camera_id, personnel_id)` within 60 seconds does NOT create a second Incident; the event still persists to `recognition_events`. A lost-child category recognition creates an Incident at P1 directly (no escalate needed). A Warning-severity recognition broadcasts on `fras.alerts` + pulses the map marker but creates no Incident. Existing IoT sensor webhook Pest tests pass unchanged.

</domain>

<decisions>
## Implementation Decisions

### Incident trigger rules + payload

- **D-01:** **Personnel categories triggering Incident creation:** `block` + `missing` + `lost_child`. Category `allow` never creates an Incident (allow = visitor pass; match is expected). **Planning must amend SC1** text: "block-list personnel" → "block-list OR missing OR lost_child personnel (operationally, all three BOLO categories)". REQUIREMENTS.md RECOGNITION-02 also needs the same amendment.
- **D-02:** **One new `IncidentType`**: `code = 'person_of_interest'`, `name = 'Person of Interest'`, category `Crime` (reuse existing category enum value). Seeder: `database/seeders/PersonOfInterestIncidentTypeSeeder.php`, idempotent (`updateOrCreate` by `code`). Active (`active=true`), sort_order last. FrasIncidentFactory resolves via `IncidentType::where('code','person_of_interest')->first()` once per factory boot (cached in service property).
- **D-03:** **Category → notes + timeline event_data:** `incident.notes` is pre-formatted by factory: `"FRAS Alert: {category_label} — {personnel.name} matched on {camera.camera_id_display} at {confidence}% confidence"`. Category labels: `block → 'Block-list match'`, `missing → 'Missing person sighting'`, `lost_child → 'Lost child sighting'`. `IncidentTimeline.event_data = ['source' => 'fras_recognition', 'recognition_event_id' => $event->id, 'camera_id' => $event->camera_id, 'personnel_id' => $event->personnel_id, 'personnel_category' => $personnel->category->value, 'confidence' => $event->similarity, 'captured_at' => $event->captured_at->toIso8601String()]`.
- **D-04:** **Incident.coordinates + barangay_id** copied verbatim from `camera.location` (PostGIS Point) and `camera.barangay_id` (Phase 20 D-06 already sets this at camera save time). No re-lookup at Incident creation — accepts barangay-boundary-drift risk as operationally negligible at CDRRMO cadence. `incident.location_text = camera.name` (falling back to `camera.camera_id_display` if `name` is null).
- **D-05:** **Severity × category → priority map** in `config/fras.php`:
  ```php
  'recognition' => [
      'priority_map' => [
          'critical' => [
              'block' => env('FRAS_PRIORITY_CRITICAL_BLOCK', 'P2'),
              'missing' => env('FRAS_PRIORITY_CRITICAL_MISSING', 'P2'),
              'lost_child' => env('FRAS_PRIORITY_CRITICAL_LOST_CHILD', 'P1'),
          ],
          // Warning + Info never auto-create Incidents (RECOGNITION-05/07)
          // so these rows are informational/future-use only.
      ],
      'confidence_threshold' => (float) env('FRAS_CONFIDENCE_THRESHOLD', 0.75),
      'dedup_window_seconds' => (int) env('FRAS_DEDUP_WINDOW_SECONDS', 60),
      'pulse_duration_seconds' => (int) env('FRAS_PULSE_DURATION_SECONDS', 3),
  ],
  ```
  `lost_child` defaults to P1 — safeguarding urgency; operator doesn't have to click-escalate on every minor sighting. Configurable without deploy per RECOGNITION-08.

### FrasIncidentFactory shape & dedup

- **D-06:** **Single service class, two public methods.** `App\Services\FrasIncidentFactory` with:
  - `public function createFromSensor(array $validated, array $mapping, IncidentType $incidentType): Incident` — factored verbatim from `IoTWebhookController::__invoke` lines 56–92 (Point construction, BarangayLookup, Incident create, IncidentTimeline with `event_data.source = 'iot_sensor'`, `IncidentCreated::dispatch`). Controller still owns validation + `sensor_mappings` config lookup + 422 error responses.
  - `public function createFromRecognition(RecognitionEvent $event): ?Incident` — returns null on any gate failure; returns the created Incident on success. **Always called** by `RecognitionHandler` after persisting the event row (RECOGNITION-01 requires persistence regardless of severity).
- **D-07:** **Gates inside `createFromRecognition()` in order:**
  1. **Severity gate:** `$event->severity !== RecognitionSeverity::Critical` → broadcast `RecognitionAlertReceived` (Warning severity triggers this per RECOGNITION-05; Info stays silent) → return null.
  2. **Confidence gate:** `$event->similarity < config('fras.recognition.confidence_threshold', 0.75)` → return null (no broadcast — Info-equivalent per RECOGNITION-07).
  3. **Personnel category gate:** Load `$personnel = Personnel::find($event->personnel_id)`. If null or `$personnel->category === PersonnelCategory::Allow` → return null. (Unknown/unmatched persons also return null — RecognitionHandler currently persists events even when personnel_id is null; factory skips those.)
  4. **Dedup gate:** `Cache::add("fras:incident-dedup:{$event->camera_id}:{$event->personnel_id}", true, config('fras.recognition.dedup_window_seconds', 60))` returns false → return null. Atomic; no race.
  5. **Write path:** resolve `IncidentType::where('code','person_of_interest')`, resolve priority from D-05 map using `$personnel->category->value`, `Incident::create([...])`, write `IncidentTimeline` with D-03 event_data, set `$event->incident_id = $incident->id` + save (FK round-trip per RECOGNITION-02), `IncidentCreated::dispatch($incident->load('incidentType','barangay'))`, broadcast `RecognitionAlertReceived` with `incident_id` populated, return Incident.
- **D-08:** **Dedup storage:** Redis cache via `Cache::add($key, true, $ttl)` — atomic add returns false if key already present. Key: `fras:incident-dedup:{camera_id}:{personnel_id}`. TTL: `config('fras.recognition.dedup_window_seconds', 60)`. No DB query, no new composite index, no cleanup job. Matches Phase 20 AckHandler cache pattern (D-15/D-16 there). Cache busts on Redis flush; accepted tradeoff.
- **D-09:** **Thin `IoTWebhookController::__invoke`** post-refactor: (a) validate request per existing rules; (b) resolve `$mapping` from `config('services.iot.sensor_mappings')` with 422 on unknown sensor_type; (c) resolve `$incidentType` with 422 on missing; (d) `$incident = $factory->createFromSensor($validated, $mapping, $incidentType)`; (e) `return response()->json(['incident_no' => $incident->incident_no, 'incident_id' => $incident->id], 201)`. Constructor injection swaps `BarangayLookupService` for `FrasIncidentFactory`. Existing `IoTWebhookControllerTest` must pass unchanged (RECOGNITION-03 SC3).
- **D-10:** **Factory owns both Incident + IncidentCreated dispatch** for both paths. `RecognitionHandler` does NOT dispatch `IncidentCreated` itself. Single source of truth for the v1.0 broadcast wiring (IncidentCreated is a locked Phase 17 snapshot; no payload drift).

### Map pulse + fras.alerts channel

- **D-11:** **New `fras.alerts` private channel** authorized in `routes/channels.php` alongside Phase 20's `fras.cameras` + `fras.enrollments`:
  ```php
  Broadcast::channel('fras.alerts', fn (User $user) => in_array($user->role->value, ['operator','dispatcher','supervisor','admin']));
  ```
  Roles: operator (IntakeStation rail + Phase 22 /fras/alerts), dispatcher (map pulse), supervisor + admin (everything). Responders are **not** on this channel (DPA role-gating preview for Phase 22).
- **D-12:** **`App\Events\RecognitionAlertReceived`** — `ShouldBroadcast` + `ShouldDispatchAfterCommit`, `PrivateChannel('fras.alerts')`, `broadcastWith()`:
  ```php
  [
      'event_id' => $event->id,                                // uuid, recognition_events.id
      'camera_id' => $event->camera_id,                        // uuid
      'camera_id_display' => $camera->camera_id_display,       // 'CAM-01'
      'camera_location' => [$lng, $lat],                       // from camera.location PostGIS Point
      'severity' => $event->severity->value,                   // 'critical' | 'warning'
      'personnel_id' => $event->personnel_id,                  // uuid or null
      'personnel_name' => $personnel?->name,                   // string or null
      'personnel_category' => $personnel?->category?->value,   // 'block'|'missing'|'lost_child'|'allow'|null
      'confidence' => $event->similarity,                      // float 0..1
      'captured_at' => $event->captured_at->toIso8601String(),
      'incident_id' => $incident?->id,                         // uuid or null (null for Warning + dedup-skipped Critical)
  ]
  ```
  Full denorm so consumers (dispatch map, IntakeStation rail, Phase 22 /fras/alerts) render without follow-up HTTP calls. Mirrors `IncidentCreated`'s eager-loaded payload shape.
- **D-13:** **Dispatch handling: `useFrasAlerts.ts` composable, not `useDispatchFeed`.** New composable `resources/js/composables/useFrasAlerts.ts` subscribes to `fras.alerts` via `useEcho`. On receipt: calls a pulse handler exposed by `useDispatchMap.ts` (new export `pulseCamera(cameraId: string)`). `useDispatchFeed` stays unchanged (INTEGRATION-04). Recognition-born Incidents reach the dispatch feed through the existing `IncidentCreated` broadcast that `FrasIncidentFactory` already dispatches (D-10).
- **D-14:** **Pulse mechanism: Mapbox `feature-state` + paint expression.** Camera symbol layer paint gets a case expression:
  ```js
  'icon-size': ['case', ['boolean', ['feature-state', 'pulsing'], false], 1.6, 1.0],
  'icon-opacity': ['case', ['boolean', ['feature-state', 'pulsing'], false], 1.0, 0.85],
  ```
  Trigger: `map.setFeatureState({source: 'cameras', id: cameraId}, {pulsing: true})`; `setTimeout(() => map.setFeatureState(..., {pulsing: false}), config.pulseDurationSeconds * 1000)`. GPU-accelerated; 60fps at 50 events/sec/camera load (SC6). Works for Warning severity too — all non-Info `RecognitionAlertReceived` events pulse the camera.
- **D-15:** **Pulse duration 3s, re-trigger resets timer.** Per-camera `Map<cameraId, timeoutHandle>` in the composable; new alert clears the prior timeout and sets a fresh one. Pulse stays visible during rapid bursts; doesn't flicker. Configurable via `config('fras.recognition.pulse_duration_seconds', 3)` exposed to the frontend via an Inertia shared prop `frasConfig`.
- **D-16:** **Info severity never reaches `fras.alerts`.** RecognitionHandler persists Info events for history (RECOGNITION-01) but the factory never broadcasts them — they're operationally silent beyond the DB (RECOGNITION-07). Phase 22 `/fras/events` surfaces history independently.

### IntakeStation rail + Escalate-to-P1

- **D-17:** **FRAS is a 6th rail, not a replacement.** `resources/js/components/intake/ChannelFeed.vue` `channelRows` array gains:
  ```ts
  { key: 'FRAS', label: 'FRAS', icon: IntakeIconFras, color: 'var(--t-ch-fras)' }
  ```
  Voice, SMS, App, IoT, Walk-in rails stay verbatim. **Planning must amend** INTEGRATION-03 requirement text + ROADMAP.md §Phase 21 SC6: "4th channel rail" → "6th channel rail". New icon component `resources/js/components/intake/icons/IntakeIconFras.vue` — planner picks the glyph (face/recognition motif).
- **D-18:** **Rail data source: Critical + Warning RecognitionEvents.** `IntakeStationController::show()` adds an Inertia prop:
  ```php
  'recentFrasEvents' => RecognitionEvent::query()
      ->with('camera:id,camera_id_display,name', 'personnel:id,name,category')
      ->whereIn('severity', [RecognitionSeverity::Critical, RecognitionSeverity::Warning])
      ->orderByDesc('received_at')
      ->limit(50)
      ->get()
      ->map(fn ($e) => [
          'event_id' => $e->id,
          'severity' => $e->severity->value,
          'camera_label' => $e->camera->camera_id_display,
          'personnel_name' => $e->personnel?->name,
          'personnel_category' => $e->personnel?->category?->value,
          'confidence' => $e->similarity,
          'captured_at' => $e->captured_at->toIso8601String(),
          'incident_id' => $e->incident_id,
          'face_image_path' => $e->face_image_path,  // used by signed-URL helper below
      ]),
  ```
  Plus an Echo subscription to `fras.alerts` via the `useIntakeFeed` composable (or a new `useFrasRail.ts` — planner picks) that appends new events to the local ring buffer. `channelCounts['FRAS']` reflects the rail's current event count.
- **D-19:** **Rail card shape:** face thumbnail (top-left) + personnel name (bold) + category chip + camera label + severity badge (Critical = red, Warning = amber) + "CREATED INCIDENT" pill when `incident_id` is not null + relative timestamp. Click behavior:
  - `incident_id !== null` → Inertia `router.visit(incidents.show(incident_id).url)` (route to existing Incident detail).
  - `incident_id === null` → open a read-only modal showing full event detail + face + scene image + why no Incident was created ("Warning severity — operator awareness only", "Confidence below 75% threshold", "Dedup window"). Modal exposes no write actions (promote-to-Incident is Phase 22).
- **D-20:** **Face thumbnail URL at Phase 21** — **Claude's Discretion / planner decides**. Options surfaced during discussion (not locked): (a) Temporary signed route mirroring Phase 20 D-22 pattern, gated to operator/supervisor/admin, 5-min TTL generated at `IntakeStationController::show()` boot; (b) defer image rendering to Phase 22 when the full signed-URL + `fras_access_log` audit stack lands — rail renders a generic icon placeholder until then. **Recommendation:** (a), as Phase 22 retrofitting the signed-URL contract around an existing image-rendering rail is awkward. Planner picks; deferred detail documented in Deferred Ideas below.
- **D-21:** **Escalate-to-P1 button on `incidents/Show.vue`.** Conditional render:
  ```ts
  const showEscalateButton = computed(() =>
      incident.timeline?.[0]?.event_data?.source === 'fras_recognition'
      && incident.priority !== 'P1'
  );
  ```
  Button placement: prominent top-right of the Incident header, alongside existing status/priority badges. Styled with the v1.0 destructive/urgent button token set (planner picks exact class, consistent with `override-priority` modal trigger styling).
- **D-22:** **Button hits the existing `intake.override-priority` route** (POST `/intake/{incident}/override-priority` with `priority=P1`). No new route, no new controller method, no new form request. Differentiated in audit via a new `event_data.trigger` key: `fras_escalate_button` — set by extending `IntakeStationController::overridePriority()` to accept an optional `trigger` form field (defaults to `manual_override` to preserve v1.0 audit shape):
  ```php
  $validated = $request->validate([
      'priority' => ['required', 'in:P1,P2,P3,P4'],
      'trigger' => ['sometimes', 'in:manual_override,fras_escalate_button'],
  ]);
  // ...
  'event_data' => [
      'old_priority' => $oldPriority,
      'new_priority' => $validated['priority'],
      'trigger' => $validated['trigger'] ?? 'manual_override',
  ],
  ```
  Wayfinder-generated action picks up the new field automatically.
- **D-23:** **Gate: reuse `override-priority`** (supervisor + admin only per `IntakeStationController::overridePriority` line 205). v1.0 invariant preserved — dispatchers cannot change priority. If operational feedback shows supervisors are a bottleneck on urgent BOLO escalations, a Phase 22+ gate-widening decision can follow; not in Phase 21 scope.

### Warning-severity broadcast completeness (RECOGNITION-05 thread)

- **D-24:** **Warning events broadcast via the same `RecognitionAlertReceived` event** on `fras.alerts` (D-12 payload with `incident_id = null`). No separate event class. Severity is a payload field; consumers filter or style accordingly. Pulse animation fires for both Critical + Warning (D-14); rail renders both (D-18). Info events do NOT broadcast (D-16).

### Planning-time text amendments required

- **D-25:** **ROADMAP.md §Phase 21 SC1** — amend "Critical-severity recognition event at ≥ 0.75 confidence against a block-list personnel" → "Critical-severity recognition event at ≥ 0.75 confidence against a block-list, missing, or lost-child personnel". Align with D-01.
- **D-26:** **REQUIREMENTS.md RECOGNITION-02** — same amendment: expand "block-list personnel" → "BOLO personnel (block / missing / lost_child categories; `allow` excluded)".
- **D-27:** **ROADMAP.md §Phase 21 SC6 + REQUIREMENTS.md INTEGRATION-03** — amend "4th channel rail" → "6th channel rail". ChannelFeed already has 5 rails in v1.0 shipped code.

### Claude's Discretion

- Exact icon design for `IntakeIconFras.vue` — planner mirrors existing icon style (stroke weight, viewBox, color-token wiring) of `IntakeIconIot.vue` / `IntakeIconSms.vue`.
- CSS token `--t-ch-fras` color value — planner picks consistent with existing channel token set (check `resources/css/` tokens file; Phase 14 Sentinel palette).
- Pulse animation visual tuning (icon scale, color shift, halo, transition curve) — planner picks; 60fps + visible-at-15-feet cognition baseline; `useDispatchMap` color token patterns for reference.
- Rail card visual layout details (thumbnail aspect, chip placement, badge colors) — planner picks consistent with existing FeedCard.vue and CameraStatusBadge conventions.
- Face thumbnail URL strategy (D-20) — recommendation is signed route now; planner confirms after reviewing Phase 20 D-22 signed-URL shape and Phase 22 DPA scope.
- `FrasIncidentFactory` property caching for `IncidentType::where('code','person_of_interest')` — planner picks (static cache vs request-memoized vs per-call fetch); low frequency, planner's call.
- Ring-buffer size for the FRAS rail Echo subscription — default 50 events matching IntakeStation's `recentActivity` limit; planner confirms.
- Whether read-only event modal (D-19) lives as a new `FrasEventDetailModal.vue` under `components/intake/` or `components/fras/` — planner picks; Phase 22 may relocate if needed.
- Route name + HTTP verb for the read-only event detail fetch (if modal needs backend data beyond the Inertia prop) — planner picks; lean toward consuming the pre-hydrated prop to avoid an extra endpoint.
- Whether the existing `OverridePriorityRequest`/validation extends to accept `trigger` inline or via a new request class — planner picks; lean inline per D-22.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 21 goal, requirements, success criteria
- `.planning/ROADMAP.md` §Phase 21 — goal, depends-on (Phase 19, 20), 6 success criteria, requirements list (RECOGNITION-01..08, INTEGRATION-01/03/04). **NOTE:** D-25/D-26/D-27 require SC1/INTEGRATION-03/SC6 text amendments during planning.
- `.planning/REQUIREMENTS.md` §RECOGNITION — RECOGNITION-01..08 acceptance criteria. **NOTE:** RECOGNITION-02 needs D-26 amendment (block-list → all BOLO categories).
- `.planning/REQUIREMENTS.md` §INTEGRATION — INTEGRATION-01/03/04. **NOTE:** INTEGRATION-03 needs D-27 amendment (4th → 6th rail).

### Phase 18 schema (already frozen; Phase 21 persists into these)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` — schema freeze reference
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-54 — `recognition_events UNIQUE (camera_id, record_id)` idempotency constraint
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-64/D-65 — `RecognitionSeverity` + `PersonnelCategory` enums
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` — `recognition_events.incident_id` FK nullable (Phase 21 fills via D-07 step 5)

### Phase 19 shape (Phase 21 extends RecognitionHandler)
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` — full MQTT listener + handler shape
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` D-01 — inline handler processing model (Phase 21 honors: factory called inline from RecognitionHandler)
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` D-14 — unknown-camera RecPush drop rule (Phase 21 inherits; no change)
- `app/Mqtt/Handlers/RecognitionHandler.php` — current handler body (persists + decodes images). Phase 21 appends `FrasIncidentFactory::createFromRecognition($event)` call after the persist + image writes.

### Phase 20 shape (Phase 21 extends channels + map layer)
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` — camera + personnel + enrollment decisions
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` D-01/D-04 — mapbox-gl retained, camera symbol layer on `useDispatchMap.ts` (Phase 21 adds pulse paint expression to same layer)
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` D-06 — `camera.barangay_id` auto-assigned at save; Phase 21 copies verbatim (D-04 here)
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` D-22 — operator signed-URL photo pattern (reference for D-20 face thumbnail URL strategy)
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` D-36/D-37/D-38 — `fras.cameras` / `fras.enrollments` channel auth + event shape (Phase 21 adds `fras.alerts` following the same pattern)
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` D-39 — `config/fras.php` structure (Phase 21 adds `recognition` section per D-05 here)

### v2.0 milestone locked decisions
- `.planning/REQUIREMENTS.md` §Scope Decisions — `IncidentChannel::IoT` reuse (no new channel enum) — **locked, honored**
- `.planning/STATE.md §Accumulated Context` — severity→priority mapping, UUID PKs, Inertia v2 retained — **all honored**
- `.planning/ROADMAP.md` §v2.0 overview paragraph — "`FrasIncidentFactory` is the single load-bearing integration seam" — **Phase 21 delivers this seam**

### IRMS v1.0 conventions + reference code
- `app/Http/Controllers/IoTWebhookController.php` — current controller body (lines 28–92) factored into `FrasIncidentFactory::createFromSensor()` per D-06
- `app/Http/Controllers/IntakeStationController.php` — `show()` method gains `recentFrasEvents` prop (D-18); `overridePriority()` method extended with optional `trigger` field (D-22)
- `routes/web.php` line 101 — `intake/{incident}/override-priority` route (Phase 21 reuses; no new route for escalate)
- `app/Services/BarangayLookupService.php` — used inside `FrasIncidentFactory::createFromSensor()` (inherited from controller factor); `createFromRecognition()` bypasses (camera.barangay_id already set)
- `app/Events/IncidentCreated.php` — broadcast contract Phase 21 dispatches via factory (D-10); do NOT modify payload shape (Phase 17 snapshot locked)
- `app/Events/CameraStatusChanged.php` (Phase 20) + `app/Events/EnrollmentProgressed.php` (Phase 20) — reference shape for `RecognitionAlertReceived` (D-12)
- `routes/channels.php` — Phase 19 (`dispatch.incidents`, `dispatch.units`) + Phase 20 (`fras.cameras`, `fras.enrollments`); Phase 21 adds `fras.alerts` per D-11
- `routes/channels.php` — Phase 20 `fras.cameras` role gate as pattern for `fras.alerts` (D-11 mirrors verbatim)
- `resources/js/components/intake/ChannelFeed.vue` — 5-rail `channelRows` array Phase 21 extends to 6 (D-17)
- `resources/js/components/intake/icons/IntakeIconIot.vue` — icon shape reference for new `IntakeIconFras.vue`
- `resources/js/components/intake/ChBadge.vue` — `ChannelKey` type Phase 21 extends with `'FRAS'`
- `resources/js/composables/useDispatchMap.ts` — existing cameras symbol layer (Phase 20 D-04); Phase 21 adds `pulseCamera(cameraId)` export + paint case-expression (D-14)
- `resources/js/composables/useDispatchFeed.ts` — **do NOT modify** (INTEGRATION-04 locked)
- `resources/js/composables/useIntakeFeed.ts` — Phase 21 may extend for `channelCounts['FRAS']` + ring buffer OR add sibling `useFrasRail.ts` (planner discretion)
- `resources/js/pages/incidents/Show.vue` — Phase 21 adds Escalate-to-P1 button per D-21 (conditional on `event_data.source === 'fras_recognition'`)
- `config/fras.php` (Phase 19/20 created + extended) — Phase 21 adds `recognition` section per D-05
- `config/services.php` `services.iot.sensor_mappings` — Phase 21 does NOT change; `FrasIncidentFactory::createFromSensor()` reads via the controller as today
- `database/seeders/` — Phase 21 adds `PersonOfInterestIncidentTypeSeeder` per D-02

### FRAS source (verbatim port references)
- `/Users/helderdene/fras/app/Services/FrasIncidentFactory.php` or equivalent — if a comparable factory exists in FRAS, reference shape; otherwise this is IRMS-new
- `/Users/helderdene/fras/` recognition event handling — for parity on severity/confidence gate ordering

### Test references
- `tests/Feature/IoTWebhookControllerTest.php` (existing, pre-Phase-21) — must pass unchanged post-refactor (RECOGNITION-03 SC3)
- `tests/Feature/Fras/RecognitionHandlerTest.php` (Phase 19) — Phase 21 extends with factory-call expectations
- New: `tests/Feature/Fras/FrasIncidentFactoryTest.php` — cover all 5 gates (D-07), both methods (D-06), full payload (D-03/D-04)
- New: `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` — payload shape (D-12), channel auth (D-11), both severity paths
- New: `tests/Feature/Fras/EscalateToP1Test.php` — button rendering conditions (D-21), route reuse + audit trigger field (D-22), gate enforcement (D-23)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`IoTWebhookController::__invoke` body (lines 56–92)** — factor verbatim into `FrasIncidentFactory::createFromSensor()`. Point construction, BarangayLookup, Incident::create, IncidentTimeline::create, IncidentCreated::dispatch. Controller keeps validation + 422s.
- **Phase 20 `fras.cameras` channel auth pattern (D-36)** — Phase 21 clones for `fras.alerts` in routes/channels.php with the same role set.
- **Phase 20 camera symbol layer on `useDispatchMap.ts` (D-04)** — Phase 21 extends the paint expression with a `feature-state` case branch; no new layer, no new source. `useDispatchMap` already exports `setCameraData` (line 761) and has the map instance; Phase 21 adds `pulseCamera(cameraId: string): void`.
- **Phase 20 `AckHandler` Redis correlation pattern (D-15)** — reference shape for Phase 21 dedup cache: `Cache::add(key, value, ttl)`.
- **`IntakeStationController::show()` Inertia prop + Echo-on-mount pattern** — Phase 21 adds `recentFrasEvents` prop following the `recentActivity` shape (line 53–64). No schema/route change.
- **`IntakeStationController::overridePriority()` + `intake.override-priority` route** — Phase 21 reuses (D-22) with optional `trigger` validation rule added; audit goes through existing `IncidentTimeline::create`.
- **`IncidentCreated` event dispatch (Phase 17 locked)** — Phase 21 factory dispatches; payload shape unchanged; byte-identical with v1.0 snapshot.
- **`ChannelFeed.vue` `channelRows` array + `ChBadge.vue` `ChannelKey` type** — Phase 21 extends both with FRAS. No refactor; pure addition.
- **Phase 19 `fras-supervisor` Horizon queue** — available if any future Phase 21 work needs to offload; Phase 21 stays inline per Phase 19 D-01.

### Established Patterns
- **Thin controller → service class** — v1.0 pattern (e.g., `AdminUnitController` → model method calls). `FrasIncidentFactory` service slots in cleanly.
- **`config/fras.php` as single source for tunables** — Phase 19/20 convention; Phase 21 adds `recognition` section. Env-var override pattern: `(int|float) env('FRAS_X', default)`.
- **Private channel auth via role gate** — `routes/channels.php` lines 9–11 (dispatch) + Phase 20 additions (fras.*). Phase 21's `fras.alerts` uses the same `in_array($user->role->value, [...])` shape.
- **ShouldBroadcast + ShouldDispatchAfterCommit + `broadcastWith()`** — standard IRMS broadcast event shape; Phase 21's `RecognitionAlertReceived` matches.
- **Mapbox GL `setFeatureState` + paint `case` expression** — already used in `useDispatchMap.ts` for incident severity styling; Phase 21 clones the idiom for camera pulsing.
- **Inertia shared prop + Echo-on-mount hydration** — `DispatchConsole.vue` + Phase 20 `EnrollmentProgressPanel.vue`; Phase 21 IntakeStation FRAS rail follows the same pattern.
- **IncidentTimeline as audit log with `event_data` JSONB** — v1.0 pattern; Phase 21 extends `event_data.trigger` key (D-22) without schema change.
- **Inline handler processing** (Phase 19 D-01) — Phase 21 factory runs inline inside `RecognitionHandler::handle()`; no queue, no async.

### Integration Points
- `app/Services/FrasIncidentFactory.php` (NEW) — two methods, gate-then-write pattern per D-06/D-07
- `app/Http/Controllers/IoTWebhookController.php` (MOD) — factored to thin controller per D-09
- `app/Mqtt/Handlers/RecognitionHandler.php` (MOD) — add `FrasIncidentFactory::createFromRecognition($event)` call after image writes (between line 119 and method end)
- `app/Events/RecognitionAlertReceived.php` (NEW) — broadcast event per D-12
- `app/Http/Controllers/IntakeStationController.php` (MOD) — `show()` adds `recentFrasEvents` prop per D-18; `overridePriority()` adds optional `trigger` field per D-22
- `routes/channels.php` (MOD) — add `fras.alerts` channel per D-11
- `resources/js/composables/useFrasAlerts.ts` (NEW) — Echo subscription + pulse trigger per D-13
- `resources/js/composables/useDispatchMap.ts` (MOD) — export `pulseCamera`, add paint case-expression per D-14/D-15
- `resources/js/composables/useFrasRail.ts` (NEW, or extend useIntakeFeed) — ring buffer for FRAS rail per D-18/D-19
- `resources/js/components/intake/ChannelFeed.vue` (MOD) — 6th rail + channelCounts.FRAS per D-17
- `resources/js/components/intake/icons/IntakeIconFras.vue` (NEW)
- `resources/js/components/intake/ChBadge.vue` (MOD) — extend `ChannelKey` with `'FRAS'`
- `resources/js/components/intake/FrasEventDetailModal.vue` (NEW) — read-only modal per D-19
- `resources/js/pages/incidents/Show.vue` (MOD) — Escalate-to-P1 button per D-21
- `config/fras.php` (MOD) — `recognition` section per D-05
- `database/seeders/PersonOfInterestIncidentTypeSeeder.php` (NEW) — per D-02; called from main seeder chain
- `tests/Feature/Fras/FrasIncidentFactoryTest.php` (NEW) — 5 gates + 2 methods + full payload
- `tests/Feature/Fras/EscalateToP1Test.php` (NEW) — button conditions + gate + audit trigger
- `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` (NEW) — payload + channel + severity paths
- `tests/Feature/Fras/IntakeStationFrasRailTest.php` (NEW) — prop shape + Echo wiring + 6th rail render
- `tests/Feature/IoTWebhookControllerTest.php` (EXISTING) — must pass unchanged (RECOGNITION-03 SC3)
- `tests/Feature/Fras/RecognitionHandlerTest.php` (Phase 19, MOD) — extend with factory-call expectation

### Known touchpoints that DO NOT change in Phase 21
- `useDispatchFeed.ts` — locked by INTEGRATION-04; recognition-born Incidents reach the feed through existing `IncidentCreated` broadcast
- `IncidentChannel` enum — no new case (v2.0 locked)
- `IncidentCreated` payload shape — Phase 17 byte-identical snapshot
- `config/services.php` `services.iot.sensor_mappings` — unchanged; controller still reads it
- `CameraStatusChanged` + `EnrollmentProgressed` events — Phase 20 shape preserved
- `recognition_events` schema — frozen (Phase 18); Phase 21 only writes `incident_id` column via D-07 step 5
- Phase 17 broadcast snapshot set (6 events) — no new broadcast joins that set

</code_context>

<specifics>
## Specific Ideas

- **"All BOLO categories trigger, not just block-list."** The spec language was narrow; operational reality is that `missing` + `lost_child` are equally urgent. Lost child maps to P1 directly so operators don't have to escalate on every sighting. SC1 + RECOGNITION-02 get amended during planning — no code-level workarounds, just spec alignment.
- **"One FrasIncidentFactory, two methods, gates inside the recognition method."** Single seam honors the v2.0 milestone "load-bearing integration seam" language. Gate ordering (severity → confidence → category → dedup → write) is the testable contract — write `FrasIncidentFactoryTest` to match the order explicitly so a future refactor can't silently re-order gates.
- **"Redis `Cache::add` for dedup — atomic, no index, no cleanup."** Matches Phase 20 AckHandler pattern. `add` returns false on existing-key, no race. 60s TTL. Cache flush = dedup reset, which is operationally fine (Redis flushes are rare + planned).
- **"Full denorm payload on RecognitionAlertReceived."** 50 events/sec/camera load test passes because the frontend does zero follow-up HTTP calls per event. Matches IncidentCreated's eager-loaded shape.
- **"Pulse via Mapbox feature-state + paint case-expression, not a second layer, not DOM overlays."** GPU-accelerated; stays attached to the feature under zoom/pan; 60fps at 50 events/sec. Per-camera timeout reset prevents flicker under bursts.
- **"6 rails on IntakeStation, not 5, not 4."** INTEGRATION-03 text is wrong; ChannelFeed already has 5 rails in shipped v1.0 code. Amend the spec, add the rail. Voice stays because v1.0 Voice intake still exists operationally.
- **"Escalate-to-P1 reuses override-priority route + gate."** No new gate, no new route. Audit differentiation via `event_data.trigger = 'fras_escalate_button'`. v1.0 audit shape preserved (defaults to `manual_override`).
- **"Rail shows Critical + Warning RecognitionEvents, not Incidents."** Matches intake mental model: rails show inbound signals, not downstream effects. "CREATED INCIDENT" pill + click-through to Incident detail preserves the linkage without forcing a different data shape.
- **"Face thumbnails on the rail are a planner call."** Recommendation is to wire the signed-URL pattern now (Phase 22 has more to add around the rendered image, not less); planner confirms after reading Phase 20 D-22.
- **"Info severity never broadcasts."** Persisted for history; silent for operators. Phase 22 /fras/events renders history from the DB.

</specifics>

<deferred>
## Deferred Ideas

- **Phase 22 `fras_access_log` audit table for rail image fetches** — if D-20 wires the signed-URL pattern at Phase 21, every rail render populates the audit log at Phase 22 without Phase 21 code change. If Phase 22 changes the URL shape, the rail's image source needs an update.
- **Dispatcher gate widening for Escalate-to-P1** — if operational feedback shows supervisors bottleneck urgent BOLO escalations. New `escalate-fras-incident` gate + route + button-gate-check. Not in Phase 21.
- **"Promote to Incident" action on the read-only event modal** — Phase 22 /fras/events owns this. Phase 21 modal is read-only.
- **Rail ring-buffer size tuning** — default 50 events; revisit if CDRRMO operations show either memory pressure or scroll-past-the-bottom friction.
- **Pulse animation visual design (color, halo, shape)** — planner discretion; can be refined in Phase 22 alongside /fras/alerts visual design.
- **`allow`-category recognition logging for Phase 22 DPA audit** — if DPA compliance requires all recognition events (including Info / allow) to appear in access log, wire at Phase 22. Phase 21 persists them in `recognition_events`; no broadcast.
- **Load-test SC6 automation** — 50 events/sec/camera verification. Phase 21 planner picks whether this is a synthetic test harness, a manual UAT step, or deferred to Phase 22 operational verification.
- **`RecognitionEvent.incident_id` backfill for events created before Phase 21 ships** — Phase 19/20 recognition_events rows have `incident_id = NULL` by design. No retroactive backfill; only Phase 21+ events get linked. Operational rule: the historical gap is intentional (pre-factory events did not have the bridge).
- **Severity × category → priority map tuning** — defaults in D-05 are conservative; operators can adjust env vars without deploy. Revisit after 2 weeks of production data.
- **IntakeStation channel order rearrangement** — current order is Voice / SMS / App / IoT / Walk-in; FRAS appended at position 6. If operational flow suggests FRAS should sit between IoT and Walk-in (both "non-verbal" channels), reorder in a Phase 22 polish pass.
- **Warning severity creating Incidents via operator override** — not in Phase 21. Phase 22's event-detail modal's "promote to Incident" action handles this.
- **Single `trigger` → full audit enum** — right now `event_data.trigger` is free-ish text with two known values. If more triggers land (Phase 22 promote-button, auto-upgrade rules), promote to a backed enum.

</deferred>

---

*Phase: 21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai*
*Context gathered: 2026-04-22*
