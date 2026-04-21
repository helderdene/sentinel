# Roadmap: IRMS

## Overview

IRMS (Incident Response Management System) is a full-stack platform for the CDRRMO of Butuan City. It manages the full emergency incident lifecycle: Report → Intake → Triage → Dispatch → Response → Resolution → Reporting. The system has five operational layers: Intake, Dispatch, Responder, Integration, and Analytics.

**v2.0 FRAS Integration** embeds HDSystem's Face Recognition Alert System into the live IRMS platform: MQTT IP-camera ingestion, BOLO personnel enrollment, recognition alerting, and a bridge that turns Critical recognition events into IoT-channel Incidents — all without regressing v1.0 dispatch/intake/responder behavior, without introducing `mapbox-gl`, and without running MQTT under Horizon. FRAS continues to ship standalone at `/Users/helderdene/fras`; IRMS gets its own CDRRMO-tailored port. The load-bearing integration seam is a single `FrasIncidentFactory` service that reuses `IncidentChannel::IoT` (no new channel enum). Phase 22 is the milestone-completion gate and blocks on CDRRMO legal sign-off for the Data Privacy Act package.

## Milestones

- ✅ **v1.0 IRMS MVP** — Phases 1-16 (shipped 2026-04-17) → [archive](milestones/v1.0-ROADMAP.md)
- 🚧 **v2.0 FRAS Integration** — Phases 17-22 (started 2026-04-21)

## Phases

**Phase Numbering:**
- Integer phases (1-16): v1.0 milestone work (shipped)
- Integer phases (17-22): v2.0 FRAS Integration
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

<details>
<summary>✅ v1.0 IRMS MVP (Phases 1-16) — SHIPPED 2026-04-17</summary>

- [x] Phase 1: Foundation (3/3 plans) — PostgreSQL + PostGIS, core data models, RBAC with 4 roles, barangay boundaries
- [x] Phase 2: Intake (3/3 plans) — Multi-channel incident triage, geocoding, priority classification, dispatch queue
- [x] Phase 3: Real-Time Infrastructure (2/2 plans) — Laravel Reverb WebSocket server, broadcast events, channel auth
- [x] Phase 4: Dispatch Console (4/4 plans) — 2D MapLibre map, unit assignment, proximity ranking, audio alerts
- [x] Phase 5: Responder Workflow (4/4 plans) — Mobile-optimized assignment receipt, GPS tracking, scene docs, messaging
- [x] Phase 6: Integration Layer (3/3 plans) — Stubbed external connectors (SMS, geocoding, weather, hospital, agencies)
- [x] Phase 7: Analytics (3/3 plans) — KPI dashboard, heatmap, DILG/NDRRMC/quarterly/annual compliance reports
- [x] Phase 8: Operator Role & Intake Station (4/4 plans) — 5th role, TRIAGED status, full-screen intake station UI
- [x] Phase 9: Public Citizen Reporting App (3/3 plans) — Mobile-first Vue SPA for citizen emergency reports
- [x] Phase 10: Design System Alignment (5/5 plans) — CSS variable remapping, auth branding, data tables, token alignment
- [x] Phase 11: Implement Units CRUD (2/2 plans) — Admin CRUD with auto-generated IDs, crew assignment, decommission
- [x] Phase 12: Bi-directional Communication (4/4 plans) — Incident-level group chat, dispatch UI, responder multi-participant
- [x] Phase 13: PWA Setup (3/3 plans) — Installable PWA, service worker caching, Web Push for assignments + P1 alerts
- [x] Phase 14: Sentinel Rebrand (3/3 plans) — Full visual rebrand: navy/blue palette, DM Mono, animated shield, app rename
- [x] Phase 15: Close RSPDR Real-Time Dispatch Visibility (2/2 plans) — Gap closure for RSPDR-06, RSPDR-10 broadcast wiring
- [x] Phase 16: v1.0 Hygiene & Traceability Cleanup (3/3 plans) — Wayfinder URL swaps, REQUIREMENTS.md backfill (102→123)

**Totals:** 16 phases, 51 plans, 111 tasks — full archive: [v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md)

</details>

### 🚧 v2.0 FRAS Integration (Phases 17-22)

- [x] **Phase 17: Laravel 12 → 13 Upgrade** — Feature-free framework upgrade; six v1.0 broadcast events emit identical payloads pre/post; Horizon drain-and-deploy protocol documented (completed 2026-04-21)
- [x] **Phase 18: FRAS Schema Port to PostgreSQL** — Feature-free schema port: 4 empty FRAS tables with UUID PKs, JSONB + GIN indexes, TIMESTAMPTZ, Magellan geography; Pest runs FRAS groups on Postgres (completed 2026-04-21)
- [ ] **Phase 19: MQTT Pipeline + Listener Infrastructure** — Dedicated `irms-mqtt` Supervisor program (not under Horizon), `irms:mqtt-listen` command, TopicRouter + 4 handlers, listener-health heartbeat banner
- [ ] **Phase 20: Camera + Personnel Admin + Enrollment** — Admin CRUD for cameras + personnel, MapLibre camera picker, `CameraEnrollmentService` with per-camera mutex, BOLO categories with expiry auto-unenroll
- [ ] **Phase 21: Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail** — `FrasIncidentFactory` bridges Critical recognitions to `IncidentChannel::IoT` Incidents at P2 with one-click P1 escalation; dispatch map cameras layer with pulse animation; IntakeStation gains a 4th rail
- [ ] **Phase 22: Alert Feed + Event History + Responder Context + DPA Compliance** — Operator `/fras/alerts` + `/fras/events`; responder SceneTab Person-of-Interest accordion; Privacy Notice route, `fras_access_log`, signed 5-min URLs, retention purge with active-incident protection — milestone gate: CDRRMO legal sign-off

## Phase Details

### Phase 17: Laravel 12 → 13 Upgrade
**Goal**: IRMS v1.0 runs unchanged on Laravel 13 — no user-visible behavior change, no broadcast payload drift, no queued-job corruption — so every downstream FRAS phase can absorb framework churn independently from feature churn
**Depends on**: Nothing new (builds on shipped v1.0 at Phase 16)
**Requirements**: FRAMEWORK-01, FRAMEWORK-02, FRAMEWORK-03
**Success Criteria** (what must be TRUE):
  1. The full v1.0 Pest suite (all feature + unit tests shipped through Phase 16) passes green against Laravel 13 with no test modifications beyond what Laravel 13's own upgrade guide requires
  2. The six existing v1.0 broadcast events (IncidentCreated, IncidentTriaged, UnitAssigned, UnitStatusChanged, ChecklistUpdated, ResourceRequested) emit byte-identical JSON payloads pre- and post-upgrade, verified by payload-snapshot assertions
  3. An admin following the documented Horizon drain-and-deploy protocol can deploy Laravel 13 without any queued job executing under a mixed-version worker (drain → deploy → restart is reproducible and documented in `docs/operations/`)
  4. A dispatcher completing a full Report → Triage → Dispatch → Acknowledge → OnScene → Resolve cycle on the upgraded build sees no behavioral difference from the v1.0 build (spot-verified against v1.0 UAT scripts)
  5. Inertia v2 is pinned and Fortify features are explicitly listed — no surprise passkey surface or v3 shim activates during the upgrade
**Plans:** 4/4 plans complete
Plans:
- [x] 17-01-PLAN.md — Wave 1: capture six byte-identical broadcast payload snapshots on Laravel 12 (FRAMEWORK-02 regression baseline, D-04 Commit 1)
- [x] 17-02-PLAN.md — Wave 2 (RESCOPED 2026-04-21): atomic composer update bumping framework ^12→^13 + 11 aligned packages (tinker, magellan, dompdf, horizon, reverb, fortify, wayfinder, inertia-laravel, boost, pest) + PHP floor ^8.3 + routes/web.php CSRF rename. 6/6 broadcast snapshots byte-identical on L13. All full-suite failures classified as baseline Family A/B (no new regressions).
- [x] 17-03-PLAN.md — Wave 3 (NARROWED scope — aligned package bumps absorbed into Wave 2): Wayfinder TS regen (`php artisan wayfinder:generate`), docs/operations/laravel-13-upgrade.md runbook with drain-and-deploy + rollback (FRAMEWORK-03), final Horizon health check + v1.0 UAT spot-check
- [x] 17-04-PLAN.md — Wave 4 (GAP CLOSURE 2026-04-21): close the single UAT gap from Test 1 (17-HUMAN-UAT.md) — add GET /incidents/{incident}/report.pdf route + `download-incident-report` Gate + Wayfinder action + conditional Download Report button on incidents/Show.vue + 10-case Pest feature test. Pre-existing v1.0 gap; PDF was already generated but never exposed.

### Phase 18: FRAS Schema Port to PostgreSQL
**Goal**: The four FRAS tables exist empty in IRMS's PostgreSQL database with types that match IRMS conventions (UUID PKs, JSONB, TIMESTAMPTZ, Magellan geography) and with the idempotency constraint recognition ingestion will rely on, so Phase 19 and Phase 20 can begin persisting data without schema churn
**Depends on**: Phase 17
**Requirements**: FRAMEWORK-04, FRAMEWORK-05, FRAMEWORK-06
**Success Criteria** (what must be TRUE):
  1. The `cameras`, `personnel`, `camera_enrollments`, and `recognition_events` tables exist in the IRMS Postgres database with UUID primary keys, TIMESTAMPTZ timestamps, JSONB columns with GIN indexes on `recognition_events.raw_payload`, and `cameras.location` as Magellan `geography(POINT, 4326)`
  2. `recognition_events` enforces a `(camera_id, record_id)` unique constraint so a duplicate RecPush delivery fails at the database layer, not downstream application code
  3. The Pest test suite for FRAS test groups runs against PostgreSQL (not SQLite in-memory), so JSONB queries and geography spatial operators are exercised in CI — and existing IRMS test groups still run against the current driver without regression
  4. Every new table has a factory and a seeder following the IRMS v1.0 `UnitFactory`/`IncidentFactory` pattern, and `php artisan migrate:fresh --seed` completes green on a clean database
  5. A PostGIS `ST_DWithin` query against `cameras.location` returns expected results for a seeded camera row, verified by a dedicated feature test
**Plans:** 6/6 plans complete
Plans:
- [x] 18-01-PLAN.md — Wave 1: cameras table + CameraStatus enum + Camera model + CameraFactory (FRAMEWORK-04)
- [x] 18-02-PLAN.md — Wave 1: personnel table + PersonnelCategory enum + Personnel model + PersonnelFactory (FRAMEWORK-04)
- [x] 18-03-PLAN.md — Wave 2: camera_enrollments pivot + CameraEnrollmentStatus enum + CameraEnrollment model + factory (FRAMEWORK-04)
- [x] 18-04-PLAN.md — Wave 2: recognition_events table + RecognitionSeverity enum + RecognitionEvent model + factory with states (FRAMEWORK-04, FRAMEWORK-06)
- [x] 18-05-PLAN.md — Wave 3: mandatory Pest feature tests (CameraSpatialQueryTest + RecognitionEventIdempotencyTest) + FrasPlaceholderSeeder + FRAMEWORK-05 verification (SC2/SC3/SC4/SC5)
- [x] 18-06-PLAN.md — Wave 3: optional regression tests (SchemaTest + EnumCheckParityTest) — belt-and-suspenders drift guard for Phases 19-22

### Phase 19: MQTT Pipeline + Listener Infrastructure
**Goal**: The MQTT ingress surface is operational — a dedicated listener process is running, topics route to handlers, recognition payloads persist with raw JSONB, and operators can see the listener's health — so feature code in Phase 20 and Phase 21 can assume MQTT events land reliably
**Depends on**: Phase 18
**Requirements**: MQTT-01, MQTT-02, MQTT-03, MQTT-04, MQTT-05, MQTT-06
**Success Criteria** (what must be TRUE):
  1. Running `php artisan irms:mqtt-listen` locally (as the 6th `composer run dev` process) and running `irms-mqtt.conf` under Supervisor in production both consume Mosquitto messages and route them to the four handler classes (Recognition, Ack, Heartbeat, OnlineOffline) via `TopicRouter` — with unmatched topics written to the log, not silently dropped
  2. A test MQTT RecPush publish with both firmware spellings (`personName` and `persionName`) results in one `recognition_events` row with the raw payload in `raw_payload` JSONB, the base64 face crop and scene image persisted to a private disk under a date-partitioned directory, and no error in the log
  3. The listener rotates cleanly every hour (`--max-time=3600`) and automatically reconnects after a simulated broker disconnect without losing its subscription state — verified by publishing a message during the reconnect window and observing it land on the next tick
  4. A dispatcher sees a `mqtt_listener_health` banner on the dispatch console within 60 seconds of the listener going silent (3 missed heartbeats), so a silently-crashed listener is visible without SSH access
  5. `config/mqtt-client.php` declares separate subscriber and publisher connections, so a slow enrollment publish cannot block recognition subscription throughput (verified by a publish-blocking test that confirms recognition messages still land)
  6. The MQTT listener runs under its own Supervisor program, never under Horizon, and a Horizon restart leaves the listener untouched (verified by a Supervisor status check after `horizon:terminate`)
**Plans:** 6 plans
Plans:
- [ ] 18-01-PLAN.md — Wave 1: cameras table + CameraStatus enum + Camera model + CameraFactory (FRAMEWORK-04)
- [ ] 18-02-PLAN.md — Wave 1: personnel table + PersonnelCategory enum + Personnel model + PersonnelFactory (FRAMEWORK-04)
- [ ] 18-03-PLAN.md — Wave 2: camera_enrollments pivot + CameraEnrollmentStatus enum + CameraEnrollment model + factory (FRAMEWORK-04)
- [ ] 18-04-PLAN.md — Wave 2: recognition_events table + RecognitionSeverity enum + RecognitionEvent model + factory with states (FRAMEWORK-04, FRAMEWORK-06)
- [ ] 18-05-PLAN.md — Wave 3: mandatory Pest feature tests (CameraSpatialQueryTest + RecognitionEventIdempotencyTest) + FrasPlaceholderSeeder + FRAMEWORK-05 verification (SC2/SC3/SC4/SC5)
- [ ] 18-06-PLAN.md — Wave 3: optional regression tests (SchemaTest + EnumCheckParityTest) — belt-and-suspenders drift guard for Phases 19-22

### Phase 20: Camera + Personnel Admin + Enrollment
**Goal**: Admins can manage the camera fleet and the personnel watch-list from IRMS, and enrollment flows from IRMS to the cameras reliably — so the recognition pipeline in Phase 21 has a populated fleet and a populated watch-list to match against
**Depends on**: Phase 18 (may run in parallel with Phase 19)
**Requirements**: CAMERA-01, CAMERA-02, CAMERA-03, CAMERA-04, CAMERA-05, CAMERA-06, PERSONNEL-01, PERSONNEL-02, PERSONNEL-03, PERSONNEL-04, PERSONNEL-05, PERSONNEL-06, PERSONNEL-07
**Success Criteria** (what must be TRUE):
  1. An admin at `/admin/cameras` can create, edit, decommission, and recommission cameras following the v1.0 `AdminUnitController` pattern; new cameras get auto-generated `CAM-01`/`CAM-02` IDs alongside the MQTT `device_id`, and deletion is blocked when any `camera_enrollments` row for that camera is in `syncing` or `pending` state
  2. Placing a camera on the MapLibre picker (rewritten from FRAS's Mapbox picker) populates forward-geocoded `address` and PostGIS-assigned `barangay_id`; the saved camera renders as a WebGL symbol layer on the dispatch console map with a live per-camera status indicator (online / offline / degraded) that updates via `CameraStatusChanged` on the private `fras.cameras` channel within 500ms
  3. A scheduled watchdog flips a camera to `offline` after a configurable heartbeat gap (default 90s) and broadcasts `CameraStatusChanged`; CI bundle-check fails if any code path imports `mapbox-gl`
  4. An admin at `/admin/personnel` can CRUD personnel records with name, category (`block` / `missing` / `lost_child` / `allow`), optional `expires_at`, and `consent_basis` text — and uploading a photo routes through `FrasPhotoProcessor` (Intervention Image v4) which validates (≤1MB, ≤1080p), resizes, re-encodes as JPEG, and MD5-hashes for dedup
  5. Creating, updating, or deleting a personnel record enqueues `EnrollPersonnelBatch` jobs for every active camera on the dedicated `fras` Horizon queue, each wrapped in `WithoutOverlapping('enrollment-camera-{id}')->expireAfter(300)`; the admin sees per-camera progress (pending / syncing / done / failed) updating live via `EnrollmentProgressed` on `fras.enrollments`, plus retry-one-camera and resync-all-cameras buttons
  6. The per-personnel-photo unguessable-UUID public URL (used by cameras to HTTP-fetch during enrollment) is automatically revoked once the `AckHandler` correlates the matching camera enrollment ACK back to the `camera_enrollments` row via cache-backed request-ID mapping, with transient errors auto-retried and terminal errors surfaced to the admin
  7. A scheduled job auto-unenrolls any personnel whose `expires_at` has passed, across all cameras, so the watch-list does not grow unbounded
**Plans:** 6 plans
Plans:
- [ ] 18-01-PLAN.md — Wave 1: cameras table + CameraStatus enum + Camera model + CameraFactory (FRAMEWORK-04)
- [ ] 18-02-PLAN.md — Wave 1: personnel table + PersonnelCategory enum + Personnel model + PersonnelFactory (FRAMEWORK-04)
- [ ] 18-03-PLAN.md — Wave 2: camera_enrollments pivot + CameraEnrollmentStatus enum + CameraEnrollment model + factory (FRAMEWORK-04)
- [ ] 18-04-PLAN.md — Wave 2: recognition_events table + RecognitionSeverity enum + RecognitionEvent model + factory with states (FRAMEWORK-04, FRAMEWORK-06)
- [ ] 18-05-PLAN.md — Wave 3: mandatory Pest feature tests (CameraSpatialQueryTest + RecognitionEventIdempotencyTest) + FrasPlaceholderSeeder + FRAMEWORK-05 verification (SC2/SC3/SC4/SC5)
- [ ] 18-06-PLAN.md — Wave 3: optional regression tests (SchemaTest + EnumCheckParityTest) — belt-and-suspenders drift guard for Phases 19-22
**UI hint**: yes

### Phase 21: Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail
**Goal**: A Critical MQTT recognition event becomes an IRMS Incident through the existing IoT channel — with deduplication, confidence gating, and severity-to-priority mapping all configurable — and dispatchers see the recognition context on the existing map and IntakeStation surfaces without breaking v1.0 IoT sensor behavior
**Depends on**: Phase 19, Phase 20
**Requirements**: RECOGNITION-01, RECOGNITION-02, RECOGNITION-03, RECOGNITION-04, RECOGNITION-05, RECOGNITION-06, RECOGNITION-07, RECOGNITION-08, INTEGRATION-01, INTEGRATION-03, INTEGRATION-04
**Success Criteria** (what must be TRUE):
  1. A Critical-severity recognition event at ≥ 0.75 confidence against a block-list personnel creates exactly one IRMS Incident with `channel = IncidentChannel::IoT`, `priority = P2` (default), an `IncidentTimeline.event_data.source = 'fras_recognition'` entry, and `recognition_events.incident_id` set — and all severity / dedup / confidence thresholds are read from `config/fras.php`, not hardcoded
  2. A second recognition event with the same `(camera_id, personnel_id)` within the configurable dedup window (default 60s) does **not** create a second Incident; it still persists to `recognition_events` for history
  3. The v1.0 IoT sensor webhook behavior is preserved — `IoTWebhookController` delegates to `FrasIncidentFactory::createFromSensor()` (the factored-out v1.0 body), and the existing IoT sensor Pest tests pass unchanged; Warning severity broadcasts on `fras.alerts` for operator awareness but never auto-creates an Incident; Info severity never surfaces beyond event history
  4. A dispatcher viewing an Incident created from a recognition event sees a one-click "Escalate to P1" button that, when clicked, updates the Incident priority and writes an audit timeline entry — without taking any other action
  5. The dispatch console map gains a toggleable cameras layer alongside the existing incidents + units layers, with a pulse animation on the matched camera marker triggered by `RecognitionAlertReceived` within 500ms; `useDispatchFeed` remains unchanged (recognition-created Incidents flow through the existing `IncidentCreated` broadcast)
  6. The IntakeStation gains a 4th channel rail showing recent recognition events, so operators triage FRAS alerts alongside SMS / App / IoT / Walk-in in one workspace — verified by a load test of 50 events/sec/camera that confirms dispatch console frame rate and Reverb throttle hold up
**Plans:** 6 plans
Plans:
- [ ] 18-01-PLAN.md — Wave 1: cameras table + CameraStatus enum + Camera model + CameraFactory (FRAMEWORK-04)
- [ ] 18-02-PLAN.md — Wave 1: personnel table + PersonnelCategory enum + Personnel model + PersonnelFactory (FRAMEWORK-04)
- [ ] 18-03-PLAN.md — Wave 2: camera_enrollments pivot + CameraEnrollmentStatus enum + CameraEnrollment model + factory (FRAMEWORK-04)
- [ ] 18-04-PLAN.md — Wave 2: recognition_events table + RecognitionSeverity enum + RecognitionEvent model + factory with states (FRAMEWORK-04, FRAMEWORK-06)
- [ ] 18-05-PLAN.md — Wave 3: mandatory Pest feature tests (CameraSpatialQueryTest + RecognitionEventIdempotencyTest) + FrasPlaceholderSeeder + FRAMEWORK-05 verification (SC2/SC3/SC4/SC5)
- [ ] 18-06-PLAN.md — Wave 3: optional regression tests (SchemaTest + EnumCheckParityTest) — belt-and-suspenders drift guard for Phases 19-22
**UI hint**: yes

### Phase 22: Alert Feed + Event History + Responder Context + DPA Compliance
**Goal**: Operators have a full FRAS surface (live alert feed + searchable event history + acknowledge/dismiss + audio), responders see person-of-interest context on recognition-born Incidents, and IRMS meets its RA 10173 Data Privacy Act obligations (Privacy Notice, audit log, signed URLs, retention purge) — at which point CDRRMO legal sign-off gates the milestone
**Depends on**: Phase 21
**Requirements**: ALERTS-01, ALERTS-02, ALERTS-03, ALERTS-04, ALERTS-05, ALERTS-06, ALERTS-07, INTEGRATION-02, DPA-01, DPA-02, DPA-03, DPA-04, DPA-05, DPA-06, DPA-07
**Success Criteria** (what must be TRUE):
  1. An operator at `/fras/alerts` sees a live severity-classified feed (real-time via the private `fras.alerts` channel) with one-click acknowledge/dismiss whose state persists and broadcasts back to other operators; Critical alerts play a severity-distinct audio cue via the shared `useAlertSystem.ts` composable (no parallel Web Audio stack); `useFrasFeed` exposes a bounded 100-alert ring buffer so long sessions do not leak memory
  2. An operator at `/fras/events` can filter history by date range, severity pills, camera, and debounced free-text search over person name + camera label, paginated with numbered pages (not cursor-based); replay badges mark faces that appear across multiple events; the event-detail modal exposes a manual "promote to Incident" action for cases where severity classification missed
  3. A responder opening the SceneTab on an Incident created from a recognition event sees a "Person of Interest" accordion with the face crop, personnel name + category, camera label, and event timestamp — but never the raw scene image (per DPA role-gating)
  4. Any human fetching a recognition image hits an auth-signed 5-minute URL scoped to operator / supervisor / admin only (responders and dispatchers cannot fetch raw recognition images), and every fetch appends a row to `fras_access_log` recording actor, IP, image ID, and timestamp
  5. A scheduled retention cleanup purges scene images at 30 days and face crops at 90 days (both configurable in `config/fras.php`), with an active-incident-protection clause that never purges images referenced by open Incidents — verified by a feature test that creates an expired image linked to an open Incident and asserts it survives
  6. A CDRRMO operator or citizen visiting `/privacy` sees a published CDRRMO-branded Privacy Notice covering biometric data collection, lawful basis, retention, and data-subject rights; `docs/dpa/` contains the PIA template, signage-template generator, and operator training notes ready for the Butuan LGU Data Privacy Officer handoff
  7. Five new gates (`view-fras-alerts`, `manage-cameras`, `manage-personnel`, `trigger-enrollment-retry`, `view-recognition-image`) extend the existing 9 without creating a new role; supervisor + admin have full access, operator has view-only on alerts, and CDRRMO legal sign-off is recorded in the Phase 22 VALIDATION before the milestone closes
**Plans:** 6 plans
Plans:
- [ ] 18-01-PLAN.md — Wave 1: cameras table + CameraStatus enum + Camera model + CameraFactory (FRAMEWORK-04)
- [ ] 18-02-PLAN.md — Wave 1: personnel table + PersonnelCategory enum + Personnel model + PersonnelFactory (FRAMEWORK-04)
- [ ] 18-03-PLAN.md — Wave 2: camera_enrollments pivot + CameraEnrollmentStatus enum + CameraEnrollment model + factory (FRAMEWORK-04)
- [ ] 18-04-PLAN.md — Wave 2: recognition_events table + RecognitionSeverity enum + RecognitionEvent model + factory with states (FRAMEWORK-04, FRAMEWORK-06)
- [ ] 18-05-PLAN.md — Wave 3: mandatory Pest feature tests (CameraSpatialQueryTest + RecognitionEventIdempotencyTest) + FrasPlaceholderSeeder + FRAMEWORK-05 verification (SC2/SC3/SC4/SC5)
- [ ] 18-06-PLAN.md — Wave 3: optional regression tests (SchemaTest + EnumCheckParityTest) — belt-and-suspenders drift guard for Phases 19-22
**UI hint**: yes

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundation | v1.0 | 3/3 | Complete | 2026-03-12 |
| 2. Intake | v1.0 | 3/3 | Complete | 2026-03-13 |
| 3. Real-Time Infrastructure | v1.0 | 2/2 | Complete | 2026-03-13 |
| 4. Dispatch Console | v1.0 | 4/4 | Complete | 2026-03-13 |
| 5. Responder Workflow | v1.0 | 4/4 | Complete | 2026-03-13 |
| 6. Integration Layer | v1.0 | 3/3 | Complete | 2026-03-13 |
| 7. Analytics | v1.0 | 3/3 | Complete | 2026-03-13 |
| 8. Operator Role & Intake Station | v1.0 | 4/4 | Complete | 2026-03-13 |
| 9. Public Citizen Reporting App | v1.0 | 3/3 | Complete | 2026-03-13 |
| 10. Design System Alignment | v1.0 | 5/5 | Complete | 2026-03-13 |
| 11. Implement Units CRUD | v1.0 | 2/2 | Complete | 2026-03-13 |
| 12. Bi-directional Communication | v1.0 | 4/4 | Complete | 2026-03-14 |
| 13. PWA Setup | v1.0 | 3/3 | Complete | 2026-03-14 |
| 14. Sentinel Rebrand | v1.0 | 3/3 | Complete | 2026-03-14 |
| 15. Close RSPDR Real-Time Dispatch Visibility | v1.0 | 2/2 | Complete | 2026-04-17 |
| 16. v1.0 Hygiene & Traceability Cleanup | v1.0 | 3/3 | Complete | 2026-04-17 |
| 17. Laravel 12 → 13 Upgrade | v2.0 | 4/4 | Complete    | 2026-04-21 |
| 18. FRAS Schema Port to PostgreSQL | v2.0 | 5/6 | In progress | — |
| 19. MQTT Pipeline + Listener Infrastructure | v2.0 | 0/? | Not started | — |
| 20. Camera + Personnel Admin + Enrollment | v2.0 | 0/? | Not started | — |
| 21. Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail | v2.0 | 0/? | Not started | — |
| 22. Alert Feed + Event History + Responder Context + DPA Compliance | v2.0 | 0/? | Not started | — |
