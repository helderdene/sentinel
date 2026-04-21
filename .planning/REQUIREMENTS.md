# Requirements: IRMS v2.0 FRAS Integration

**Milestone:** v2.0 FRAS Integration
**Goal:** Embed HDSystem's Face Recognition Alert System capabilities into IRMS so AI IP-camera recognition events flow into CDRRMO's dispatch pipeline via the existing IoT intake channel, without regressing v1.0 dispatch/intake/responder behavior.
**Started:** 2026-04-21

## Scope Decisions (from research + discussion)

Locked before phase planning:

- **PK type:** UUIDs on all 4 new FRAS tables (cameras, personnel, recognition_events, camera_enrollments), matching IRMS Incident/Message precedent.
- **Severity → Priority:** Critical → P2 default with dispatcher one-click escalation to P1. Warning → P4 notify-only, never auto-Incident. Info → history-only, never surfaces on dispatch.
- **Frontend baseline:** Inertia v2 retained. FRAS v3 pages port down to v2 at copy-time.
- **Map baseline:** MapLibre GL JS. `mapbox-gl` explicitly rejected. CI bundle-check must fail if imported.
- **Process model:** MQTT listener runs under a dedicated Supervisor program, never under Horizon. Horizon gains a separate `fras` queue supervisor for enrollment jobs.
- **Integration surface:** Reuse `IncidentChannel::IoT` enum value — no new channel. Recognition events tagged via `IncidentTimeline.event_data.source = 'fras_recognition'`.
- **Photo URL model:** Two-namespace scheme — public unguessable-UUID URL for camera enrollment fetch (revoked post-sync), auth-signed 5-min URL for human operators (operator/supervisor/admin only; responders and dispatchers excluded from raw recognition images).
- **Retention defaults:** 30 days for scene images, 90 days for face crops (configurable in `config/fras.php`), with active-incident-protection so images referenced by open incidents never get purged. CDRRMO legal finalizes exact numbers during Phase 22 sign-off.
- **Camera auto-ID:** Follow IRMS v1.0 `AMB-01`/`FIRE-02` pattern with `CAM-01`/`CAM-02` auto-generated IDs alongside the MQTT `device_id`.

## Milestone v2.0 Requirements

### FRAMEWORK — Laravel 12 → 13 Upgrade + Schema Foundation

Feature-free foundation that gates every downstream phase.

- [x] **FRAMEWORK-01
**: Admin can deploy IRMS on Laravel 13 with the full v1.0 Pest suite green and no user-visible behavior change
- [x] **FRAMEWORK-02
**: All 6 existing Reverb broadcast events (IncidentCreated, IncidentTriaged, UnitAssigned, UnitStatusChanged, ChecklistUpdated, ResourceRequested) emit identical payloads pre- and post-upgrade
- [x] **FRAMEWORK-03
**: Admin can follow a documented Horizon drain-and-deploy protocol so queued jobs never execute under a mixed Laravel-version worker
- [x] **FRAMEWORK-04
**: IRMS Postgres schema gains empty `cameras`, `personnel`, `camera_enrollments`, `recognition_events` tables with UUID primary keys, JSONB columns (with GIN indexes on `raw_payload`), TIMESTAMPTZ timestamps, and Magellan `geography(POINT, 4326)` for `cameras.location`
- [ ] **FRAMEWORK-05**: Pest test suite switches to PostgreSQL for FRAS test groups so JSONB + geography behavior is actually exercised (SQLite in-memory has neither)
- [ ] **FRAMEWORK-06**: `recognition_events` has a `(camera_id, record_id)` unique constraint for idempotency against MQTT redelivery

### MQTT — Pipeline, Listener, Handlers

MQTT ingestion surface separate from `app/Http/`.

- [ ] **MQTT-01**: Operator can run `php artisan irms:mqtt-listen` locally (as the 6th `composer run dev` process) and in production under a dedicated `irms-mqtt` Supervisor program, not under Horizon
- [ ] **MQTT-02**: `app/Mqtt/TopicRouter` dispatches incoming messages to 4 handler classes (Recognition, Ack, Heartbeat, OnlineOffline) based on topic pattern, with unmatched topics logged not silently dropped
- [ ] **MQTT-03**: `RecognitionHandler` parses RecPush payloads including firmware quirks (`personName` vs `persionName`), stores base64 face crop + scene image to a private disk under date-partitioned directories, and persists the raw payload to `recognition_events.raw_payload` (JSONB)
- [ ] **MQTT-04**: Listener rotates cleanly every hour via `--max-time=3600` and reconnects automatically when the broker disconnects, without losing subscription state
- [ ] **MQTT-05**: Dispatcher sees a `mqtt_listener_health` banner on the dispatch console if the listener misses 3 consecutive heartbeats (≥60s gap), so silent listener death is visible
- [ ] **MQTT-06**: `config/mqtt-client.php` has separate subscriber and publisher connections so enrollment publish cannot block recognition subscribe

### CAMERA — Camera Management + Dispatch Map Layer

Admin CRUD + WebGL map layer reusing IRMS v1.0 Unit patterns.

- [ ] **CAMERA-01**: Admin can create, edit, decommission, and recommission cameras via `/admin/cameras` following the `AdminUnitController` pattern (auto-generated `CAM-01`/`CAM-02` IDs alongside MQTT `device_id`)
- [ ] **CAMERA-02**: Admin picks a camera location on a MapLibre GL JS picker (ported from FRAS's Mapbox picker, rewritten for MapLibre), with forward geocoding to populate `address` + PostGIS barangay auto-assignment
- [ ] **CAMERA-03**: Cameras render as a toggleable WebGL symbol layer on the dispatch console MapLibre map (no HTML overlays, no `mapbox-gl` import — CI bundle check enforces)
- [ ] **CAMERA-04**: Dispatcher sees a live per-camera status indicator (online, offline, degraded) on the map that updates in real-time via `CameraStatusChanged` broadcast on the private `fras.cameras` channel
- [ ] **CAMERA-05**: `HeartbeatHandler` updates `cameras.last_heartbeat_at` on every heartbeat; a scheduled watchdog flips cameras to `offline` after a configurable gap (default 90s) and broadcasts `CameraStatusChanged`
- [ ] **CAMERA-06**: Camera deletion is blocked if any `camera_enrollments` row is `syncing` or `pending` for that camera (prevents orphaned enrollment state)

### PERSONNEL — Personnel Management + BOLO + Enrollment Sync

Watch-list and enrollment pipeline.

- [ ] **PERSONNEL-01**: Admin can CRUD personnel records with name, category (`block`, `missing`, `lost_child`, `allow`), optional `expires_at`, and `consent_basis` text field, under `/admin/personnel`
- [ ] **PERSONNEL-02**: Admin uploads a personnel photo that `FrasPhotoProcessor` (Intervention Image v4) validates (≤1MB, ≤1080p), resizes, re-encodes as JPEG, and hashes (MD5) for dedup
- [ ] **PERSONNEL-03**: Personnel photos served via an unguessable-UUID public URL that cameras can HTTP-fetch during enrollment, with the URL revoked automatically once the enrollment ACK is received
- [ ] **PERSONNEL-04**: Creating, updating, or deleting a personnel record enqueues `EnrollPersonnelBatch` jobs for all active cameras, wrapped in `WithoutOverlapping('enrollment-camera-{id}')->expireAfter(300)` so only one enrollment runs per camera at a time
- [ ] **PERSONNEL-05**: Admin sees per-camera enrollment progress (pending / syncing / done / failed) with a retry-one-camera button and a resync-all-cameras button, updating live via `EnrollmentProgressed` broadcast on the private `fras.enrollments` channel
- [ ] **PERSONNEL-06**: Personnel with `expires_at` in the past are auto-unenrolled from all cameras by a scheduled job (lost_child and missing categories expire so watch-list doesn't grow unbounded)
- [ ] **PERSONNEL-07**: `AckHandler` correlates camera enrollment ACKs back to `camera_enrollments` rows via cache-backed request-ID mapping, with per-error-code retry policy (transient → retry, terminal → surface to admin)

### RECOGNITION — Pipeline + IoT-Intake Bridge

The integration seam: MQTT recognition event → IRMS Incident.

- [ ] **RECOGNITION-01**: Every MQTT RecPush event (regardless of severity) persists to `recognition_events` with classified severity (Critical / Warning / Info), matched personnel FK (nullable), confidence score, and both image paths
- [ ] **RECOGNITION-02**: `FrasIncidentFactory::createFromRecognition()` creates an IRMS Incident from a Critical recognition with `channel = IoT`, `priority = P2` (default), `IncidentTimeline.event_data.source = 'fras_recognition'`, and sets `recognition_events.incident_id` to complete the FK round-trip
- [ ] **RECOGNITION-03**: `IoTWebhookController` is refactored to delegate to `FrasIncidentFactory::createFromSensor()` so the existing IoT sensor flow and the new recognition flow share one adapter, preserving v1.0 IoT intake behavior
- [ ] **RECOGNITION-04**: Dispatcher sees a one-click "Escalate to P1" button on Incidents created from recognition events; clicking it updates the Incident priority and writes an audit timeline entry
- [ ] **RECOGNITION-05**: Warning-severity recognition events broadcast on `fras.alerts` for operator awareness but **never** auto-create Incidents (`FrasIncidentFactory` returns null for non-Critical)
- [ ] **RECOGNITION-06**: Duplicate suppression: a second recognition event with the same `(camera_id, personnel_id)` within a configurable window (default 60s) does **not** create a second Incident (it still persists to `recognition_events` for history)
- [ ] **RECOGNITION-07**: Recognition events below a configurable confidence threshold (default 0.75) are classified as Info and never surface beyond event history
- [ ] **RECOGNITION-08**: All severity/dedup/confidence thresholds live in `config/fras.php` so field tuning doesn't require a code deploy

### ALERTS — Live Feed + Event History + Audio

Operator-facing alert surface.

- [ ] **ALERTS-01**: Operator sees a live severity-classified FRAS alert feed at `/fras/alerts` with real-time updates via the private `fras.alerts` channel
- [ ] **ALERTS-02**: Operator can acknowledge or dismiss an alert with one click; state persists and broadcasts back so other operators see the same acknowledged status
- [ ] **ALERTS-03**: Alert feed plays a severity-distinct audio cue on Critical alerts using the shared `useAlertSystem.ts` composable (no parallel Web Audio stack)
- [ ] **ALERTS-04**: Operator can filter the event history page (`/fras/events`) by date range, severity (pills), camera (select), and debounced free-text search over person name + camera label
- [ ] **ALERTS-05**: Event history paginates with numbered pages (not cursor-based) and shows replay badges when a face appears multiple times across events
- [ ] **ALERTS-06**: `useFrasFeed` composable exposes a bounded 100-alert ring buffer so long operator sessions don't leak memory
- [ ] **ALERTS-07**: Operator can manually promote a non-Critical recognition event to an Incident from the event-detail modal (for cases where severity classification missed)

### INTEGRATION — Responder + IntakeStation + Dispatch Map

v1.0 feature surfaces gain FRAS context.

- [ ] **INTEGRATION-01**: Dispatch console map gains a toggleable cameras layer alongside existing incidents + units layers, with a pulse animation triggered by `RecognitionAlertReceived` on the matched camera marker
- [ ] **INTEGRATION-02**: Responder SceneTab on an Incident created from a recognition event shows a "Person of Interest" accordion with the face crop, personnel name + category, camera label, and event timestamp (responders see face crop but not raw scene image per DPA role-gating)
- [ ] **INTEGRATION-03**: IntakeStation gains a 4th channel rail showing recent recognition events, so operators can triage FRAS alerts alongside SMS/App/IoT/Walk-in in one workspace
- [ ] **INTEGRATION-04**: `useDispatchFeed` remains unchanged — recognition-created Incidents flow through existing `IncidentCreated` broadcast, so the dispatch console composable doesn't fork

### DPA — Privacy, Audit, Retention (milestone-completion gate)

Philippine Data Privacy Act (RA 10173) compliance package for LGU deployment. Phase 22 blocks on CDRRMO legal sign-off.

- [ ] **DPA-01**: Published `/privacy` route with a CDRRMO-branded Privacy Notice page covering biometric data collection, lawful basis, retention, and data-subject rights
- [ ] **DPA-02**: `fras_access_log` table records an append-on-every-view audit entry (actor, IP, image ID, timestamp) whenever a human fetches a recognition image
- [ ] **DPA-03**: Raw recognition images served only via auth-signed 5-minute URLs scoped to operator/supervisor/admin roles — responders and dispatchers explicitly excluded
- [ ] **DPA-04**: Scheduled retention cleanup purges scene images at 30 days and face crops at 90 days by default (configurable), with an active-incident-protection clause so images referenced by open Incidents are never purged
- [ ] **DPA-05**: Admin can configure retention windows in `config/fras.php` so CDRRMO legal can tighten or loosen the window without a deploy
- [ ] **DPA-06**: PIA (Privacy Impact Assessment) template + signage-template generator + operator training notes committed to `docs/dpa/` for CDRRMO legal / Butuan LGU Data Privacy Officer handoff
- [ ] **DPA-07**: New gates (`view-fras-alerts`, `manage-cameras`, `manage-personnel`, `trigger-enrollment-retry`, `view-recognition-image`) extend the existing 9 gates without creating a new role, with supervisor + admin having full access and operator having view-only on alerts

## Future Requirements (deferred from v2.0)

- Inertia v2 → v3 upgrade (its own milestone — FRAS pages port down to v2 at copy-time)
- Lost-child SMS auto-broadcast to barangay captains (requires Semaphore SMS un-stub + agency MOU)
- Stranger-detection via MQTT `Snap` topic (low value for CDRRMO dispatch, high noise)
- Capacitor APK responder wrapper (carried over from v1.0)
- Bulk CSV personnel import
- FRAS video streaming / live feed (beyond event history)
- Behavioral analytics (loitering, crowd detection)
- Additional biometric modalities (fingerprint, iris)
- Multi-tenancy (single-LGU Butuan deployment still proven sufficient)

## Out of Scope (v2.0)

Explicit exclusions with reasoning:

- **Source-merge of FRAS repo into IRMS** — FRAS continues as a standalone HDSystem product at `/Users/helderdene/fras` for other clients. IRMS gets its own CDRRMO-tailored port, not a file-level merge.
- **`mapbox-gl` dependency** — IRMS's MapLibre setup is the map baseline. FRAS's Mapbox picker gets rewritten for MapLibre. CI bundle check enforces.
- **New `IncidentChannel` enum value** — recognition events route via existing `IncidentChannel::IoT`. Disambiguation happens at `event_data.source = 'fras_recognition'`, not at the channel level.
- **Auto-P1 on Critical recognition** — decision already locked (Critical → P2 default with dispatcher escalation). Prevents dispatcher alert-fatigue during the first weeks of field tuning.
- **Responder access to raw recognition scene images** — face crop context only. Scene images carry higher PII burden and are restricted to operator/supervisor/admin.
- **Running MQTT under Horizon** — architectural separation. Listener lives under its own Supervisor program so Horizon restart/redeploy doesn't interrupt camera ingestion.
- **External API unblocks (SMS, PAGASA, hospital FHIR)** — still stubbed per v1.0 Out of Scope; revisit when agency MOUs land.

## Traceability

Phase mapping (populated 2026-04-21 by gsd-roadmapper). All 43 v2.0 requirements mapped to exactly one phase — 100% coverage, no orphans.

| Requirement | Phase | Status |
|-------------|-------|--------|
| FRAMEWORK-01 | Phase 17 | Complete |
| FRAMEWORK-02 | Phase 17 | Complete |
| FRAMEWORK-03 | Phase 17 | Complete |
| FRAMEWORK-04 | Phase 18 | Pending |
| FRAMEWORK-05 | Phase 18 | Pending |
| FRAMEWORK-06 | Phase 18 | Pending |
| MQTT-01 | Phase 19 | Pending |
| MQTT-02 | Phase 19 | Pending |
| MQTT-03 | Phase 19 | Pending |
| MQTT-04 | Phase 19 | Pending |
| MQTT-05 | Phase 19 | Pending |
| MQTT-06 | Phase 19 | Pending |
| CAMERA-01 | Phase 20 | Pending |
| CAMERA-02 | Phase 20 | Pending |
| CAMERA-03 | Phase 20 | Pending |
| CAMERA-04 | Phase 20 | Pending |
| CAMERA-05 | Phase 20 | Pending |
| CAMERA-06 | Phase 20 | Pending |
| PERSONNEL-01 | Phase 20 | Pending |
| PERSONNEL-02 | Phase 20 | Pending |
| PERSONNEL-03 | Phase 20 | Pending |
| PERSONNEL-04 | Phase 20 | Pending |
| PERSONNEL-05 | Phase 20 | Pending |
| PERSONNEL-06 | Phase 20 | Pending |
| PERSONNEL-07 | Phase 20 | Pending |
| RECOGNITION-01 | Phase 21 | Pending |
| RECOGNITION-02 | Phase 21 | Pending |
| RECOGNITION-03 | Phase 21 | Pending |
| RECOGNITION-04 | Phase 21 | Pending |
| RECOGNITION-05 | Phase 21 | Pending |
| RECOGNITION-06 | Phase 21 | Pending |
| RECOGNITION-07 | Phase 21 | Pending |
| RECOGNITION-08 | Phase 21 | Pending |
| INTEGRATION-01 | Phase 21 | Pending |
| INTEGRATION-03 | Phase 21 | Pending |
| INTEGRATION-04 | Phase 21 | Pending |
| ALERTS-01 | Phase 22 | Pending |
| ALERTS-02 | Phase 22 | Pending |
| ALERTS-03 | Phase 22 | Pending |
| ALERTS-04 | Phase 22 | Pending |
| ALERTS-05 | Phase 22 | Pending |
| ALERTS-06 | Phase 22 | Pending |
| ALERTS-07 | Phase 22 | Pending |
| INTEGRATION-02 | Phase 22 | Pending |
| DPA-01 | Phase 22 | Pending |
| DPA-02 | Phase 22 | Pending |
| DPA-03 | Phase 22 | Pending |
| DPA-04 | Phase 22 | Pending |
| DPA-05 | Phase 22 | Pending |
| DPA-06 | Phase 22 | Pending |
| DPA-07 | Phase 22 | Pending |

**Coverage:** 43/43 requirements mapped (100%). No orphans.

---
*Last updated: 2026-04-21 — v2.0 roadmap + traceability populated*
