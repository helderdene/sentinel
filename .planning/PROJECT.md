# IRMS — Incident Response Management System

## What This Is

A full-stack emergency incident response platform for the City Disaster Risk Reduction and Management Office (CDRRMO) of Butuan City, Caraga Region. It digitizes the entire incident lifecycle — from public report intake through dispatch, field response, and post-incident analytics — replacing manual radio-log and paper-based workflows with a real-time, multi-channel system connecting dispatchers and field responders.

**Current state (post-v1.0):** 16 phases shipped — full Report → Intake → Triage → Dispatch → Response → Resolution → Reporting pipeline operational. Real-time dispatch via Laravel Reverb, PostGIS-backed geospatial queries, installable PWA with Web Push, Sentinel-branded UI, and a separate citizen reporting SPA. 82,960 LOC, 111 tasks, 51 plans. 123 requirements traced to phases. See [MILESTONES.md](MILESTONES.md) and [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md).

## Core Value

Dispatchers can receive an incident report, triage it, assign the nearest available unit, and track the response in real-time on a live map — reducing emergency response time and providing situational awareness across all active incidents.

**Still the right core value after v1.0** — shipping validated that real-time dispatch + live map + multi-channel intake is the anchor capability; everything else orbits it.

## Current Milestone: v2.0 FRAS Integration

**Goal:** Embed HDSystem's Face Recognition Alert System capabilities into IRMS so that AI IP-camera recognition events flow into CDRRMO's dispatch pipeline via the existing IoT intake channel.

**Target features:**

- MQTT pipeline + recognition event handler (ports `php-mqtt/laravel-client`, `TopicRouter`, `RecPush`, Intervention Image v3)
- Cameras CRUD + live heartbeat/online-offline status, rendered as a layer on the dispatch MapLibre map
- Personnel management + BOLO/block-list enrollment sync to cameras
- FRAS alert feed + event history with severity classification and image retention
- Recognition events ingested through existing IoT intake channel (no new channel)
- Laravel 12 → 13 upgrade (keep PostgreSQL/PostGIS; port FRAS MySQL schema)

**Key context:** FRAS stays as a separate HDSystem product at `/Users/helderdene/fras`; IRMS gets its own CDRRMO-tailored port. Must not regress v1.0 dispatch/intake/responder flows during the framework upgrade.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

**v1.0 (shipped 2026-04-17):**

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

**Pre-v1.0 (initial codebase):**

- ✓ User registration with email and password — existing (Fortify)
- ✓ Email verification after signup — existing (Fortify)
- ✓ Password reset via email link — existing (Fortify)
- ✓ Login/logout with session persistence — existing (Fortify)
- ✓ Two-factor authentication (TOTP) with recovery codes — existing (Fortify)
- ✓ Profile management (name, email, delete account) — existing
- ✓ Password change with current password confirmation — existing
- ✓ Appearance/theme switching (dark/light/system) — existing
- ✓ Vue 3 + Inertia SPA with Tailwind CSS, Wayfinder route generation — existing

### Active

<!-- Current scope for next milestone. Populated by /gsd-new-milestone. -->

**v2.0 FRAS Integration** — embed HDSystem's Face Recognition Alert System capabilities into IRMS so AI IP-camera recognition events flow into CDRRMO's dispatch pipeline via the existing IoT intake channel.

Target features:

- [ ] MQTT pipeline + recognition event handler (port `php-mqtt/laravel-client`, `TopicRouter`, `RecPush` handler, Intervention Image v3 photo processing)
- [ ] Cameras CRUD + live heartbeat/online-offline status, rendered as a layer on the dispatch MapLibre map
- [ ] Personnel management + BOLO/block-list enrollment sync to cameras (managed from IRMS admin)
- [ ] FRAS alert feed + event history (severity-classified alerts, acknowledge/dismiss, image retention)
- [ ] Recognition events ingested through existing IoT intake channel (no new channel)
- ✓ Framework upgrade: Laravel 12 → 13 (v2.0 Phase 17, 2026-04-21) — aligned ecosystem packages, drain-and-deploy runbook shipped, incident report PDF download gap closed

Key constraints:

- FRAS continues as a standalone HDSystem product at `/Users/helderdene/fras` (run in parallel indefinitely) — IRMS gets its own CDRRMO-tailored port, not a literal source merge
- Must not regress v1.0 dispatch/intake/responder behavior during the framework upgrade
- Reuses existing Reverb broadcast infrastructure; adds new channels alongside the 6 v1.0 broadcast events

**v2.0 intake candidates (deferred from v1.0):**

- [ ] Phase 15 human UAT completion (6 pending items: Scene Progress gate, live checklist update, resource audio/ticker, state-sync reload, XSS) — see STATE.md `## Deferred Items`
- [ ] Resolve `chat-input-hidden-by-status-btn` debug session (hypothesis recorded but unverified)
- [ ] `dompdf` memory exhaustion (pre-existing, explicitly deferred from v1.0)
- [ ] `UnitForm.vue` TS2322 type error (pre-existing, explicitly deferred from v1.0)
- [ ] Phase 15 Nyquist VALIDATION.md approval (still `draft`)
- [ ] Capacitor.js APK wrapper (deferred since v1.0 planning)
- [ ] AI/ML triage service (Python + FastAPI) — Phase 6 future per original spec

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Multi-tenancy (stancl/tenancy) — still deferred post-v1.0; single-LGU (Butuan) deployment proven; revisit if another LGU adopts
- Pinia state management — Inertia props + composables (useDispatchFeed, useResponderSession, useEcho) have sufficed through v1.0; keep as-is unless a concrete pain point emerges
- Real external API integrations — all external services (Semaphore SMS, PAGASA, hospital EHR, NDRRMC, BFP, PNP) remain stubbed at v1.0 ship; activate when API keys and agency MOUs are in place
- 3D map with pitch/terrain — v1.0 used 2D flat MapLibre per Phase 4 decision; upgrade only if dispatchers ask for it

**Moved in scope during v1.0:**

- PWA Service Worker + Web Push (VAPID) — originally out-of-scope, implemented in Phase 13 after responder layer stabilized

## Context

- **Client:** CDRRMO Butuan City, Caraga Region XIII
- **Prepared by:** HDSystem (HyperDrive System), Butuan City
- **Existing codebase:** Laravel 12 + Vue 3 + Inertia v2 starter with Fortify auth (registration, login, 2FA, password reset, email verification), user settings (profile, password, appearance), and Wayfinder route generation. Served locally via Laravel Herd at `irms.test`.
- **Specification:** Full technical spec at `docs/IRMS-Specification.md` (1,137 lines) covering all five layers, data models, API reference, real-time events, priority system, roles, mobile approach, external integrations, infrastructure, and security.
- **Target deployment:** DigitalOcean (Droplet + Managed PostgreSQL + Redis), Nginx, PHP-FPM, Laravel Reverb on port 6001, Horizon for queues.
- **Target users:** CDRRMO dispatchers (desktop), field responders (mobile web), supervisors/Mayor's Office (desktop dashboards).

## Constraints

- **Tech stack:** Laravel 12 + Vue 3 + Inertia v2 + TypeScript + Tailwind CSS 4 — already established, must continue
- **Database:** PostgreSQL + PostGIS required for spatial queries (geocoding, barangay assignment, proximity search, heatmaps)
- **Real-time:** Laravel Reverb (Pusher-compatible WebSocket) — spec mandates sub-500ms message delivery
- **Maps:** MapLibre GL JS with Mapbox basemap/geocoding/directions APIs — all markers as WebGL layers (no HTML overlays)
- **Auth:** Laravel Fortify already in place — extend with role/permission system, not replace
- **Browser support:** Chrome 110+, Edge 110+ (desktop dispatch/analytics), Safari 16.4+ (iOS responder)
- **External APIs:** All stubbed initially — must be architecturally ready for real integrations without refactoring
- **Performance:** API p95 < 200ms, map initial load < 3s on 4G, GPS update processing < 1s

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| PostgreSQL + PostGIS from the start | Spatial queries are core to dispatch, geocoding, and analytics — SQLite can't do this | ✓ Good — Magellan casts + ST_Contains/ST_SimplifyPreserveTopology powered dispatch proximity ranking, barangay lookup, and heatmap |
| Laravel Reverb for WebSocket | Real-time unit tracking and dispatch updates are fundamental, not an add-on | ✓ Good — 6 broadcast events with role-based channel auth; Phase 15 added 2 more live subscribers without architecture changes |
| MapLibre GL JS (WebGL markers) | Spec requires 3D map with animated markers; HTML overlays won't perform at scale | ✓ Good — shipped 2D flat; WebGL marker layers absorbed heavy marker counts; 3D deferred |
| Web-first responder, PWA later | Get the responder workflow right in browser first, add offline/PWA shell after | ✓ Good — validated web-first in Phases 5 + 12, then Phase 13 shipped installable PWA with Web Push |
| Stub all external integrations | No API keys or agency agreements yet; build integration layer with mock endpoints | ✓ Good — Phase 6 delivered contracts + stubs (SMS, Directions, PAGASA, FHIR R4, NDRRMC, BFP, PNP); real wiring is plug-in |
| Role-based access early | Dispatcher vs responder vs supervisor views are fundamentally different; can't add later | ✓ Good — 9 gates landed in Phase 1; Phase 8 cleanly added 5th role (operator) without refactor |
| Defer multi-tenancy | Single-LGU (Butuan City) first; multi-tenant adds complexity with no immediate value | ✓ Good — single-LGU deployed; no regret |
| Extend Fortify, not replace | Auth scaffolding is working; add roles/permissions alongside existing Fortify setup | ✓ Good — Fortify TOTP + role redirect + custom LoginResponse worked through all 16 phases without friction |
| **v1.0 milestone decisions** |  |  |
| Magellan over raw PostGIS SQL | Type-safe spatial casts in Eloquent | ✓ Good — no raw SQL needed for 95% of geospatial queries |
| Custom role enum + gates (not Spatie) | 4 fixed roles, no permission UI needed | ✓ Good — gates remained legible; Phase 8 added operator in one migration |
| Named Wayfinder imports (not default) | Tree-shaking + matches useGpsTracking analog | ✓ Good — resolved as policy in Phase 16 D-07 |
| TDD for Phase 8 operator role | High-stakes role redirect + 6 gates | ✓ Good — 56 tests caught edge cases during implementation |
| Pest convention guards | Belt-and-suspenders vs literal URL regression | ✓ Good — Phase 16 shipped `WayfinderConventionTest.php`; future violations fail CI |
| `audited:` key in VALIDATION frontmatter (not `approved:`) | Match Phase 13 precedent verbatim for frontmatter consistency | ✓ Good — Phase 16 resolved across Phase 14 approval |

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
*Last updated: 2026-04-21 — v2.0 Phase 17 (Laravel 13 upgrade) complete*
