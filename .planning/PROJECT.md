# IRMS — Incident Response Management System

## What This Is

A full-stack emergency incident response platform for the City Disaster Risk Reduction and Management Office (CDRRMO) of Butuan City, Caraga Region. It digitizes the entire incident lifecycle — from public report intake through dispatch, field response, and post-incident analytics — replacing manual radio-log and paper-based workflows with a real-time, multi-channel system connecting dispatchers and field responders.

## Core Value

Dispatchers can receive an incident report, triage it, assign the nearest available unit, and track the response in real-time on a live map — reducing emergency response time and providing situational awareness across all active incidents.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. Inferred from existing codebase. -->

- ✓ User registration with email and password — existing (Fortify)
- ✓ Email verification after signup — existing (Fortify)
- ✓ Password reset via email link — existing (Fortify)
- ✓ Login/logout with session persistence — existing (Fortify)
- ✓ Two-factor authentication (TOTP) with recovery codes — existing (Fortify)
- ✓ Profile management (name, email, delete account) — existing
- ✓ Password change with current password confirmation — existing
- ✓ Appearance/theme switching (dark/light/system) — existing
- ✓ Vue 3 + Inertia SPA with SSR, Tailwind CSS, Wayfinder route generation — existing

### Active

<!-- Current scope. Building toward these. -->

- [ ] Role-based access control (dispatcher, responder, supervisor, admin) with permissions matrix
- [ ] PostgreSQL + PostGIS database with spatial queries and barangay boundary polygons
- [ ] Intake Layer: multi-channel incident triage form (SMS, App, Voice, IoT, Walk-in)
- [ ] Intake Layer: auto-priority classifier (P1–P4) based on incident type keywords
- [ ] Intake Layer: geocoding with Mapbox API + PostGIS barangay assignment
- [ ] Intake Layer: IoT sensor webhook ingestion (flood gauge, fire alarm, weather, seismic, CCTV)
- [ ] Dispatch Layer: 3D map console with MapLibre GL JS (WebGL-rendered incident/unit markers)
- [ ] Dispatch Layer: unit assignment workflow with proximity filtering and ETA calculation
- [ ] Dispatch Layer: real-time unit tracking via Laravel Reverb WebSocket
- [ ] Dispatch Layer: dispatch queue with priority ordering and session metrics
- [ ] Dispatch Layer: mutual aid protocol for cross-agency coordination
- [ ] Dispatch Layer: audio alert system (Web Audio API) per priority level
- [ ] Responder Layer: assignment receipt with acknowledgement timer (90s)
- [ ] Responder Layer: status workflow (Standby → Acknowledged → En Route → On Scene → Resolving → Resolved)
- [ ] Responder Layer: navigation tab with embedded MapLibre mini-map and ETA
- [ ] Responder Layer: scene tab with contextual arrival checklists per incident type
- [ ] Responder Layer: patient vitals form (BP, HR, SpO₂, GCS) and quick assessment tags
- [ ] Responder Layer: outcome/closure form with hospital selection
- [ ] Responder Layer: bi-directional messaging with dispatch (quick reply chips)
- [ ] Responder Layer: resource request submission
- [ ] Integration Layer: stubbed external API connectors (Semaphore SMS, Mapbox Geocoding/Directions, PAGASA Weather)
- [ ] Integration Layer: stubbed government system connectors (hospital EHR/HL7 FHIR, NDRRMC SitRep, BFP, PNP e-Blotter)
- [ ] Analytics Layer: KPI dashboard (response time, scene arrival, resolution rate, unit utilization, false alarm rate)
- [ ] Analytics Layer: incident heatmap (choropleth by barangay with date/type/priority filters)
- [ ] Analytics Layer: automated compliance report generation (DILG monthly, NDRRMC SitRep, quarterly, annual)
- [ ] Incident data model with full lifecycle timestamps, vitals (JSONB), assessment tags, and timeline
- [ ] Units data model with GPS coordinates, status, type, agency, crew, shift
- [ ] Barangays reference table with PostGIS boundary polygons and risk levels
- [ ] Incident timeline and messaging tables
- [ ] Auto-generated incident report PDF on closure
- [ ] Laravel Reverb WebSocket server for all real-time broadcast events

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Multi-tenancy (stancl/tenancy) — deferred until single-LGU deployment is proven; adds architectural complexity too early
- PWA Service Worker / offline caching — web-first approach; add PWA shell after responder layer is stable
- Capacitor.js APK wrapper — deferred to post-v1; web app must work first
- AI/ML triage service (Python + FastAPI) — Phase 6 feature per spec; not needed for initial deployment
- Pinia state management — evaluate need as frontend complexity grows; Inertia props may suffice
- Real external API integrations — all external services (Semaphore, PAGASA, hospital EHR, NDRRMC, BFP, PNP) are stubbed; connect when API keys and agreements are in place
- Web Push notifications (VAPID) — defer until responder layer is proven in web-first mode

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
| PostgreSQL + PostGIS from the start | Spatial queries are core to dispatch, geocoding, and analytics — SQLite can't do this | — Pending |
| Laravel Reverb for WebSocket | Real-time unit tracking and dispatch updates are fundamental, not an add-on | — Pending |
| MapLibre GL JS (WebGL markers) | Spec requires 3D map with animated markers; HTML overlays won't perform at scale | — Pending |
| Web-first responder (no PWA yet) | Get the responder workflow right in browser first, add offline/PWA shell after | — Pending |
| Stub all external integrations | No API keys or agency agreements yet; build integration layer with mock endpoints | — Pending |
| Role-based access early | Dispatcher vs responder vs supervisor views are fundamentally different; can't add later | — Pending |
| Defer multi-tenancy | Single-LGU (Butuan City) first; multi-tenant adds complexity with no immediate value | — Pending |
| Extend Fortify, not replace | Auth scaffolding is working; add roles/permissions alongside existing Fortify setup | — Pending |

---
*Last updated: 2026-03-12 after initialization*
