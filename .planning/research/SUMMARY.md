# Project Research Summary

**Project:** IRMS — Incident Response Management System
**Domain:** Computer-Aided Dispatch (CAD) + Field Response + Compliance Analytics
**Context:** CDRRMO Butuan City, Caraga Region XIII, Philippines
**Researched:** 2026-03-12
**Confidence:** HIGH

## Executive Summary

IRMS is a full-stack Computer-Aided Dispatch system built on an existing Laravel 12 + Vue 3 + Inertia v2 monolith. The expert approach for this domain is a hybrid architecture: Inertia handles page navigation and initial state loading, while a parallel real-time subsystem (Laravel Reverb + Laravel Echo) delivers sub-500ms updates for dispatch tracking, GPS positions, and incident status changes. The data layer extends to PostgreSQL + PostGIS, which is non-negotiable — spatial queries for barangay boundary lookups, proximity-based unit dispatch, and choropleth heatmaps cannot be done without it. The five operational layers (Foundation, Intake, Dispatch, Responder, Analytics) have a hard dependency chain and must be built in strict sequence.

The recommended approach is stub-first for all external integrations: Semaphore SMS, Mapbox geocoding, NDRRMC reporting, hospital FHIR, and inter-agency connections all require API agreements that do not yet exist. Every integration is wrapped behind a PHP interface so stubs can be swapped for real implementations without touching business logic. This lets the entire system be built, tested, and demonstrated without external dependencies. MapLibre GL JS (not Mapbox GL JS, which went proprietary in v2) provides the 3D WebGL dispatch map with WebGL-rendered markers — DOM-based Leaflet markers cannot meet the performance or spec requirements.

The top risks are: (1) WebSocket message loss during mobile network drops — mitigate with a state-sync reconnection endpoint from day one; (2) dual-dispatch race conditions — mitigate with pessimistic database locking on unit assignments; (3) PostGIS geometry vs. geography type confusion — use `geography` for all point columns from the first migration or proximity calculations will silently produce wrong distances. Five pitfalls are classified as Critical and must be addressed in their respective phases, not retrofitted later.

---

## Key Findings

### Recommended Stack

The existing Laravel 12 + Vue 3 + Inertia v2 + Tailwind 4 + Pest 4 stack is fully retained. Nine packages are added incrementally across phases — never all at once. The most significant additions are PostgreSQL + PostGIS (with `clickbar/laravel-magellan` for Eloquent integration), Laravel Reverb for self-hosted WebSockets, Laravel Horizon + Redis for queue management, MapLibre GL JS for the 3D dispatch map, and Spatie's permission and activity-log packages. See `.planning/research/STACK.md` for full version table and installation order.

**Core additions:**
- `clickbar/laravel-magellan ^2.0`: PostGIS Eloquent integration — only maintained Laravel 12-compatible PostGIS package; replaces archived `mstaack/laravel-postgis`
- `laravel/reverb ^1.8`: Self-hosted WebSocket server — first-party, zero cost, handles 30K+ connections; no Pusher dependency for critical infrastructure
- `laravel/horizon ^5.45` + Redis 7+: Queue management — dedicated queues for GPS updates, dispatch events, notifications, and PDF generation with real-time throughput monitoring
- `maplibre-gl ^5.20` + `@indoorequal/vue-maplibre-gl ^8.4`: 3D WebGL map — open-source fork of Mapbox GL JS; `updateData()` API enables partial GeoJSON updates without full re-renders
- `@turf/turf ^7.3`: Client-side geospatial — distance calculations and point-in-polygon for ETA preview; tree-shakeable
- `spatie/laravel-permission ^7.2`: RBAC — 4-role system (dispatcher, responder, supervisor, admin) with a <25-permission taxonomy
- `spatie/laravel-activitylog ^4.12`: Audit trail — append-only incident timeline required for RA 10121 / DILG compliance
- `spatie/laravel-pdf ^2.4`: Report generation — driver-based (DomPDF for simple reports, Browsershot upgrade path for complex layouts)
- `chart.js ^4.4` + `vue-chartjs ^5.3`: Analytics charts — lightweight, all required chart types, reactive Vue 3 wrappers

### Expected Features

Full feature research is in `.planning/research/FEATURES.md`. Summary below.

**Must have (table stakes):**
- Incident creation with type, priority (P1–P4 auto-suggested + dispatcher override), location via Mapbox geocoding, and auto-barangay assignment
- Auto-generated incident number (INC-YYYY-NNNNN), status workflow with lifecycle timestamps, append-only timeline
- Real-time dispatch map with WebGL incident and unit markers, priority-ordered dispatch queue
- Unit assignment workflow (3-click target), real-time GPS tracking, 90-second acknowledgement timer with audio/visual alerts
- Responder assignment receipt, status transitions, navigation deep-link, bi-directional messaging, outcome/closure form
- Role-based access for 4 roles; user-unit association; PostgreSQL + PostGIS with 86-barangay boundary data

**Should have (differentiators):**
- Proximity-based unit recommendation (PostGIS ST_DWithin ranked list; dispatcher makes final call)
- ETA calculation via Mapbox Directions API displayed at assignment time
- Embedded responder navigation mini-map (MapLibre with animated route polyline)
- Contextual arrival checklists per incident type; patient vitals capture (BP, HR, SpO2, GCS); assessment tag chips
- KPI dashboard (response time, scene arrival, resolution rate, unit utilization, false alarm rate)
- Incident heatmap choropleth by barangay; DILG monthly report automation; NDRRMC SitRep on P1 closure
- Auto-generated PDF report on incident closure; 3D map with pitch and terrain for the dispatch console
- IoT sensor webhook ingestion; SMS inbound parsing via Semaphore

**Defer (v2+):**
- Full ePCR/NEMSIS compliance — no Philippine mandate; structured vitals are sufficient
- AI/ML triage — no training data exists yet; collect data first with rule-based classification
- PWA offline mode / Capacitor APK wrapper — web-first until the online workflow is proven stable
- Multi-tenancy — Butuan City single-LGU deployment must succeed before multi-tenant complexity is justified
- Public-facing incident reporting portal — unauthenticated traffic introduces spam, moderation, and PII risk
- Automated dispatch (auto-assign nearest unit) — human dispatcher judgment is required in Philippine road conditions
- Video/image upload from responders — text-based scene documentation is sufficient for v1

### Architecture Approach

The architecture is a monolith with a dual-mode data path: Inertia page controllers handle initial state loading and standard CRUD (intake forms, analytics pages), while a real-time subsystem (Reverb + Echo composables) handles all incremental state updates after page load. The key frontend pattern is: reactive `ref()` initialized from Inertia props, mutated by Laravel Echo event handlers — no Pinia, no polling after initial load. Server-side, all business logic lives in single-purpose Action classes called by thin controllers; Events + Listeners decouple side effects (broadcasting, timeline logging, PDF generation); all external APIs are hidden behind PHP interfaces with stubs bound in the service container. See `.planning/research/ARCHITECTURE.md` for the full component diagram and six named anti-patterns.

**Major components:**
1. **Intake Layer** (IntakeController + CreateIncident Action + GeocodingService + PriorityClassifier) — receives incidents from all 5 channels, geocodes, classifies, queues for dispatch
2. **Dispatch Layer** (DispatchController + MapLibre console + AssignUnit Action + Reverb channels) — full-screen 3D map console, unit assignment with pessimistic locking, real-time GPS tracking via Echo
3. **Responder Layer** (ResponderController + mobile-optimized pages + useGpsTracking composable) — assignment receipt, status transitions, scene documentation, adaptive GPS frequency by status
4. **Analytics Layer** (PostGIS aggregate queries + Chart.js + PDF generation jobs) — KPI dashboard, choropleth heatmap, DILG/NDRRMC compliance reports
5. **Integration Layer** (interface-backed service classes, all stubbed initially) — Semaphore SMS, Mapbox, PAGASA, hospital FHIR, NDRRMC, BFP, PNP

### Critical Pitfalls

Full pitfall research is in `.planning/research/PITFALLS.md`. The five Critical pitfalls that require upfront design decisions:

1. **WebSocket message loss on reconnect** — Reverb is fire-and-forget; implement a state-sync API endpoint clients call immediately on reconnect + a Redis event stream for replaying missed events; add a visible "Reconnecting..." UI banner; use 15–30s Inertia polling as a consistency fallback alongside WebSocket
2. **Dual-dispatch race condition** — two dispatchers can simultaneously assign the same unit; use `Unit::lockForUpdate()` pessimistic locking on the assignment query; add a `version` column for optimistic concurrency; disable the Assign button on click until server confirms
3. **PostGIS geometry vs. geography type confusion** — `geometry` with SRID 4326 calculates distances in degrees, not meters; use `geography` type for all point columns; proximity queries will silently produce wrong results for Butuan City (latitude ~8.9N) if this is wrong from the first migration
4. **GPS update frequency destroying responder battery** — adaptive frequency by status (60s standby, 10s en route, 30s on scene); dead-reckoning skip if position unchanged by <50m; batch 3–5 positions per HTTP request rather than one WebSocket message per fix
5. **Mutable incident records breaking audit compliance** — RA 10121 requires immutable audit trails; use append-only `incident_timeline` table for every state change with actor, timestamp, old value, and new value; never overwrite incident status history

---

## Implications for Roadmap

Based on the dependency chain identified across all four research files, the build order is fixed. No layer can be built without its predecessors; stub scaffolding can compensate only at integration boundaries.

### Phase 1: Foundation — Database, RBAC, Core Models

**Rationale:** Every subsequent layer depends on PostgreSQL + PostGIS, the role system, and the Incident/Unit data models. This is the highest-risk setup phase because the geometry/geography type choice and the timeline table design cannot be changed later without painful migrations. Get them right here.

**Delivers:** PostgreSQL + PostGIS with 86 barangay boundary polygons and GiST indexes; spatie/laravel-permission with 4 roles and <25 permissions seeded as version-controlled fixtures; Incident, Unit, Barangay, IncidentTimeline, IncidentMessage models with PHP 8.1+ backed Enums; User model extended with role and unit association; UTC timestamp convention and PST display convention established globally.

**Features addressed:** Data foundation (table stakes), role-based access control (table stakes), barangay reference data.

**Pitfalls to avoid:** Geometry vs. geography confusion (Critical — Pitfall 3); missing GiST indexes (Moderate — Pitfall 8 — create in the same migration as spatial columns); mutable records with no timeline table (Critical — Pitfall 5 — timeline table must exist from the first incident migration); timezone convention (Minor — Pitfall 14); permission complexity creep (Moderate — Pitfall 9 — define taxonomy now, enforce it indefinitely).

**Research flag:** Standard Laravel patterns. No phase-specific research needed.

---

### Phase 2: Intake Layer — Incident Triage and Creation

**Rationale:** Dispatch requires incidents to exist. The intake triage form, geocoding service, and priority classifier are self-contained and can be fully tested without dispatch or responder functionality. This phase validates the data model with real form submissions before the map console is built.

**Delivers:** Three-panel intake UI (channel monitor, triage form, priority queue); GeocodingService wrapping Mapbox API with PostGIS ST_Contains barangay fallback; PriorityClassifier with rule-based keyword matching for P1–P4 suggestions; incident creation from all channels (form, SMS webhook stub, IoT webhook stub); auto-generated INC-YYYY-NNNNN numbers; DispatchQueueService with priority + FIFO ordering.

**Features addressed:** Incident creation form, auto-generated incident number, priority classification, incident status workflow initial states, location capture with auto-barangay, caller information, dispatch queue.

**Stack introduced:** No new packages — uses Phase 1 foundation. GeocodingService calls Mapbox API; stub with log driver initially.

**Pitfalls to avoid:** IoT webhook rate limiting (Moderate — Pitfall 13 — per-sensor 5-minute rate window from day one); fat controllers (Anti-Pattern 5 — geocoding + classification must be in Action/Service classes, not controller methods).

**Research flag:** Standard patterns. No phase-specific research needed.

---

### Phase 3: Real-Time Infrastructure — Reverb + Echo

**Rationale:** The dispatch console cannot function without WebSocket infrastructure. Dedicating a phase to this before building the map console UI ensures that channel architecture, authorization rules, and the reconnection strategy are designed and tested in isolation — not discovered as bugs mid-dispatch phase.

**Delivers:** Laravel Reverb server configured; Laravel Echo + pusher-js installed; @laravel/echo-vue composables; all broadcast events defined (IncidentCreated, IncidentStatusChanged, UnitLocationUpdated, UnitStatusChanged, AssignmentPushed, MessageSent, ResourceRequested); channel authorization in `routes/channels.php` for all private channels; reconnection state-sync endpoint (`GET /api/dispatch/state`); visible "Reconnecting..." UI component.

**Features addressed:** Real-time infrastructure, WebSocket channel architecture.

**Stack introduced:** `laravel/reverb ^1.8`, `laravel-echo ^2.3`, `pusher-js ^8.0`, `@laravel/echo-vue`.

**Pitfalls to avoid:** WebSocket message loss (Critical — Pitfall 1 — state-sync endpoint built in this phase, not retrofitted); Reverb 1,024 connection limit (Moderate — Pitfall 7 — ext-uv install + ulimit configuration documented in deployment checklist); channel authorization leaking incident data (Moderate — Pitfall 10 — explicit per-channel auth rules written alongside each event definition).

**Research flag:** Phase research recommended. `@laravel/echo-vue` has MEDIUM confidence (npm availability unverified). Fallback plan — manual Echo composable setup (~50 lines) — should be documented before starting. Reverb horizontal scaling configuration should also be reviewed.

---

### Phase 4: Dispatch Layer — Map Console and Unit Assignment

**Rationale:** The most complex phase. Depends on incidents (Phase 2) and real-time infrastructure (Phase 3). MapLibre GL JS 3D map with WebGL layers, GeoJSON source management, and Echo subscriptions all compose together here. The dual-dispatch race condition and the MapLibre `updateData()` performance pattern must both be established in this phase — neither can be retrofitted cleanly.

**Delivers:** Full-screen dispatch console with 3D MapLibre map (45-degree pitch); WebGL-rendered incident and unit markers as circle layer stacks (not HTML Marker overlays); GeoJSON sources with unique feature IDs for `updateData()` partial updates; dispatch queue sidebar with priority ordering; unit assignment modal with proximity ranking (PostGIS ST_DWithin) and Mapbox Directions ETA; pessimistic locking (`lockForUpdate`) on unit assignment; 90-second acknowledgement timer with countdown; audio/visual alerts (Web Audio API) with distinct tones per priority level; session metrics header (active incidents, units deployed, pending count); DispatchLayout (full-screen, no sidebar).

**Features addressed:** Real-time map (table stakes), dispatch queue (table stakes), unit assignment workflow (table stakes), unit status display (table stakes), real-time GPS tracking (table stakes), acknowledgement timer (table stakes), audio/visual alerts (table stakes), session metrics (table stakes), proximity-based unit recommendation (differentiator), ETA calculation (differentiator), 3D map (differentiator).

**Stack introduced:** `maplibre-gl ^5.20`, `@indoorequal/vue-maplibre-gl ^8.4`, `@turf/turf ^7.3`.

**Pitfalls to avoid:** Dual-dispatch race condition (Critical — Pitfall 2 — pessimistic locking is mandatory on the first assignment implementation); setData full re-render (Moderate — Pitfall 6 — `updateData()` with feature IDs from the first GeoJSON source created, never `setData()` in a loop); memory leaks in long sessions (Minor — Pitfall 11 — `onUnmounted()` cleanup and `shallowRef` for GeoJSON data from the first component); HTML marker overlays (Anti-Pattern 4 — circle layers strictly enforced).

**Research flag:** Phase research needed. MapLibre v5 `updateData()` with `@indoorequal/vue-maplibre-gl ^8.4` and Mapbox terrain tiles combination needs hands-on validation. ARCHITECTURE.md code examples cover the patterns but this is the highest-complexity phase.

---

### Phase 5: Responder Layer — Mobile Workflow and GPS Tracking

**Rationale:** Depends on dispatch assignments existing (Phase 4). Mobile-first design constraints are distinct from the desktop dispatch console. Adaptive GPS tracking frequency must be designed before the tracking feature is built — adding it afterward requires restructuring the composable.

**Delivers:** ResponderLayout (mobile-optimized, bottom tab navigation); Assignment.vue with status-aware tabs (Info, Nav, Scene, Outcome, Comms); status transition buttons with 44px touch targets; assignment receipt via WebSocket private channel push; adaptive GPS tracking composable (useGpsTracking) with status-based frequency (60s standby / 10s en route / 30s on scene) and 50m dead-reckoning skip; deep-link navigation to Google Maps; embedded MapLibre mini-map with route polyline (ResponderMap.vue); bi-directional messaging with quick-reply chips; contextual arrival checklists per incident type; patient vitals capture (BP, HR, SpO2, GCS); assessment tag chips; resource request from field; outcome/closure form with hospital picker.

**Features addressed:** Assignment receipt (table stakes), acknowledgement action (table stakes), status transitions (table stakes), navigation (table stakes), bi-directional messaging (table stakes), outcome/closure form (table stakes), checklists (differentiator), vitals capture (differentiator), resource request (differentiator), embedded navigation mini-map (differentiator).

**Stack introduced:** No new packages — uses Phase 3 real-time + Phase 4 map stack.

**Pitfalls to avoid:** GPS battery drain (Critical — Pitfall 4 — adaptive frequency system must be the first thing built in this phase); Mapbox token exposure (Minor — Pitfall 12 — proxy geocoding/directions through a Laravel API endpoint, URL-restrict the client-side basemap token to the production domain).

**Research flag:** Standard patterns for mobile-responsive Inertia pages and Vue composables. No phase-specific research needed.

---

### Phase 6: Integration Layer — Stubbed External Connectors

**Rationale:** Integration interfaces are defined (but stubbed) in earlier phases. This phase upgrades stubs toward real implementations as API credentials become available. Semaphore SMS and IoT webhook hardening are the most actionable; NDRRMC, BFP, and PNP integrations will likely remain stubbed until formal agreements are in place.

**Delivers:** Complete PHP interface definitions for all external APIs; custom Semaphore notification channel (~50 lines using Laravel HTTP client); SMS inbound webhook with keyword-based incident classification; IoT sensor webhook with HMAC-SHA256 signature validation and per-sensor 5-minute rate limiting; auto-generated incident closure PDF (DomPDF driver via `spatie/laravel-pdf`); Mapbox API token security hardening (server-side proxy for geocoding/directions, URL-restricted client token for basemap tiles).

**Features addressed:** Multi-channel intake — SMS and IoT (differentiator), auto-generated PDF report (differentiator), mutual aid protocol workflow (differentiator — logged as timeline entry).

**Stack introduced:** `spatie/laravel-pdf ^2.4` (if not introduced in an earlier phase).

**Pitfalls to avoid:** IoT webhook flooding (Moderate — Pitfall 13 — per-sensor rate limiting if not already present from Phase 2); Mapbox token exposure (Minor — Pitfall 12 — final remediation here).

**Research flag:** Phase research needed for Semaphore SMS API current documentation and rate limits. STACK.md explicitly flags this as a gap. No maintained Laravel package exists; custom channel implementation is required.

---

### Phase 7: Analytics Layer — KPIs, Heatmaps, and Compliance Reports

**Rationale:** Analytics is read-only against historical data accumulated from all previous layers. Building it last means real incident data exists for query validation and the data model is finalized. PostGIS aggregate queries for heatmaps are the heaviest database operations in the system and should be validated against production-scale barangay data.

**Delivers:** KPI dashboard (five core metrics with formulas and benchmarks) filterable by date range, incident type, priority, and barangay; incident heatmap choropleth using PostGIS spatial aggregation and MapLibre GL JS fill layer; DILG monthly incident report (aggregate by type/priority/barangay/outcome, PDF + CSV, scheduled via Laravel scheduler on the 1st of each month); NDRRMC Situation Report generation on P1 closure (stub POST or PDF email fallback); quarterly/annual performance reports with trend analysis; spatie/laravel-activitylog wired for audit trail queries; Chart.js dashboard (line for response time trends, bar for incident counts, doughnut for type distribution).

**Features addressed:** KPI dashboard (differentiator), choropleth heatmap (differentiator), DILG monthly report (differentiator), NDRRMC SitRep (differentiator), quarterly/annual reports (differentiator).

**Stack introduced:** `chart.js ^4.4`, `vue-chartjs ^5.3`, `spatie/laravel-activitylog ^4.12`. Optionally `laravel/pulse ^1.0` for server monitoring.

**Pitfalls to avoid:** ST_Distance without index (Moderate — Pitfall 8 — use ST_DWithin filter-then-order pattern for all spatial aggregate queries; verify with EXPLAIN ANALYZE before launch). Heavy heatmap queries should target a read replica when one is available.

**Research flag:** Phase research recommended for the official NDRRMC XML template format and DILG monthly report schema. These are not publicly documented online and require direct verification against agency submission requirements.

---

### Phase Ordering Rationale

- **Strict dependency chain enforced by architecture:** PostGIS + data models (Phase 1) → incidents exist (Phase 2) → real-time infrastructure (Phase 3) → dispatch console can show live data (Phase 4) → assignments exist for responders to receive (Phase 5) → integrations fill in external channels (Phase 6) → sufficient data exists for meaningful analytics (Phase 7). No layer can be built out of this order without stub scaffolding that duplicates later work.
- **Risk is front-loaded:** The two most architecturally irreversible decisions — `geography` column type for PostGIS (Phase 1) and `updateData()` with feature IDs for MapLibre (Phase 4) — are locked in early where the cost to fix is lowest.
- **Critical pitfalls cluster in Phases 1–5:** All five Critical pitfalls are addressed before the analytics layer. Phase 7 contains only Moderate pitfalls that are straightforward to mitigate with known patterns.
- **Stub-first keeps Phases 1–5 self-contained:** No external API key is required to build, test, or demonstrate the core dispatch-to-responder workflow. This is essential given that most external API agreements do not yet exist.

### Research Flags

Phases needing deeper research during planning:

- **Phase 3 (Real-Time Infrastructure):** `@laravel/echo-vue` has MEDIUM confidence; npm availability was not independently verified. Fallback plan (manual Echo composable) should be documented before the phase starts.
- **Phase 4 (Dispatch Layer):** MapLibre v5 `updateData()` API with `@indoorequal/vue-maplibre-gl ^8.4`; Mapbox terrain tiles integration with MapLibre 3D mode. Most complex phase; warrants a targeted spike before full implementation.
- **Phase 6 (Integration Layer):** Semaphore SMS API current documentation, inbound webhook format, and rate limits. No maintained Laravel package exists.
- **Phase 7 (Analytics):** Official NDRRMC SitRep XML schema and DILG monthly report submission format must be obtained from the agencies.

Phases with well-documented patterns (skip research-phase):

- **Phase 1 (Foundation):** PostgreSQL + PostGIS migration, Spatie Permission seeding, Eloquent model creation — all standard, HIGH-confidence Laravel patterns.
- **Phase 2 (Intake):** Inertia form controllers, Action classes, service wrappers — established patterns fully within the existing stack.
- **Phase 5 (Responder):** Mobile-responsive Inertia pages, Vue composables, Geolocation API — well-documented, no novel technical territory.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All packages verified on Packagist/npm with dates; Laravel 12 compatibility confirmed; alternatives thoroughly evaluated with clear rejection rationale |
| Features | HIGH | Domain well-established (CAD is a mature domain with federal/DHS documentation); Philippine compliance requirements verified against RA 10121 and NDRRMC frameworks; primary source is the 1,126-line IRMS Technical Specification |
| Architecture | HIGH | Core patterns (Inertia + Echo hybrid, event-driven lifecycle, interface-backed integrations) are standard, proven Laravel patterns; code examples verified; six anti-patterns explicitly documented with detection methods |
| Pitfalls | HIGH | 5 of 14 pitfalls have HIGH confidence backed by official documentation, peer-reviewed research, or known GitHub issues; 6 have MEDIUM confidence backed by community consensus across multiple sources |

**Overall confidence:** HIGH

### Gaps to Address

- **Mapbox API pricing at scale:** Free tier (100K req/month) is likely sufficient for Butuan City volume, but geocoding request patterns during intake should be validated. Server-side proxy architecture in Phase 6 reduces exposure.
- **PostGIS spatial index benchmarking:** GiST indexes are specified, but query performance with realistic Butuan City data volumes (86 barangay polygons, ~50 units, ~100 incidents/day) should be benchmarked in Phase 1 before the Phase 4 dispatch map is built against it.
- **`@indoorequal/vue-maplibre-gl` production readiness:** MEDIUM confidence. If issues arise, the fallback is direct MapLibre GL JS usage with a custom Vue composable (~100 lines). Evaluate before committing fully in Phase 4.
- **`@laravel/echo-vue` npm availability:** STACK.md flagged a 403 on npm version fetch. Verify availability before Phase 3 starts; fallback is manual Echo setup documented in ARCHITECTURE.md.
- **Semaphore SMS API:** Current documentation, rate limits, and inbound webhook format need verification when Phase 6 begins. No maintained Laravel package exists; custom channel implementation is ~50 lines using Laravel HTTP client.
- **NDRRMC/DILG report schemas:** Official submission formats must be obtained directly from the agencies before Phase 7 report generation is built. These formats are not publicly documented online.

---

## Sources

### Primary (HIGH confidence)
- `docs/IRMS-Specification.md` — 1,126-line project technical specification; primary source for all feature requirements and data models
- [Laravel Reverb Documentation](https://laravel.com/docs/12.x/reverb) — WebSocket server, connection limits, ext-uv, channel architecture
- [Laravel Broadcasting Documentation](https://laravel.com/docs/12.x/broadcasting) — event broadcasting, channel authorization
- [MapLibre GL JS Documentation](https://maplibre.org/maplibre-gl-js/docs/) — GeoJSONSource updateData API, layer configuration
- [RA 10121: Philippine DRRM Act](https://lawphil.net/statutes/repacts/ra2010/ra_10121_2010.html) — compliance requirements
- [DHS CAD Systems TechNote](https://www.dhs.gov/sites/default/files/publications/CAD_TN_0911-508.pdf) — CAD domain standards
- [Law Enforcement CAD Systems (BJA/LEITSC)](https://bja.ojp.gov/sites/g/files/xyckuh186/files/media/document/leitsc_law_enforcement_cad_systems.pdf) — feature expectations
- Packagist/npm package registry — all package versions verified 2026-03-12

### Secondary (MEDIUM confidence)
- [911 CAD Software in 2025: Key Features, Vendors, and Emerging Trends](https://www.criticalcommunicationsreview.com/ccr/business-inside/115237/911-cad-software-in-2025-key-features-vendors-and-emerging-trends) — domain feature landscape
- [NG 9-1-1 Call Taking UI Research (Frontiers)](https://www.frontiersin.org/journals/human-dynamics/articles/10.3389/fhumd.2022.670647/full) — dispatcher UX patterns, peer-reviewed
- [Paul Ramsey: Spatial Indexes and Bad Queries](http://blog.cleverelephant.ca/2021/05/indexes-and-queries.html) — PostGIS ST_DWithin pattern
- [MapLibre Issue #106: setData performance](https://github.com/maplibre/maplibre-gl-js/issues/106) — updateData() rationale
- [Reverb Issue #272: Publishing without auth](https://github.com/laravel/reverb/issues/272) — channel security concern
- [Spatie Laravel Permission: Performance Tips](https://spatie.be/docs/laravel-permission/v7/best-practices/performance)
- [DROMIC Situation Reports (DSWD)](https://dromic.dswd.gov.ph/category/situation-reports/) — Philippine compliance reporting examples
- [COA Assessment of DRRM at Local Level](https://web.coa.gov.ph/disaster_audit/doc/Local.pdf) — audit requirements

### Tertiary (LOW confidence)
- [UX Design for Crisis Situations (UXmatters)](https://www.uxmatters.com/mt/archives/2025/03/ux-design-for-crisis-situations-lessons-from-the-los-angeles-wildfires.php) — crisis UX patterns; needs validation for Philippine context
- [Computer-Aided Dispatch architecture (Wikipedia)](https://en.wikipedia.org/wiki/Computer-aided_dispatch) — general domain overview only

---
*Research completed: 2026-03-12*
*Ready for roadmap: yes*
