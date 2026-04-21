# Feature Landscape — v2.0 FRAS Integration

**Domain:** Face-recognition alerting embedded into an emergency-response CAD platform (IRMS) for CDRRMO Butuan City
**Parent milestone:** IRMS v2.0 — porting HDSystem's FRAS v1.0 (at `/Users/helderdene/fras`) into IRMS while keeping FRAS standalone
**Researched:** 2026-04-21
**Overall confidence:** HIGH (shipped FRAS v1.0 codebase is the authoritative reference; IRMS v1.0 composables/events are the authoritative integration surface)

---

## Scope Reminder

This milestone **adds** the following capability areas on top of shipped IRMS v1.0. It does **not** revisit intake, dispatch, responder, or analytics feature scope unless a FRAS concept plugs into them.

Four new feature categories:

1. **Camera management** — IP-camera fleet CRUD, liveness, map pins
2. **Personnel / BOLO** — watch-list enrollment + photo sync to cameras
3. **Recognition event pipeline** — MQTT ingestion → classification → persistence → broadcast
4. **Alert feed + map overlays** — operator review UI, acknowledgement, dispatch-map integration

Plus one integration surface:

5. **Recognition → IoT intake bridge** — convert high-severity recognition events into IRMS incidents via the existing IoT webhook / channel, not a new channel

Ported code reference points (from `/Users/helderdene/fras`):

- `app/Models`: `Camera`, `Personnel`, `RecognitionEvent`, `CameraEnrollment`
- `app/Services/CameraEnrollmentService`, `PhotoProcessor`
- `app/Mqtt/{TopicRouter, Handlers/{Recognition,Heartbeat,OnlineOffline,Ack}Handler}`
- `app/Jobs/EnrollPersonnelBatch` (WithoutOverlapping)
- `app/Events/{RecognitionAlert, CameraStatusChanged, EnrollmentStatusChanged}`
- `app/Enums/AlertSeverity` (Critical/Warning/Info/Ignored)
- `app/Console/Commands/{FrasMqttListen, CheckOfflineCameras, CheckEnrollmentTimeouts, CleanupRetentionImages}`
- `resources/js/pages/{cameras,personnel,alerts,events}`

---

## Table Stakes

Features any operator or admin working with FRAS would expect. Missing any is a usability failure.

### Category 1 — Camera Management

| Feature | Why Expected | Complexity | Notes / Port source |
|---------|--------------|------------|---------------------|
| Camera CRUD (device_id, name, location label, lat/lng) | Without a camera roster, nothing else works. Matches FRAS v1.0 exactly | LOW | Direct port of `CameraController` + `Http/Requests/Camera/*`. Drop-in to IRMS Admin layout. `device_id` must be unique — it keys the MQTT topic `mqtt/face/{device_id}/...`. |
| Heartbeat tracking with `is_online` + `last_seen_at` | Operators must know at a glance which cameras are dark. Matches shipped FRAS | LOW | Port `Mqtt/Handlers/HeartbeatHandler` + `CheckOfflineCamerasCommand` (every 30s, 90s offline threshold). Reuse as-is. |
| Online/offline broadcast via `CameraStatusChanged` event | Real-time status pills in UI must flip without refresh | LOW | Port `Events/CameraStatusChanged`. New Reverb channel: `cameras` (private, admin+operator+dispatcher read). |
| Camera creation map picker (click-to-place pin) | Coordinates can't be entered as decimals by hand reliably | LOW | FRAS `cameras/Create.vue` uses Mapbox GL JS; IRMS already uses MapLibre GL JS — re-implement picker with MapLibre to match existing stack (do NOT introduce Mapbox GL JS as a second map library). |
| Camera detail view with enrolled-personnel list + per-person sync status | Admins need to diagnose "is this personnel actually on this camera?" | LOW | Direct port of `CameraController::show` + `cameras/Show.vue` logic. |
| Delete camera (with soft warning if enrollments exist) | Standard CRUD expectation | LOW | Direct port. Cascade behavior already defined by `camera_enrollments.camera_id` FK. |
| Camera pins on dispatch map (new layer) | **IRMS-specific table stake.** Operators expect cameras to appear alongside incidents and units on the existing dispatch console map | MEDIUM | New `cameras` MapLibre source + symbol layer in `useDispatchMap.ts`. Pins color-coded by `is_online`. Click → popup with recent events summary. Z-order: below units, above barangay fill. |

### Category 2 — Personnel / BOLO Management

| Feature | Why Expected | Complexity | Notes / Port source |
|---------|--------------|------------|---------------------|
| Personnel CRUD (custom_id, name, person_type, optional photo) | Without personnel records, there is nothing to recognize | LOW | Direct port of `PersonnelController` + `Http/Requests/Personnel/*`. |
| Photo preprocessing (resize ≤1080p, compress <1MB, MD5 hash) | Camera firmware rejects large photos; hash enables diff-based re-sync | LOW | Direct port of `Services/PhotoProcessor.php`. Upgrade Intervention Image v3 → v4 if framework upgrade already pulls it in; otherwise keep v3 (FRAS shipped on v4). |
| Person-type taxonomy (block / allow / refused / stranger) | Drives severity classification. Industry-standard BOLO distinction | LOW | `AlertSeverity::fromEvent($personType, $verifyStatus)` logic is the source of truth — port verbatim as `app/Enums/AlertSeverity.php`. |
| Enrollment sync to all cameras on create/update | Operators will not individually enroll a person to 8 cameras — the system must do it | MEDIUM | Direct port of `CameraEnrollmentService::enrollPersonnel()` and `enrollAllToCamera()`. Preserve `WithoutOverlapping` on `EnrollPersonnelBatch` job. |
| Per-camera enrollment status visible on personnel detail (pending / enrolled / failed, with `last_error`) | Operators must know which cameras accepted the person vs. which failed and why | LOW | Direct port of `PersonnelController::show` + `personnel/Show.vue`. Reuse `EnrollmentController::retry` + `resyncAll`. |
| Human-readable error translation for enrollment failures (codes 461/463/464/465/466/467/468/474/478) | Raw camera error codes are meaningless to operators | LOW | Direct port of `CameraEnrollmentService::translateErrorCode()`. |
| Delete personnel → MQTT `DeletePersons` to all enrolled cameras | Compliance + hygiene: removing the watch-list record must remove the face from cameras | LOW | Direct port of `CameraEnrollmentService::deleteFromAllCameras()`. |
| Photo upload path publicly reachable by cameras | Cameras fetch photos via HTTP URL from `picURI` field | LOW | FRAS uses `Storage::disk('public')`. Camera subnet must reach Laravel host on HTTP — a deployment constraint, not UI work. |

### Category 3 — Recognition Event Pipeline

| Feature | Why Expected | Complexity | Notes / Port source |
|---------|--------------|------------|---------------------|
| Long-running MQTT listener artisan command | Without this, no events ever arrive | LOW | Direct port of `FrasMqttListenCommand`. Rename to `irms:mqtt-listen` or similar. Supervisor config required. |
| Topic router dispatching to per-operator handlers | Clean separation between Heartbeat / Online-Offline / RecPush / Ack | LOW | Direct port of `Mqtt/TopicRouter.php` + `Mqtt/Contracts/MqttHandler.php`. |
| RecPush event parsing with firmware quirk handling (`personName` vs `persionName`, stringified numerics, missing scene) | Real cameras ship these quirks | LOW | Direct port of `RecognitionHandler::parsePayload()`. |
| Severity classification (Critical = block, Warning = refused, Info = allow, Ignored = stranger/nothing) | Drives what is shown, broadcast, and audio-alerted | LOW | Direct port of `AlertSeverity::fromEvent()`. |
| Event persistence with `raw_payload` JSONB for audit | Operators and auditors must be able to reconstruct what the camera claimed | LOW | Direct port of `recognition_events` schema. Change MySQL JSON → PostgreSQL JSONB (free in Postgres). |
| Base64 image extraction + size-limited storage (face ≤1MB, scene ≤2MB) to date-partitioned directories | Camera pushes images inline in base64; dumping them to disk before DB write is how FRAS does it | LOW | Direct port of `RecognitionHandler::saveImage()`. Keep `storage/app/recognition/{YYYY-MM-DD}/{faces,scenes}/{event_id}.jpg` pattern. |
| Auth-protected image serving endpoints | Face crops and scene photos are PII | LOW | Direct port of `AlertController::faceImage` + `sceneImage`. Keep `local` disk (not `public`) so auth gate is enforced. |
| Broadcast `RecognitionAlert` only when `is_real_time && severity.shouldBroadcast()` | Do not flood browsers with replay/Ignored events | LOW | Direct port of `RecognitionHandler::handle` tail and `Events/RecognitionAlert`. |
| Personnel lookup by `custom_id` (null-safe) | Camera UI-enrolled persons may arrive with empty customId | LOW | Already in `RecognitionHandler` — preserve. |
| Retention cleanup (scene images 30 days, face crops 90 days) | Disk growth kills small deployments fast | LOW | Direct port of `CleanupRetentionImagesCommand`, scheduled daily at 02:00. |
| Enrollment ACK correlation + timeout detection | Without this, "pending" enrollments never resolve | LOW | Direct port of `AckHandler` + `CheckEnrollmentTimeoutsCommand` + the cache-based correlation in `CameraEnrollmentService::upsertBatch()`. |

### Category 4 — Alert Feed + Map Overlays

| Feature | Why Expected | Complexity | Notes / Port source |
|---------|--------------|------------|---------------------|
| Live alert feed (reverse-chronological, 50 most recent real-time events) | The primary operator surface for FRAS. Missing = product unusable | LOW | Direct port of `AlertController::index` + `alerts/Index.vue`. |
| Severity filter (Critical / Warning / Info pills) | Operators triage by severity | LOW | Direct port. |
| Camera filter (multi-select) | Operators often watch one or two cameras closely | LOW | Direct port. |
| Alert detail modal (face crop, scene photo, personnel metadata, camera, timestamp, similarity) | Click-through is how operators verify matches | LOW | Direct port of `AlertDetailModal`. |
| Acknowledge action (captures user + timestamp, shows acknowledger name) | Accountability trail | LOW | Direct port of `AlertController::acknowledge` with auth user capture. |
| Dismiss action (captures timestamp) | Clears false positives out of the active feed | LOW | Direct port of `AlertController::dismiss`. |
| Audio alert on Critical | Critical (block-list) must be impossible to miss | LOW | Port `shouldAlert()` logic. Integrate with existing IRMS `useAlertSystem.ts` to avoid two parallel audio stacks — one shared Web Audio layer. |
| Real-time feed updates via Echo/Reverb on `fras.alerts` private channel | Table stakes for a real-time product | LOW | Port channel auth. Rename channel to fit IRMS convention (e.g., `recognition.alerts` or `fras.alerts` — decide in REQUIREMENTS). |
| Event history page with date range, camera select, debounced search, severity pills, numbered pagination, sortable columns | Operators investigate after the fact | LOW | Direct port of `EventHistoryController` + `events/Index.vue`. Keep whitelist-validated sort. |
| **Camera marker pulse/flash on recognition** | Visual correlation between alert and map location | MEDIUM | FRAS does this on its own dashboard map; in IRMS it must happen on the **dispatch map**. New animation layer on the cameras symbol layer driven by `RecognitionAlert` Echo events. |

### Category 5 — Recognition → Incident Bridge (IRMS-specific integration)

This category does not exist in FRAS. It is the reason this port matters for CDRRMO.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Critical recognition event → IRMS incident via IoT intake channel | The whole value proposition: a block-list face at a flood-gate camera should flow into dispatch, not sit in a separate UI | MEDIUM | In `RecognitionHandler`, after persisting the event: when `severity === Critical`, emit to the existing IoT intake (existing `IoTWebhookController` logic or an internal service call) with a `source='fras'` discriminator. Incident `channel='iot'`, `caller_name` = camera name, location = camera coordinates, priority auto-suggested as P1 or P2. |
| Recognition-origin incidents link back to event + personnel | Dispatcher opening the incident needs the face crop and personnel record without leaving dispatch | MEDIUM | New nullable `incidents.recognition_event_id` FK. Incident detail panel renders a small FRAS card (face, name, camera) when set. |
| Warning severity events NOT creating incidents | Warnings (refused) are informational only; auto-escalating them to incidents would flood dispatch | LOW | Classification rule: only Critical promotes; Warning/Info stay in the alert feed. Revisit after real usage. |
| Operator can manually promote a non-critical alert to an incident | Escalation when an operator recognizes a genuine emergency context | LOW | Button on alert detail modal: "Create Incident from this alert" — reuses existing intake triage form pre-populated with recognition data. |

---

## Differentiators (CDRRMO-Specific)

These are what make the IRMS port worth doing versus just keeping FRAS standalone. Each is an additive capability FRAS does not have today.

### BOLO for Disaster Response

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Missing-persons watch-list (category: `missing`) | During floods/typhoons, relatives report missing persons; auto-pushing their photos to camera fleet can surface them at evacuation gates, choke points, or transit areas | LOW | New `person_type` enum value (e.g., `missing`) alongside block/allow/refused. Maps to a new severity: e.g., treat as Warning with a distinct tone and UI color. |
| Lost-child BOLO with elevated severity + SMS broadcast on match | Lost children are P1-equivalent urgency. Surfacing a match at any camera should alert dispatch AND the reporting family | MEDIUM | New severity tier: `Alert` or reuse Critical. On match, fire existing IRMS SMS stub integration to the reporter's phone. |
| BOLO expiry / auto-unenrollment | Missing-persons entries must not linger in the camera fleet indefinitely (privacy + camera storage) | MEDIUM | New nullable `personnel.expires_at`. Nightly job unenrolls expired entries via `DeletePersons`. |
| BOLO category with reporter incident link | Missing-persons BOLO entries should trace to the originating incident | LOW | New nullable `personnel.source_incident_id` FK to `incidents`. |

### Responder Hand-off

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Camera snapshot + personnel profile attached to assignment | Responder en route to a P1 incident caused by a block-list match sees the face + name in the scene briefing before arrival | MEDIUM | Extend `AssignmentPushed` broadcast event payload with optional `recognition_event_id`. ResponderLayout SceneTab renders a new "Context" accordion section when present. Image served via auth-protected face image endpoint. |
| Camera feed location on responder navigation | Responder sees the camera location as a waypoint/marker on their nav mini-map | LOW | Add cameras as an optional MapLibre layer on `useDispatchMap.ts` also consumed by responder navigation tab. Gated by feature flag — don't clutter responder UI by default. |

### Privacy / Regulatory Controls (LGU Context)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Per-personnel consent flag and legal basis tag | Philippines Data Privacy Act (RA 10173) requires a documented legal basis for processing biometric data | LOW | New `personnel.consent_basis` enum (e.g., `court_order`, `voluntary_enrollment`, `public_safety`, `disaster_response`). Required field. |
| Audit log of who enrolled/deleted/acknowledged each personnel and event | NPC (National Privacy Commission) audit requests will ask "who added this person to the watch-list?" | MEDIUM | New `audit_logs` table (or reuse incident timeline pattern). Covers personnel CRUD + event acknowledge/dismiss. Out of scope in FRAS v1.0 but explicitly listed there as deferred — now becomes required for LGU deployment. |
| Shorter default image retention with configurable override | Default FRAS retention (scene 30d / face 90d) is aggressive for a government deployment where retention policies are legally bounded | LOW | Move `retention.scene_days` and `retention.face_days` into `config/irms.php` (already the pattern). Default to smaller values; document override in deployment guide. |
| Role-scoped access to BOLO and recognition events | A responder should not see the full BOLO list or every recognition event; a dispatcher should see events but not edit the watch-list | MEDIUM | New IRMS gates: `manage-bolo` (admin only), `view-bolo` (admin + dispatcher + operator), `view-recognition-events` (admin + dispatcher + operator + supervisor), `acknowledge-recognition-event` (dispatcher + operator). Extends existing 9-gate system from v1.0. |

### Operator UX

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Recognition alert overlay on IntakeStation's three-column UI | Operators run intake and watch FRAS alerts on one screen — don't force them to switch windows | MEDIUM | Reuse existing IntakeStation shell. Add a fourth micro-panel (collapsible rail) or extend the channel monitor with a `fras` channel. Decide in REQUIREMENTS. |
| Alert acknowledgement attribution flowing into incident timeline when promoted | "PO3 dela Cruz acknowledged the block-list match at 14:03, promoted to P1 incident at 14:04" tells the full story | LOW | When promoting an alert to an incident, copy alert acknowledgement + acknowledger name into the incident timeline as entry type `recognition_match`. |

---

## Dependencies on Existing IRMS v1.0 Features

Explicit merge points — what already exists that this milestone extends rather than replaces.

### Database / Models

| Existing | New dependency |
|---|---|
| PostgreSQL + PostGIS (from v1.0 Phase 1) | Port FRAS MySQL schema for `cameras`, `personnel`, `camera_enrollments`, `recognition_events` to Postgres. Use `geography(POINT, 4326)` for camera lat/lng to match incidents/units convention (FRAS uses `decimal:7` — upgrade to geography during port). |
| `incidents` table (v1.0 Phase 1) | Add nullable `recognition_event_id` FK column. No data loss risk. |
| Barangays reference table (v1.0 Phase 1) | Cameras should be barangay-assigned via PostGIS `ST_Contains` lookup at save time (existing pattern from IncidentController). |
| 9-gate RBAC system (v1.0 Phase 1 + 8) | Add new gates: `manage-cameras`, `manage-bolo`, `view-recognition-events`, `acknowledge-recognition-event`. Extend existing admin/dispatcher/operator role assignments. |

### Real-time Infrastructure

| Existing | New dependency |
|---|---|
| Laravel Reverb + 6 broadcast events + role-based channel auth (v1.0 Phase 3) | Add 3 new broadcast events: `RecognitionAlert`, `CameraStatusChanged`, `EnrollmentStatusChanged`. Add channel auth for `recognition.alerts`, `cameras`, per-personnel enrollment status channels. |
| `useWebSocket.ts` composable (v1.0 Phase 3) | Reuse for all 3 new events — no new composable needed. |
| `useAlertSystem.ts` Web Audio composable (v1.0 Phase 4) | Extend with a new tone signature for Critical recognition matches. Must not conflict with the existing P1–P4 + resource-request arpeggio palette. |

### Intake

| Existing | New dependency |
|---|---|
| IoT intake channel + `IoTWebhookController` + HMAC-SHA256 validation (v1.0 Phase 2) | `RecognitionHandler` calls IoT intake internally (not via HTTP) when promoting critical recognition events to incidents. Preserves the single-intake-pipeline invariant from the milestone goal. |
| Channel monitor panel in IntakeStation (v1.0 Phase 8) | Surface FRAS as a channel-monitor entry or as a new sibling rail. UX decision in REQUIREMENTS. |
| Bilingual keyword-based priority classifier (v1.0 Phase 2) | Recognition-origin incidents bypass the classifier — severity is already determined by FRAS's `AlertSeverity` enum. Map Critical → P1, Warning → P2 as the default rule. |

### Dispatch Map

| Existing | New dependency |
|---|---|
| `useDispatchMap.ts` + MapLibre GL JS with WebGL markers (v1.0 Phase 4) | Add cameras as a new symbol layer (not HTML overlays — existing rule). Add recognition-pulse animation layer driven by `RecognitionAlert` Echo subscription. |
| `useDispatchFeed.ts` composable wiring 5 broadcast events to local state (v1.0 Phase 4) | Extend to subscribe to the 3 new events. Add derived reactive state for active camera alerts. |
| Priority-based audio alerts (v1.0 Phase 4) | Reuse `useAlertSystem.ts` for recognition Critical tone. |
| Custom dark-mode Mapbox style (from recent commit `ea52f22`) | Cameras layer styling must respect both light + custom dark styles. |

### Responder

| Existing | New dependency |
|---|---|
| `useResponderSession.ts` (v1.0 Phase 5 + 12) | Extend assignment payload type to include optional recognition context. |
| SceneTab accordion (v1.0 Phase 5) | Add a new accordion section: "Recognition Context" rendered only when the incident was promoted from a recognition event. |
| NavTab MapLibre mini-map (v1.0 Phase 5) | Optional cameras layer — feature-flagged off by default. |

### Admin

| Existing | New dependency |
|---|---|
| Admin CRUD panel with 3 controllers + Reka UI (v1.0 Phase 1) | 3 new admin resources: Cameras, Personnel, Enrollment status. All follow existing AdminLayout + Reka UI table pattern. |
| AdminUnitController auto-ID pattern (AMB-01, FIRE-02) from v1.0 Phase 11 | Consider same auto-ID pattern for cameras (CAM-01, CAM-02) if device_id is not operator-friendly. Decide in REQUIREMENTS. |

### Framework

| Existing | New dependency |
|---|---|
| Laravel 12 + PHP 8.2 (v1.0 baseline) | Milestone upgrades to Laravel 13 (FRAS is already on 13). Must not regress v1.0 behavior — dedicated upgrade phase required before FRAS feature work starts. |
| Vite 7 + Inertia v2 (v1.0) | FRAS is on Inertia v3. Decide: upgrade IRMS to Inertia v3 or keep v2 and adapt ported pages. Prefer v2 for less blast radius (Wayfinder, useEcho patterns depend on current Inertia). |

### Packages Added

| Package | Purpose | Source |
|---|---|---|
| `php-mqtt/laravel-client` | MQTT subscribe + publish | FRAS dep |
| `intervention/image` | Photo resize/compress/hash | FRAS dep (v3 in IRMS or v4 if upgrading) |

No other new runtime deps — Mapbox GL JS is explicitly NOT added (use existing MapLibre GL JS).

---

## Anti-Features (Explicit Out-of-Scope)

Each exclusion carries its own reasoning to prevent re-adding during REQUIREMENTS or implementation.

| Anti-Feature | Why Excluded | What To Do Instead |
|---|---|---|
| Training or fine-tuning face-recognition models | The cameras ship with on-device AI. IRMS is the alerting + orchestration layer, not an ML platform. Adding model training would require GPU infrastructure, dataset pipelines, and ML ops skills the team does not staff for | Cameras do the recognition; IRMS consumes `RecPush` events as a black-box input. Record raw payloads for possible future review — do not analyze. |
| Video streaming (RTSP, HLS, WebRTC) from cameras | Live video from 8+ cameras to browser is bandwidth-heavy, codec-sensitive, and adds a whole subsystem (media server, TURN, player). FRAS v1.0 did not stream video and still delivered the core value | Use the scene photo (single frame) attached to each recognition event. If an operator needs live video, they use the camera vendor's native console. |
| Other biometric modalities (fingerprint, iris, gait, voice) | CDRRMO has no hardware for these. Each modality has its own enrollment flow, matching backend, and privacy regime | Face-only for v2.0. Revisit only if the camera vendor ships a new modality on the same hardware. |
| Stranger-detection alerts (Snap topic) | FRAS v1.0 explicitly deferred these to v1.1. For CDRRMO, alerting on every unknown face at public infrastructure produces overwhelming noise with no operational response available | Ignore Snap topic in `TopicRouter`. Only RecPush (matched) events flow through. |
| Behavioral analytics events (tripwire, area intrusion, smoke/fire, PPE violation) | These are a separate problem domain (camera-based event detection, not face recognition). Each requires its own pipeline, classification, and UI | Not in this milestone. Could become a future "Camera Events" milestone if CDRRMO deploys such cameras. |
| Bulk personnel import via CSV / Excel | Deferred by FRAS v1.0 too. Adds validation surface (photo URL resolution, error rollback, dup detection) with no value for an initial watch-list of <200 people | Manual CRUD sufficient for v2.0. Revisit if watch-list exceeds ~500 entries. |
| Temporary visitor passes with auto-expiry | FRAS deferred to v2. For LGU context, the valuable expiry feature is for missing-persons BOLO (different semantic) — covered as a differentiator | Differentiator covers LGU-appropriate expiry; generic visitor-pass UX is commercial-facility territory. |
| PushAck continuous transmission ACK loop | FRAS explicitly disabled `ResumefromBreakpoint` on cameras. Re-enabling introduces message-loss accounting complexity for marginal benefit | Accept at-most-once delivery semantics. Retention cleanup + event history query satisfies recovery needs. |
| Camera firmware upgrades via the system | Camera vendor's own tooling handles firmware. Building a parallel path creates support liability | Document firmware upgrade as a manual vendor-console operation in the deployment guide. |
| Liveness/anti-spoofing detection in software | Cameras claim on-device liveness. IRMS adding a second liveness layer would require video frames + ML compute we explicitly excluded | Trust camera-reported matches. Operator acknowledgement is the human-in-the-loop anti-spoofing check. |
| Multi-tenancy per camera site | v1.0 IRMS is single-LGU (Butuan City) and explicitly defers multi-tenancy. Adding multi-site FRAS would drag IRMS's multi-tenancy decision forward prematurely | All cameras belong to one CDRRMO deployment. Revisit only after LGU-level multi-tenancy is on the roadmap. |
| Native mobile app for alert feed | Citizen PWA + dispatcher web console are the established IRMS UI surfaces. A native FRAS-only app would fragment the stack | Alerts visible in the existing responder PWA and dispatch console. Web Push (already in v1.0) can cover background notification. |
| Re-implementing the FRAS SOC-aesthetic redesign (glassmorphism, Inter font, slate/steel palette) | IRMS v1.0 shipped its own Sentinel navy/blue palette with DM Mono + Bebas Neue. Two competing design systems inside one app is a UX failure | Adapt ported FRAS pages to IRMS design tokens during the port. Specifically: CSS variable remap, font swap, table/badge pattern reuse from v1.0 Phase 10. |
| Dual maps (keep Mapbox GL JS alongside MapLibre) | Doubles bundle size, bifurcates map composables, splits basemap billing | Reimplement FRAS map pages with MapLibre GL JS. Adopt IRMS's existing Mapbox-styled dark basemap. |

---

## Complexity Summary (Porting Effort)

| Category | LOW (direct port) | MEDIUM (adapt to IRMS conventions) | HIGH (new design work) |
|---|---|---|---|
| Camera management | CRUD, heartbeat, offline detection, status broadcast, show page | Map picker (MapLibre not Mapbox), dispatch-map cameras layer | — |
| Personnel / BOLO | CRUD, photo processing, enrollment service, delete sync, error translation | Person-type taxonomy expansion (add missing/lost-child), BOLO expiry, role-scoped gates | Consent & audit log subsystem (new table) |
| Recognition pipeline | MQTT listener + router + 4 handlers, RecPush parser, image save, retention cleanup, severity enum, ACK correlation | Postgres JSONB schema, recognition → IoT intake bridge | — |
| Alert feed + map | Feed page, filters, detail modal, ack/dismiss, event history, audio integration | Dispatch-map pulse animation on recognition, IntakeStation integration surface | — |
| Framework upgrade | — | Laravel 12 → 13 | Regression-proofing v1.0 (16 phases of behavior) during upgrade |
| Integration | — | Incident linkage column, assignment payload extension, SceneTab recognition-context accordion | Manual promote-to-incident UX flow |

**Aggregate:** FRAS code is ~80% direct port at the service/handler/job layer; ~60% rework at the Vue page layer (design system remap, Mapbox→MapLibre); ~100% new work at the IRMS integration layer (map pins, intake bridge, BOLO categories, audit log). The framework upgrade is orthogonal and potentially the largest single risk.

---

## Feature Dependencies

```
Laravel 13 upgrade (blocks everything)
    |
    +-- Port cameras/personnel/recognition_events/camera_enrollments schema (MySQL → Postgres)
            |
            +-- Camera CRUD
            |     |
            |     +-- Heartbeat handler + offline detection + CameraStatusChanged broadcast
            |     +-- Map picker (MapLibre)
            |     +-- Dispatch-map cameras layer
            |
            +-- Personnel CRUD + PhotoProcessor
            |     |
            |     +-- CameraEnrollmentService + EnrollPersonnelBatch job + AckHandler
            |     +-- Per-personnel enrollment status page
            |     +-- Retry / Resync actions
            |     +-- BOLO category extensions (missing, lost-child, expiry)
            |           |
            |           +-- Nightly auto-unenroll job for expired BOLO
            |
            +-- MQTT listener + TopicRouter
                  |
                  +-- RecognitionHandler (RecPush)
                        |
                        +-- AlertSeverity classification
                        +-- RecognitionAlert broadcast (real-time + shouldBroadcast)
                        +-- Alert feed + event history UIs
                        +-- Alert acknowledge / dismiss
                        +-- Dispatch-map recognition-pulse animation
                        +-- Critical severity → IoT intake bridge → incident creation
                              |
                              +-- incidents.recognition_event_id FK
                              +-- Assignment payload extension
                              +-- ResponderLayout SceneTab recognition-context accordion
                              +-- Incident timeline recognition_match entries

Role-based access gates (extends v1.0)
    |
    +-- manage-cameras (admin)
    +-- manage-bolo (admin)
    +-- view-recognition-events (admin, dispatcher, operator, supervisor)
    +-- acknowledge-recognition-event (dispatcher, operator)

Audit log subsystem (new)
    |
    +-- Personnel CRUD audit
    +-- Recognition event acknowledge/dismiss audit
    +-- Enrollment retry/resync audit
```

---

## MVP Recommendation (v2.0 Ordering)

Recommended phase ordering for REQUIREMENTS author. Numbers are suggestive groupings, not fixed phase boundaries.

### Group A — Framework Foundation (must be first)

1. Laravel 12 → 13 upgrade with v1.0 regression suite passing
2. MySQL-schema → Postgres-schema port for the 4 FRAS tables
3. Package installation: `php-mqtt/laravel-client`, `intervention/image`
4. MQTT listener command + TopicRouter + handler stubs
5. Reverb channel auth for 3 new private channels

### Group B — Camera Management (standalone, unblocks everything else)

6. Camera model + CRUD (Postgres + PostGIS camera point)
7. Heartbeat + Online/Offline handlers + offline detection scheduler
8. `CameraStatusChanged` broadcast + admin UI status pills
9. Camera map picker (MapLibre)
10. Cameras layer on dispatch MapLibre map

### Group C — Personnel / BOLO (depends on cameras)

11. Personnel model + CRUD + PhotoProcessor
12. CameraEnrollmentService + EnrollPersonnelBatch job + Ack correlation
13. Per-personnel enrollment status page with retry/resync
14. Delete-from-all-cameras on personnel delete
15. Role-scoped gates + admin navigation

### Group D — Recognition Pipeline (depends on cameras + personnel)

16. RecPush handler + base64 image save + persistence
17. AlertSeverity classification
18. `RecognitionAlert` broadcast + retention cleanup
19. Alert feed page + severity/camera filters + detail modal
20. Acknowledge / dismiss actions
21. Event history page with filters + pagination
22. Dispatch-map recognition-pulse animation

### Group E — IRMS Integration Bridge (depends on pipeline)

23. `incidents.recognition_event_id` column + migration
24. Critical-severity → IoT intake service call (no HTTP round-trip)
25. Assignment payload extension with recognition context
26. ResponderLayout SceneTab recognition-context accordion
27. Incident timeline `recognition_match` entry type
28. Manual promote-to-incident action on alert detail

### Group F — CDRRMO Differentiators (final)

29. BOLO category expansion (missing, lost-child)
30. BOLO expiry + nightly auto-unenroll job
31. Consent basis field on personnel
32. Audit log table + hooks
33. Shorter retention defaults + config override
34. Design-system remap of ported FRAS pages to Sentinel tokens

### Defer (explicitly out-of-milestone)

- Stranger-detection alerts (Snap topic) — defer to v2.1
- Bulk personnel CSV import — defer until watch-list exceeds 200
- Behavioral analytics events (tripwire, PPE, etc.) — defer to dedicated milestone
- Video streaming — defer indefinitely

---

## Feature Prioritization Matrix

| Feature | User Value | Port/Build Cost | Priority |
|---|---|---|---|
| Camera CRUD + heartbeat + offline detection | HIGH | LOW | P1 |
| Personnel CRUD + photo processing + enrollment sync | HIGH | LOW | P1 |
| RecPush handler + severity classification + persistence | HIGH | LOW | P1 |
| Alert feed + event history + ack/dismiss | HIGH | LOW | P1 |
| Laravel 12 → 13 upgrade | HIGH (unblocks everything) | HIGH | P1 |
| Postgres schema port | HIGH (unblocks everything) | MEDIUM | P1 |
| Cameras layer on dispatch map | HIGH | MEDIUM | P1 |
| Recognition → IoT intake bridge | HIGH (the whole point) | MEDIUM | P1 |
| Recognition-pulse animation on dispatch map | MEDIUM | MEDIUM | P2 |
| Role-scoped gates for FRAS resources | MEDIUM | LOW | P2 |
| ResponderLayout recognition-context accordion | MEDIUM | MEDIUM | P2 |
| Manual promote-to-incident action | MEDIUM | LOW | P2 |
| BOLO category expansion (missing, lost-child) | MEDIUM | LOW | P2 |
| Audit log subsystem | MEDIUM (compliance) | MEDIUM | P2 |
| Consent basis field | MEDIUM (compliance) | LOW | P2 |
| BOLO expiry + auto-unenroll | LOW (until BOLO is used heavily) | MEDIUM | P3 |
| Design-system remap of ported pages | LOW (cosmetic) | MEDIUM | P3 |
| IntakeStation FRAS channel integration | LOW (workflow optimization) | MEDIUM | P3 |
| SMS broadcast on lost-child match | LOW (requires SMS integration unstubbed) | MEDIUM | P3 |

**Priority key:** P1 = required for v2.0 ship. P2 = strongly recommended for v2.0 ship. P3 = acceptable to defer to v2.1.

---

## Sources

- `/Users/helderdene/fras/.planning/PROJECT.md` — FRAS v1.0 shipped requirements (PRIMARY)
- `/Users/helderdene/fras/.planning/MILESTONES.md` — FRAS v1.0 accomplishments (PRIMARY)
- `/Users/helderdene/fras/.planning/ROADMAP.md` — FRAS v1.0 phase inventory (PRIMARY)
- `/Users/helderdene/fras/app/Models/{Camera,Personnel,RecognitionEvent,CameraEnrollment}.php` — schema source of truth (PRIMARY)
- `/Users/helderdene/fras/app/Services/CameraEnrollmentService.php` — enrollment flow source of truth (PRIMARY)
- `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php` — RecPush parsing + severity + broadcast logic (PRIMARY)
- `/Users/helderdene/fras/app/Enums/AlertSeverity.php` — classification rules (PRIMARY)
- `/Users/helderdene/fras/app/Http/Controllers/{Camera,Personnel,Enrollment,Alert,EventHistory}Controller.php` — controller responsibilities (PRIMARY)
- `/Users/helderdene/IRMS/.planning/PROJECT.md` — IRMS v1.0 validated requirements + v2.0 goal (PRIMARY)
- `/Users/helderdene/IRMS/app/Events/*.php` — existing 9 broadcast events that FRAS events must coexist with (PRIMARY)
- `/Users/helderdene/IRMS/resources/js/composables/{useDispatchMap,useDispatchFeed,useAlertSystem,useWebSocket}.ts` — integration surface (PRIMARY)
- `/Users/helderdene/IRMS/app/Http/Controllers/IoTWebhookController.php` — intake bridge target (PRIMARY)
- `/Users/helderdene/IRMS/docs/IRMS-Specification.md` — IRMS technical spec (PRIMARY)
- RA 10173 (Philippine Data Privacy Act) — biometric-data legal basis requirement referenced for consent field (MEDIUM, training data)
- RA 10121 (Philippine DRRM Act) — NDRRMC / LGU-responsibility context for BOLO / missing-persons response (MEDIUM, training data)

---

*v2.0 FRAS-into-IRMS feature research — 2026-04-21.*
