# Milestones

## v2.0 FRAS Integration (Shipped: 2026-04-22)

**Delivered:** Full integration of HDSystem's Face Recognition Alert System into IRMS — IP-camera MQTT ingestion, BOLO personnel enrollment, live recognition alerts, and an automated bridge that promotes Critical recognition events into dispatch-ready Incidents, all RA 10173 compliant.

**Phases completed:** 6 phases, 38 plans, 58 tasks
**Commits:** 283 commits · 465 files changed · +90k/-4k LOC
**Timeline:** 2026-04-17 → 2026-04-22 (6 days)

**Key accomplishments:**

- **Phase 17 — Laravel 12 → 13 upgrade** with byte-identical broadcast payload snapshots, CSRF middleware rename, drain-and-deploy runbook, and closes the incident-report PDF download gap (route + Gate + 10 Pest tests).
- **Phase 18 — FRAS PostgreSQL schema port**: cameras (PostGIS geography + GIST), personnel (VARCHAR+CHECK category enum), camera_enrollments pivot (idempotency UNIQUE), recognition_events (28 columns, JSONB GIN index, microsecond TIMESTAMPTZ, UNIQUE(camera_id, record_id)). 11 regression tests guard shape drift across downstream phases.
- **Phase 19 — MQTT pipeline + listener infrastructure**: dedicated `php-mqtt/laravel-client` listener running under `[program:irms-mqtt]` Supervisor (never Horizon), TopicRouter → 4 handlers (Recognition/Heartbeat/OnlineOffline/Ack), `mqtt_listener_health` watchdog banner on dispatch console, live-broker verified against cloud MQTT 148.230.99.73 with real firmware `info.facesluiceId` payload shape.
- **Phase 20 — Camera & Personnel admin + enrollment sync**: full CRUD on `/admin/cameras` and `/admin/personnel` with status live-updates via `CameraStatusChanged` broadcast, FrasPhotoProcessor photo pipeline (Intervention Image v4), unguessable-UUID enrollment photo URLs, `EnrollPersonnelBatch` with `WithoutOverlapping` per-camera lock, retention expiry scheduler, dispatch map cameras layer.
- **Phase 21 — Recognition → IoT-Intake bridge + dispatch map pulse**: `FrasIncidentFactory` service layer (single integration seam, reuses `IncidentChannel::IoT` — no new channel enum), severity-aware Mapbox pulse on camera layer, FRAS rail cards + read-only event modal + Escalate-to-P1 button, SSR-seeded 50-event buffer.
- **Phase 22 — Alert feed + event history + responder context + DPA compliance gate**: `/fras/alerts` live feed with 100-alert ring buffer and cross-operator ACK broadcast, `/fras/events` filterable/paginated history with promote-to-Incident, responder POI accordion (face crop + personnel + camera, never raw scene image per DPA role-gating), public `/privacy` page with CDRRMO branding + bilingual EN/TL toggle, `fras_access_log` audit trail, 5-minute signed URLs, retention purge (30d scene / 90d face) with active-incident protection, `docs/dpa/` package (PIA + signage + operator training), `fras:dpa:export` + `fras:legal-signoff` CLIs.

---

## v1.0 IRMS v1.0 MVP (Shipped: 2026-04-17)

**Phases completed:** 16 phases, 51 plans, 111 tasks

**Key accomplishments:**

- PostgreSQL/PostGIS database with 7 Eloquent models, Magellan spatial casts, RBAC middleware with 9 gates, 86 seeded barangay boundaries, and 8 passing foundation test files
- Admin CRUD panel with 3 controllers, 5 FormRequests, 6 Vue pages using Reka UI, and 29 Pest tests for user management, incident type taxonomy, and barangay metadata editing
- Role-based sidebar navigation with per-role Inertia shared props (9 permission flags), 8 placeholder routes, ComingSoon component, and role-aware Dashboard for all 4 IRMS roles
- IncidentController with priority suggestion, geocoding, barangay auto-assignment, and dispatch queue ordering via PostGIS and keyword-based bilingual priority scoring
- Complete dispatcher intake UI with grouped combobox triage form, priority auto-suggestion, geocoding autocomplete, 10s-polling dispatch queue, incident detail with timeline, and 5-channel monitor dashboard widget
- IoT sensor and SMS inbound webhooks with HMAC-SHA256 validation, bilingual keyword classification, and channel monitor count verification
- Reverb WebSocket server + Horizon queue dashboard + 6 broadcast events with role-based channel auth and state-sync reconnection endpoint
- Echo client with useWebSocket composable, reconnection banner, real-time dispatch queue updates via WebSocket (replacing polling), and P1/P2 audio alerts via Web Audio API
- Multi-unit assignment with PostGIS proximity ranking, forward-only status transitions, mutual aid agencies, and 6 dispatch controller actions
- Full-screen MapLibre GL JS map with WebGL incident/unit marker layers, priority-based audio tones, P1 red flash, and dispatch layout shell with stats topbar
- Full dispatch console panel system with incident queue, SLA progress bars, 90-second ack timer, status pipeline with advance, one-click proximity-ranked assignment chips, and reactive session metrics
- useDispatchFeed composable wiring all 5 broadcast events to local state, MutualAidModal with type-based agency suggestions, live ticker, and fully operational real-time dispatch console
- ResponderController with 10 tested endpoints, DomPDF PDF generation, IncidentOutcome/ResourceType enums, and 2 broadcast events covering all 11 RSPDR requirements
- Mobile-first responder UI shell with TypeScript types, useResponderSession/useGpsTracking composables, ResponderLayout with 44px topbar + 56px tab bar, StandbyScreen, StatusButton, and Station.vue page with tab switching
- SceneTab accordion with contextual checklists, vitals form, assessment tags, ChatTab with 8 quick-reply chips, and MessageBanner for cross-tab incoming message notifications
- Full responder UI completion: AssignmentNotification with priority audio + 90s countdown, MapLibre NavTab with ETA, OutcomeSheet with hospital picker, ResourceRequestModal, ClosureSummary, and complete Station.vue wiring for standby-to-closure workflow
- Unified integration architecture with SmsParser and Directions interfaces, Haversine-based stub, centralized config, and controller retrofits
- PAGASA weather advisory interface with 3-level color-coded system and HL7 FHIR R4 hospital pre-notification with Patient, Encounter, and Observation resources
- NDRRMC SitRep XML, BFP bidirectional fire sync, and PNP e-Blotter 5W1H interfaces with Philippine-specific stub implementations
- AnalyticsService with 5 KPI metrics via PostgreSQL aggregation, AnalyticsController with 7 endpoints, GeneratedReport model, and cached barangay GeoJSON with ST_SimplifyPreserveTopology
- Chart.js sparkline and line chart KPI dashboard, MapLibre choropleth incident heatmap with barangay density, and Reports download center with Inertia polling auto-refresh
- 4 compliance report jobs (DILG monthly PDF+CSV, NDRRMC SitRep with XML stub, quarterly with quarter-over-quarter comparison, annual with year-over-year) via DomPDF and league/csv, with P1 closure hook and monthly schedule
- Operator role with 6 intake gates, IntakeStationController (show/triage/manual-entry), Fortify role-redirect, and 56 passing Pest tests via TDD
- DM Sans + Space Mono fonts, 27 intake color tokens with dark mode, IntakeLayout shell with topbar/statusbar, 14 custom SVG icons, and PriBadge/ChBadge/RoleBadge/UserChip components
- Three-column IntakeStation with live WebSocket feed, channel activity bars, filter tabs, and dual-path triage form (existing incident triage + manual entry) using design system tokens
- Right-panel dispatch queue with priority-ordered triaged incidents, session metrics, priority breakdown chart, supervisor override/recall actions, and session log -- completing the full three-column intake station
- Citizen reporting REST API with tracking tokens, status mapping, rate limiting, and admin-configurable public incident types
- Standalone Vue 3 + Vue Router SPA with design tokens, 4 composables (API, geolocation, localStorage, draft state), and 6 shared components matching IRMS design system
- 1. [Rule 1 - Bug] Fixed priority type mismatch in API resources
- Remapped ~30 Shadcn CSS variables to IRMS design system tokens for app-wide cascade, plus consolidated 3 auth layouts into single CDRRMO-branded layout
- CDRRMO-branded sidebar with Space Mono labels, bg-t-bg content depth, and design-system-token-only Dashboard and Settings pages
- Design system data table pattern and color-mix() badges applied to all admin and incidents pages, replacing hardcoded neutral/color classes with CSS variable tokens
- Analytics pages restyled with design system cards and Space Mono KPI labels; dispatch console and responder station token-aligned for visual consistency across all IRMS pages
- AdminUnitController with auto-generated IDs (AMB-01, FIRE-02), decommission/recommission lifecycle, bidirectional crew sync, and scopeActive() filtering
- Units index table with type/status color badges and create/edit form with crew multi-select using Reka UI Combobox
- Incident-level dual-channel MessageSent broadcasting with dispatch sendMessage endpoint and incident channel authorization
- Dispatch-side messaging UI with collapsible Messages section, 7 quick-reply chips, unread tracking badges on queue cards and topbar, and subtle audio notification for incoming messages
- Dynamic incident-level Echo channel subscription in useResponderSession with group chat sender identification (unit callsign + name) in ChatTab
- Dispatch Messages header enlarged to 11px with chat icon and border separator; ChatTab padded 100px to clear StatusButton overlay
- Installable PWA with vite-plugin-pwa injectManifest strategy, custom TypeScript service worker precaching 104 build assets, push/notificationclick handlers, and ReloadPrompt update banner
- Backend web push infrastructure with VAPID keys, 3 notification types, event-driven listeners, ack timeout job, and subscription CRUD endpoints
- Vue push subscription composable with custom permission prompt UI, and 16 Pest tests validating entire push notification pipeline (subscription CRUD, event-to-notification dispatch, ack timeout, VAPID config)
- Sentinel navy/blue palette applied to all CSS design tokens with DM Mono and Bebas Neue fonts in both main app and report-app
- Animated Sentinel shield on auth page, simplified shield in sidebar/favicon, PWA icons on Command Blue, all IRMS/CDRRMO strings replaced with Sentinel
- Sentinel palette applied to all hardcoded hex colors in MapLibre maps, Chart.js analytics, responder/intake components, and badges -- zero old palette values remaining
- Pest closures now verify the exact PrivateChannel name and full payload shape of ChecklistUpdated + ResourceRequested broadcasts; StateSyncController widened to dispatch-active statuses and hydrates incident.resource_requests[] from timeline rows so reconnect/reload reaches resource history.
- Two new useEcho subscribers wire RSPDR-06 checklist progress and RSPDR-10 resource requests into the live dispatch console — progress bar gated by ON_SCENE/RESOLVING/RESOLVED, resource requests surface via a distinct triangle-wave arpeggio tone + ticker entry + newest-first detail-panel list, all merged with state-sync hydrated history for reload recovery.
- Commit:
- Commit:

---
