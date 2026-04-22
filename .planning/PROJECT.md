# IRMS — Incident Response Management System

## What This Is

A full-stack emergency incident response platform for the City Disaster Risk Reduction and Management Office (CDRRMO) of Butuan City, Caraga Region. It digitizes the entire incident lifecycle — from public report intake through dispatch, field response, and post-incident analytics — replacing manual radio-log and paper-based workflows with a real-time, multi-channel system connecting dispatchers and field responders.

**As of v2.0, IRMS also includes a complete Face Recognition Alert System (FRAS):** MQTT ingestion from IP cameras, BOLO/block-list personnel enrollment, live severity-classified alerts to dispatchers, and an automated bridge that promotes Critical recognition events into dispatch-ready Incidents — all RA 10173 (Data Privacy Act) compliant, with a CDRRMO-branded bilingual Privacy Notice, access audit log, signed image URLs, and retention purge with active-incident protection.

**Current state:** 22 phases shipped across two milestones. 89 plans, 169 tasks, 283 commits since v1.0 tag. Full Report → Intake → Triage → Dispatch → Response → Resolution → Reporting pipeline operational, plus the complete FRAS recognition surface. See [MILESTONES.md](MILESTONES.md).

## Core Value

Dispatchers can receive an incident report — from any channel, including AI camera recognition — triage it, assign the nearest available unit, and track the response in real-time on a live map, reducing emergency response time and providing situational awareness across all active incidents.

**Still the right core value after v2.0** — shipping FRAS validated that the core value extends cleanly to IoT-channel recognition events without diluting it. Real-time dispatch + live map + multi-channel intake remains the anchor; recognition events are "just another intake channel" that reuse the same dispatch pipeline via `FrasIncidentFactory` → `IncidentChannel::IoT`.

## Current State

- **v1.0 IRMS MVP** — shipped 2026-04-17 (16 phases, 51 plans). Full dispatch pipeline, citizen reporting SPA, Sentinel branding, PWA + Web Push.
- **v2.0 FRAS Integration** — shipped 2026-04-22 (6 phases, 38 plans). MQTT listener, camera/personnel admin, recognition bridge, alert feed, DPA compliance gate.

**Post-ship items tracked outside the milestone:**

- **CDRRMO Data Privacy Officer legal sign-off** via `php artisan fras:legal-signoff` — CLI fully tested (LegalSignoffTest 5/5), runs once DPO completes RA 10173 review of `/privacy` page + `docs/dpa/` artifacts.
- **Phase 19 ops-environment smoke tests** (listener silence → banner, Horizon isolation under Supervisor) — documented manual verifications for the first production ops session; structural coverage already green (MqttListenerWatchdogTest 7/7, DispatchConsoleMqttHealthPropTest 4/4).

## Next Milestone Goals

TBD — to be scoped via `/gsd-new-milestone`. Likely candidates from accumulated tech debt and deferred items (see the v2.0 intake candidates section under Out of Scope):

- Nyquist VALIDATION.md audit catchup for phases 17/18/19/21 (draft → compliant).
- Capacitor.js APK wrapper (deferred since v1.0 planning).
- AI/ML triage service (Python + FastAPI) — Phase 6 future per original spec.
- Real external API integrations (Semaphore SMS, PAGASA, hospital EHR, NDRRMC, BFP, PNP) as agency MOUs land.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

**v2.0 FRAS Integration (shipped 2026-04-22):**

- ✓ Laravel 13 upgrade with byte-identical broadcast payload parity (v1.0 never regressed) — v2.0 Phase 17
- ✓ Incident report PDF download route + Gate + UI affordance (closes v1.0 gap) — v2.0 Phase 17
- ✓ FRAS PostgreSQL schema: cameras (PostGIS), personnel (CHECK enum), camera_enrollments (UNIQUE idempotency), recognition_events (28 cols, JSONB GIN, microsecond TIMESTAMPTZ) — v2.0 Phase 18
- ✓ MQTT listener under dedicated `[program:irms-mqtt]` Supervisor (not Horizon); TopicRouter → 4 handlers; watchdog banner on dispatch console — v2.0 Phase 19
- ✓ RecPush handler with firmware quirk support (`personName`/`persionName`, nested `info.facesluiceId`); raw JSONB payload persist; date-partitioned image storage on private disk — v2.0 Phase 19
- ✓ Admin Camera CRUD + live status broadcast (CameraStatusChanged); MapLibre camera picker; dispatch map cameras layer — v2.0 Phase 20
- ✓ Admin Personnel CRUD + BOLO categories (allow/block/missing/lost_child) with expires_at auto-unenroll — v2.0 Phase 20
- ✓ FrasPhotoProcessor (Intervention Image v4): ≤1MB, ≤1080p, JPEG re-encode + MD5 dedup; unguessable-UUID enrollment photo URLs — v2.0 Phase 20
- ✓ EnrollPersonnelBatch job with `WithoutOverlapping('enrollment-camera-{id}')` per-camera mutex; AckHandler correlates enrollment ACKs — v2.0 Phase 20
- ✓ FrasIncidentFactory bridges Critical recognitions to `IncidentChannel::IoT` Incidents at P2 (single integration seam, no new channel enum) — v2.0 Phase 21
- ✓ Dispatch map severity-aware Mapbox feature-state pulse on camera layer; fras.alerts Echo composable; SSR-seeded 50-event rail buffer — v2.0 Phase 21
- ✓ IntakeStation 4th rail: severity badge, rail card, read-only event modal, Escalate-to-P1 destructive action — v2.0 Phase 21
- ✓ `/fras/alerts` live feed with 100-alert ring buffer, cross-operator ACK broadcast (fras.alerts channel), P1 audio via shared useAlertSystem — v2.0 Phase 22
- ✓ `/fras/events` history with date/severity/camera/debounced-search filters, numbered pagination, replay badges, promote-to-Incident modal — v2.0 Phase 22
- ✓ Responder POI accordion (face crop + personnel + camera + timestamp); scene image absence enforced by arch test for DPA role-gating — v2.0 Phase 22
- ✓ Public `/privacy` route: CDRRMO-branded bilingual (EN/TL) DPA notice covering biometric collection, lawful basis, retention, data-subject rights — v2.0 Phase 22
- ✓ `fras_access_log` audit table: actor, IP, image ID, timestamp on every image fetch — v2.0 Phase 22
- ✓ 5-minute signed image URLs scoped to operator/supervisor/admin (responder + dispatcher explicitly 403'd) — v2.0 Phase 22
- ✓ Retention purge: 30d scene / 90d face (configurable) with active-incident protection clause — v2.0 Phase 22
- ✓ `docs/dpa/` package: PIA template, bilingual signage templates, operator training notes; `fras:dpa:export` PDF generator; `fras:legal-signoff` CLI — v2.0 Phase 22
- ✓ 5 new gates (view-fras-alerts, manage-cameras, manage-personnel, trigger-enrollment-retry, view-recognition-image) extend existing 9 without new role — v2.0 Phase 22

<details>
<summary>v1.0 IRMS MVP (shipped 2026-04-17) — 40 items</summary>

- ✓ Role-based access control with 5 roles (dispatcher, responder, supervisor, admin, operator) and 9 gates — v1.0 Phase 1 + 8
- ✓ PostgreSQL + PostGIS database with spatial queries and 86 seeded barangay polygons — v1.0 Phase 1
- ✓ Intake Layer: multi-channel incident triage (SMS, App, Voice, IoT, Walk-in) — v1.0 Phase 2 + 8
- ✓ Intake Layer: bilingual keyword-based auto-priority classifier (P1–P4) — v1.0 Phase 2
- ✓ Intake Layer: geocoding + PostGIS barangay auto-assignment — v1.0 Phase 2
- ✓ Intake Layer: IoT sensor webhook ingestion with HMAC-SHA256 validation — v1.0 Phase 2
- ✓ Dispatch Layer: 2D MapLibre GL JS console with WebGL incident/unit markers — v1.0 Phase 4
- ✓ Dispatch Layer: multi-unit assignment with PostGIS proximity ranking + ETA — v1.0 Phase 4
- ✓ Dispatch Layer: real-time updates via Laravel Reverb + useDispatchFeed composable — v1.0 Phase 3 + 4
- ✓ Dispatch Layer: session metrics, priority breakdown, live ticker, 90s ack timer — v1.0 Phase 4 + 8
- ✓ Dispatch Layer: mutual aid protocol with type-based agency suggestions — v1.0 Phase 4
- ✓ Dispatch Layer: priority-based Web Audio API alerts (P1–P4) — v1.0 Phase 4
- ✓ Responder Layer: assignment receipt + 90s acknowledgement countdown — v1.0 Phase 5
- ✓ Responder Layer: status workflow (Standby → ACK → EnRoute → OnScene → Resolving → Resolved) — v1.0 Phase 5
- ✓ Responder Layer: NavTab with MapLibre mini-map + ETA — v1.0 Phase 5
- ✓ Responder Layer: SceneTab with contextual checklists per incident type — v1.0 Phase 5
- ✓ Responder Layer: vitals form (BP/HR/SpO₂/GCS) + assessment tags — v1.0 Phase 5
- ✓ Responder Layer: OutcomeSheet with hospital picker + ClosureSummary — v1.0 Phase 5
- ✓ Responder Layer: bi-directional ChatTab with quick-reply chips + multi-participant awareness — v1.0 Phase 5 + 12
- ✓ Responder Layer: resource request modal with broadcast to dispatch — v1.0 Phase 5 + 15
- ✓ Integration Layer: stubbed external connectors (SMS, Directions, PAGASA, Hospital FHIR R4, NDRRMC, BFP, PNP) — v1.0 Phase 6
- ✓ Analytics Layer: KPI dashboard (5 metrics) with Chart.js sparklines — v1.0 Phase 7
- ✓ Analytics Layer: MapLibre choropleth incident heatmap by barangay — v1.0 Phase 7
- ✓ Analytics Layer: compliance reports (DILG monthly PDF+CSV, NDRRMC SitRep XML, quarterly, annual) — v1.0 Phase 7
- ✓ Incident data model with full lifecycle timestamps, JSONB vitals, assessment tags, timeline — v1.0 Phase 1 + 2
- ✓ Units data model with GPS coordinates, status, type, agency, crew, shift — v1.0 Phase 1 + 11
- ✓ Units CRUD with auto-generated IDs (AMB-01, FIRE-02), decommission/recommission — v1.0 Phase 11
- ✓ Barangays reference table with PostGIS polygons + risk levels — v1.0 Phase 1
- ✓ Incident timeline + messaging tables with real-time group chat — v1.0 Phase 1 + 12
- ✓ Auto-generated incident report PDF on closure via DomPDF — v1.0 Phase 5
- ✓ Laravel Reverb WebSocket server with 6 broadcast events + role-based channel auth — v1.0 Phase 3
- ✓ Intake Station three-column UI (live feed, channel bars, filter tabs, dual-path triage) — v1.0 Phase 8
- ✓ Public Citizen Reporting SPA (Vue 3 + Vue Router) with tracking tokens + rate limiting — v1.0 Phase 9
- ✓ Design system alignment: 30 CSS variables remapped, color-mix() opacity tokens, consolidated auth layouts — v1.0 Phase 10
- ✓ Installable PWA with vite-plugin-pwa, custom service worker, Web Push (VAPID), 16 Pest tests — v1.0 Phase 13
- ✓ Sentinel rebrand: navy/blue palette, DM Mono + Bebas Neue, animated shield, full app rename — v1.0 Phase 14
- ✓ Real-time dispatch visibility closure: ChecklistUpdated + ResourceRequested live broadcast wiring — v1.0 Phase 15
- ✓ v1.0 hygiene closure: Wayfinder URL swaps, REQUIREMENTS.md backfill (102→123), Phase 14 approved, Phase 10 browser-verified — v1.0 Phase 16

Pre-v1.0 (Fortify starter): user registration, email verification, password reset, login/logout with session persistence, 2FA (TOTP) with recovery codes, profile management, password change, appearance/theme switching, Vue 3 + Inertia SPA with Tailwind + Wayfinder.

</details>

### Active

<!-- Current scope for next milestone. Populated by /gsd-new-milestone. -->

*No active milestone. Use `/gsd-new-milestone` to scope v2.1.*

**Carried forward from v2.0 (post-ship):**

- [ ] CDRRMO legal sign-off via `fras:legal-signoff` CLI (waiting on DPO review of `/privacy` + `docs/dpa/`)
- [ ] Phase 19 ops smoke-test (listener-silence banner + Horizon isolation under live Supervisor)
- [ ] Nyquist VALIDATION.md audit catchup for Phases 17, 18, 19, 21 (currently `draft`)

**Candidate intake for v2.1:**

- [ ] Phase 15 human UAT completion (6 pending items: Scene Progress gate, live checklist update, resource audio/ticker, state-sync reload, XSS) — see STATE.md `## Deferred Items`
- [ ] `dompdf` memory exhaustion (pre-existing, explicitly deferred from v1.0)
- [ ] `UnitForm.vue` TS2322 type error (pre-existing, explicitly deferred from v1.0)
- [ ] Capacitor.js APK wrapper (deferred since v1.0 planning)
- [ ] AI/ML triage service (Python + FastAPI) — Phase 6 future per original spec
- [ ] Real external API integrations (Semaphore SMS, PAGASA, hospital EHR, NDRRMC, BFP, PNP) as agency MOUs land

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Multi-tenancy (stancl/tenancy) — single-LGU (Butuan) deployment proven through v2.0; revisit if another LGU adopts
- Pinia state management — Inertia props + composables (useDispatchFeed, useResponderSession, useEcho, useFrasFeed) sufficed through v2.0; keep as-is unless a concrete pain point emerges
- Real external API integrations — all external services remain stubbed at v2.0 ship; activate when API keys and agency MOUs land
- 3D map with pitch/terrain — v2.0 still uses 2D flat MapLibre; upgrade only if dispatchers ask for it
- Merging FRAS into a single codebase with IRMS — FRAS continues as standalone HDSystem product at `/Users/helderdene/fras`; IRMS runs its own CDRRMO-tailored port (validated through v2.0 Phase 21 FrasIncidentFactory seam)
- `mapbox-gl` — explicitly avoided for v2.0; MapLibre + Mapbox tile/geocoding APIs proved sufficient for the cameras layer + recognition pulse
- MQTT under Horizon — explicitly rejected in Phase 19 (Pitfall 6); dedicated Supervisor program is load-bearing

**Moved in scope during v2.0:**

- Public unauthenticated `/privacy` route — originally scoped as an authenticated settings page; v2.0 DPA compliance required public bilingual accessibility for CDRRMO legal

**Moved in scope during v1.0:**

- PWA Service Worker + Web Push (VAPID) — originally out-of-scope, implemented in Phase 13 after responder layer stabilized

## Context

- **Client:** CDRRMO Butuan City, Caraga Region XIII
- **Prepared by:** HDSystem (HyperDrive System), Butuan City
- **Codebase:** Laravel 13 + Vue 3 + Inertia v2 + TypeScript + Tailwind CSS 4 + Laravel Fortify auth + Laravel Wayfinder route generation. Reverb WebSocket, Horizon queues, dedicated `irms-mqtt` Supervisor program for MQTT ingestion. Served locally via Laravel Herd at `irms.test`.
- **Specification:** Full technical spec at `docs/IRMS-Specification.md`. FRAS-specific reference port at `/Users/helderdene/fras` (standalone HDSystem product, parallel deployment).
- **DPA compliance:** `docs/dpa/` contains PIA template, bilingual signage templates, operator training notes. RA 10173 sign-off mechanism ready via `php artisan fras:legal-signoff`.
- **Target deployment:** DigitalOcean (Droplet + Managed PostgreSQL + Redis), Nginx, PHP-FPM, Laravel Reverb on port 6001, Horizon for queues, dedicated `irms-mqtt` Supervisor program for MQTT listener.
- **Target users:** CDRRMO dispatchers + operators (desktop), field responders (mobile web), admins (desktop camera/personnel management), supervisors/Mayor's Office (desktop dashboards), public citizens (unauthenticated `/privacy` + public reporting SPA).

## Constraints

- **Tech stack:** Laravel 13 + Vue 3 + Inertia v2 + TypeScript + Tailwind CSS 4 — established through v2.0
- **Database:** PostgreSQL + PostGIS required for spatial queries + FRAS JSONB recognition_events
- **Real-time:** Laravel Reverb (Pusher-compatible WebSocket) — spec mandates sub-500ms message delivery
- **Maps:** MapLibre GL JS with Mapbox basemap/geocoding/directions APIs; no `mapbox-gl` (explicit v2.0 constraint)
- **Auth:** Laravel Fortify + 14 gates (9 v1.0 + 5 v2.0 FRAS) — extend, don't replace
- **MQTT:** `php-mqtt/laravel-client` under dedicated `[program:irms-mqtt]` Supervisor program (never Horizon)
- **DPA:** RA 10173 compliance is non-negotiable; 5-minute signed URLs, access audit log, retention purge, bilingual Privacy Notice
- **Browser support:** Chrome 110+, Edge 110+ (desktop dispatch/analytics), Safari 16.4+ (iOS responder)
- **External APIs:** All stubbed initially — architecture must absorb real integrations without refactoring
- **Performance:** API p95 < 200ms, map initial load < 3s on 4G, GPS update processing < 1s, MQTT message → alert feed < 2s

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| PostgreSQL + PostGIS from the start | Spatial queries are core to dispatch, geocoding, analytics, and FRAS camera picker | ✓ Good — extended cleanly to FRAS cameras (Magellan geography) and recognition_events (JSONB GIN) |
| Laravel Reverb for WebSocket | Real-time unit tracking, dispatch updates, and now FRAS alerts are fundamental | ✓ Good — added `fras.alerts` + camera status broadcasts without architectural changes |
| MapLibre GL JS (WebGL markers) | 3D map with animated markers; HTML overlays won't perform | ✓ Good — absorbed camera layer + severity pulse in v2.0 Phase 21 without mapbox-gl |
| Web-first responder, PWA later | Get responder workflow right in browser first | ✓ Good — PWA shipped v1.0 Phase 13 after workflow stabilized |
| Stub all external integrations | No API keys or agency agreements yet | ✓ Good — real wiring is plug-in; no refactor needed through v2.0 |
| Role-based access early | Dispatcher/responder/supervisor views are fundamentally different | ✓ Good — 9 gates v1.0 + 5 v2.0 extended cleanly with no new role |
| Defer multi-tenancy | Single-LGU first; multi-tenant adds complexity with no immediate value | ✓ Good — single-LGU deployed through v2.0; no regret |
| Extend Fortify, not replace | Auth scaffolding works; add roles alongside | ✓ Good — survived L12→L13 upgrade untouched |
| **v1.0 decisions** |  |  |
| Magellan over raw PostGIS SQL | Type-safe spatial casts in Eloquent | ✓ Good — extended to FRAS cameras table without new patterns |
| Custom role enum + gates (not Spatie) | Fixed roles, no permission UI needed | ✓ Good — v2.0 added 5 FRAS gates via same pattern |
| Named Wayfinder imports (not default) | Tree-shaking | ✓ Good — v2.0 added 20+ new action imports, no regressions |
| **v2.0 decisions** |  |  |
| Keep FRAS standalone; port into IRMS | FRAS is HDSystem product; IRMS is CDRRMO deployment | ✓ Good — `FrasIncidentFactory` seam kept the port surgical |
| Reuse `IncidentChannel::IoT` (no new enum) | Recognition events are "just another IoT intake" | ✓ Good — dispatch pipeline accepted FRAS incidents with zero new code paths |
| MQTT under dedicated Supervisor, never Horizon | Horizon `terminate` would kill the long-running subscriber | ✓ Good — `[program:irms-mqtt]` isolation held through Phase 19 |
| Accept firmware `persionName` typo + nested `info.facesluiceId` | Match HDSystem reference port verbatim | ✓ Good — real-hardware UAT proved the fallback was load-bearing |
| Bilingual public `/privacy` (EN + TL) | CDRRMO legal + LGU Data Privacy Officer require Filipino | ✓ Good — bilingual toggle landed with Day 4 shipping; unblocks DPO sign-off |
| 5-minute signed URLs + audit log on every fetch | RA 10173 Data Privacy Act mandate | ✓ Good — `fras_access_log` + FrasEventFaceController 10/10 tests passing |
| CDRRMO legal sign-off as external post-deploy gate | DPO availability is not a ship blocker | ✓ Good — `fras:legal-signoff` CLI ready; v2.0 shipped, sign-off tracked separately |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-22 — after v2.0 FRAS Integration milestone close*
