# Requirements: IRMS

**Defined:** 2026-03-12
**Core Value:** Dispatchers can receive an incident report, triage it, assign the nearest available unit, and track the response in real-time on a live map.

## v1 Requirements

### Foundation

- [x] **FNDTN-01**: System uses PostgreSQL with PostGIS extension for all spatial queries
- [x] **FNDTN-02**: Barangay reference table with 86 boundary polygons, district, risk level, and GiST spatial index
- [x] **FNDTN-03**: Role-based access control with four roles (dispatcher, responder, supervisor, admin) and permissions matrix per spec Section 9
- [x] **FNDTN-04**: User can be associated with a unit (responders linked to AMB-01, RESCUE-02, etc.)
- [x] **FNDTN-05**: Incident data model with lifecycle timestamps (dispatched_at through resolved_at), vitals JSONB, assessment_tags TEXT[], coordinates geography, and append-only timeline
- [x] **FNDTN-06**: Units data model with GPS coordinates (geography), status, type, agency, crew count, shift, and GiST spatial index
- [x] **FNDTN-07**: Incident timeline table (append-only audit log with event_type, event_data JSONB, actor_type, actor_id)
- [x] **FNDTN-08**: Incident messages table for bi-directional dispatch-responder communication
- [x] **FNDTN-09**: Laravel Reverb WebSocket server configured with channel authorization and presence channels
- [x] **FNDTN-10**: Redis configured for cache, queue (Horizon), and Reverb pub/sub

### Intake

- [x] **INTK-01**: Dispatcher can create incident with type (40+ types across 8 categories), priority (P1-P4), location, caller info, channel, and notes
- [x] **INTK-02**: System auto-generates unique incident number (INC-YYYY-NNNNN) on creation
- [x] **INTK-03**: System auto-suggests priority (P1-P4) based on incident type keywords with confidence score; dispatcher can override
- [x] **INTK-04**: Location text is geocoded via Mapbox API with Philippines filter; coordinates auto-populated
- [x] **INTK-05**: PostGIS ST_Contains query auto-assigns barangay from geocoded coordinates; dispatcher can manually correct
- [x] **INTK-06**: Dispatch queue displays all triaged incidents ordered by priority (P1 first) then FIFO within same priority
- [x] **INTK-07**: IoT sensor webhook endpoint accepts alerts with HMAC-SHA256 validation; auto-creates incidents from threshold exceedances (stubbed sensor integration)
- [x] **INTK-08**: SMS inbound webhook parses incoming messages with keyword classifier for incident type suggestion; auto-reply on creation (stubbed Semaphore integration)
- [x] **INTK-09**: Channel monitor panel shows live feed from all 5 channels with unacknowledged message highlighting and pending count badges

### Operator Role

- [x] **OP-01**: Operator case exists in `UserRole` enum with value `'operator'` as the 5th role (alongside dispatcher, responder, supervisor, admin)
- [x] **OP-02**: Triaged case exists in `IncidentStatus` enum with value `'TRIAGED'` at position 2, between `PENDING` and `DISPATCHED`
- [x] **OP-03**: 6 intake gates defined (`access-intake-station`, `create-incident-intake`, `triage-incident`, `override-priority`, `recall-incident`, `view-session-log`) with correct role matrix (operator/supervisor/admin access where appropriate)
- [x] **OP-04**: Operator login redirects to `/intake` (not `/dashboard`) via custom Fortify `LoginResponse` singleton bound in `FortifyServiceProvider`
- [x] **OP-05**: Design system tokens adopted as Tailwind utilities — DM Sans + Space Mono fonts, 27 `--color-t-*` tokens with `:root` light values and `.dark` overrides, used across intake components
- [x] **OP-06**: Intake station route (`intake.station`) accessible to operator/supervisor/admin; forbidden to dispatcher/responder via `role:operator,supervisor,admin` middleware
- [x] **OP-07**: Three-column intake station with live WebSocket feed (left channel feed at 296px, center triage form, right 304px dispatch queue) and filter tabs (All/Pending/Triaged) via `useIntakeFeed` composable subscribing to `dispatch.incidents`
- [x] **OP-08**: Triage action transitions incidents from `PENDING` to `TRIAGED` via `IntakeStationController::triage`; manual entry (`intake.store-and-triage`) creates TRIAGED incidents directly with dual timeline entries (created + triaged)
- [x] **OP-09**: Dispatch queue right panel renders priority-ordered TRIAGED incidents with per-row priority border and session metrics (SessionMetrics, PriorityBreakdown)
- [x] **OP-10**: Topbar stat pills (Incoming, Pending, Triaged, Avg Resp) update in real-time via WebSocket through `inject('topbarStats')` + `inject('tickerEvents')` chain from `IntakeStation.vue` to `IntakeTopbar.vue`
- [x] **OP-11**: Override Priority and Recall actions available to supervisor/admin only (via `v-if="canOverride"` / `v-if="canRecall"`); both write timeline entries and broadcast WebSocket events
- [x] **OP-12**: `IntakeLayout.vue` full-screen shell with 56px topbar (`IntakeTopbar`) and statusbar (`IntakeStatusbar`) wrapping the intake station body
- [x] **OP-13**: 14 custom SVG icon components under `resources/js/components/intake/icons/` (Sms, App, Voice, Iot, Walkin, Pin, User, Check, Intake, Logout, Shield, Recall, Override, Activity)
- [x] **OP-14**: Intake badge components (`PriBadge` P1–P4, `ChBadge` channel, `RoleBadge`, `UserChip` with avatar and dropdown) styled with design system tokens and used across intake surfaces
- [x] **OP-15**: Operator role included in `dispatch.incidents` channel authorization (`routes/channels.php` `$dispatchRoles`) so operators receive real-time incident events

### Dispatch

- [x] **DSPTCH-01**: 2D dispatch map rendered with MapLibre GL JS, zoom 13, centered on Butuan City, with custom dark/light vector tile styles
- [x] **DSPTCH-02**: Incident markers rendered as WebGL circle layers (halo, pulse rings, border, pin, dot) colored by priority (P1 red, P2 orange, P3 amber, P4 green)
- [x] **DSPTCH-03**: Unit markers rendered as WebGL circle layers (glow, border, body, ring, dot) colored by status (available green, en route blue, on scene yellow, offline gray)
- [x] **DSPTCH-04**: Unit GPS positions update in real-time via Reverb WebSocket (every 10s en route, 60s on scene); markers animate smoothly between positions
- [x] **DSPTCH-05**: Dispatcher can select incident from queue or map and assign one or more available units
- [x] **DSPTCH-06**: System ranks available units by proximity using PostGIS ST_DWithin; displays distance and ETA (Mapbox Directions API, stubbed)
- [x] **DSPTCH-07**: Assignment pushed to responder via WebSocket with full incident payload
- [x] **DSPTCH-08**: 90-second acknowledgement timer starts on assignment; visual countdown and audio alert on expiry with reassign/escalate suggestion
- [x] **DSPTCH-09**: Audio alerts via Web Audio API with distinct tones per priority level; P1 triggers red screen flash
- [x] **DSPTCH-10**: Session metrics displayed in console header: total incidents, triaged/pending, active incidents, units available/deployed, average handle time
- [x] **DSPTCH-11**: Mutual aid modal with suggested agencies (BFP, PNP, DSWD, adjacent LGU, DOH) based on incident type; contact info, radio channel, and request logged to timeline

### Responder

- [x] **RSPDR-01**: Responder receives assignment via WebSocket with toast notification and audio cue; full incident card with type, priority, location, notes
- [x] **RSPDR-02**: Responder can acknowledge assignment with single tap; timestamp captured and dispatch timer closed
- [x] **RSPDR-03**: Responder can transition status (Acknowledged > En Route > On Scene > Resolving > Resolved) with large touch targets (44px min); each transition broadcasts to dispatch via WebSocket
- [x] **RSPDR-04**: Navigation deep-link to Google Maps with incident coordinates; embedded MapLibre mini-map with animated route polyline, unit position, incident pulse ring, and live ETA countdown
- [x] **RSPDR-05**: Bi-directional messaging with dispatch; 8 preset quick-reply chips plus free text; message history persists for incident duration
- [ ] **RSPDR-06**: Contextual arrival checklists per incident type (cardiac, road accident, structure fire, default) with animated checkboxes and progress bar; completion % broadcast to dispatch
- [x] **RSPDR-07**: Patient vitals form: blood pressure (mmHg), heart rate (bpm), SpO2 (%), GCS score (3-15) with validation ranges and placeholders
- [x] **RSPDR-08**: Quick assessment tags as toggle chips: Conscious, Breathing, Bleeding, Unresponsive, Fracture, Burns, Shock, Chest Pain, Head Trauma, Airway Compromised, Anaphylaxis
- [x] **RSPDR-09**: Outcome selection required before closure: Treated On Scene, Transported to Hospital (with hospital picker), Patient Refused Treatment, Declared DOA, Stand Down/False Alarm
- [ ] **RSPDR-10**: Resource request from field: 6 types (additional ambulance, fire unit, police backup, rescue boat, medical officer, medevac); request creates timeline entry and dispatch notification
- [x] **RSPDR-11**: Auto-generated incident report PDF on closure: ID, type, priority, scene time, checklist %, vitals, tags, outcome, hospital, notes

### Integration

- [x] **INTGR-01**: All external integrations behind PHP interfaces bound in service container; stub implementations log calls; real implementations plug in without business logic changes
- [x] **INTGR-02**: Stubbed Mapbox Geocoding connector for forward geocoding with Philippines country filter
- [x] **INTGR-03**: Stubbed Mapbox Directions connector for road-network ETA calculation
- [x] **INTGR-04**: Stubbed Semaphore SMS connector for inbound parsing and outbound acknowledgement/status messages
- [x] **INTGR-05**: Stubbed PAGASA Weather connector for rainfall, wind, and flood advisory overlay data
- [x] **INTGR-06**: Stubbed Hospital EHR connector (HL7 FHIR R4) for patient pre-notification on transport outcome
- [x] **INTGR-07**: Stubbed NDRRMC connector for SitRep XML submission on P1 closure
- [x] **INTGR-08**: Stubbed BFP connector for bidirectional fire incident sync
- [x] **INTGR-09**: Stubbed PNP e-Blotter connector for criminal incident auto-blotter entry

### Analytics

- [x] **ANLTCS-01**: KPI dashboard with 5 metrics: average response time, average scene arrival time, resolution rate, unit utilization, false alarm rate — filterable by date range, type, priority, barangay
- [x] **ANLTCS-02**: Incident heatmap as choropleth map colored by incident density per barangay; filters for type, priority, date range (30/90/365 days); exportable as PNG
- [x] **ANLTCS-03**: DILG monthly incident report auto-generated on 1st of each month as PDF + CSV; incidents aggregated by type, priority, barangay, outcome
- [x] **ANLTCS-04**: NDRRMC Situation Report auto-generated on P1 incident closure; IRMS record mapped to NDRRMC XML template (stubbed submission); fallback PDF emailed to OCD Caraga
- [x] **ANLTCS-05**: Quarterly performance report with KPI trends, incident volume charts, response time analysis as PDF
- [x] **ANLTCS-06**: Annual statistical summary for Mayor's Office with year-over-year comparison as PDF

### Citizen Reporting

- [x] **CITIZEN-01**: Citizen can submit emergency report without authentication via mobile-first web app; report creates Incident with channel='app', status=PENDING
- [x] **CITIZEN-02**: Each citizen report generates a unique 8-character URL-safe tracking token (uppercase alphanumeric, no ambiguous chars); token stored on incident record
- [x] **CITIZEN-03**: Citizen can track report status by entering tracking token; lookup returns citizen-facing status without exposing internal INC number
- [x] **CITIZEN-04**: Curated subset of incident types shown as visual card grid; admin-configurable via show_in_public_app boolean on incident_types table; "Other Emergency" always visible
- [x] **CITIZEN-05**: GPS geolocation requested from device; if granted, auto-detect coordinates + PostGIS barangay lookup; if denied, fallback to manual barangay dropdown + address text
- [x] **CITIZEN-06**: Citizen report flows directly into operator intake feed via existing IncidentCreated broadcast event; operators triage as normal
- [x] **CITIZEN-07**: Submitted reports stored in browser localStorage for "My Reports" tab; status refreshed on page visit (poll on visit, no WebSocket)
- [x] **CITIZEN-08**: Citizen-facing status mapping: PENDING -> Received, TRIAGED -> Verified, DISPATCHED/ACKNOWLEDGED/EN_ROUTE/ON_SCENE/RESOLVING -> Dispatched, RESOLVED -> Resolved
- [x] **CITIZEN-09**: Public API endpoints under /api/v1/citizen/* with rate limiting (5 submissions/min, 60 reads/min per IP) and CORS configuration
- [x] **CITIZEN-10**: Report app is a standalone Vue 3 SPA in /report-app/ monorepo subfolder with shared design tokens (DM Sans, Space Mono, color system); mobile-first matching HTML prototype

### Design System Alignment

- [x] **DS-01**: Shadcn CSS variables (--background, --foreground, --border, --card, --primary, etc.) remap to IRMS design system tokens (--t-bg, --t-text, --t-border, etc.) in both :root and .dark blocks; all Shadcn components inherit design system colors via cascade
- [x] **DS-02**: 5-level shadow scale (shadow-1 through shadow-5) defined as CSS custom properties with border+shadow pairing per design system spec
- [x] **DS-03**: Focus ring override uses design system combined border-color + box-shadow pattern instead of Shadcn ring utility
- [x] **DS-04**: Auth pages use single unified layout with CDRRMO branding (52x52 icon, "CDRRMO Butuan City", subtitle), centered card, Level 4 shadow, 14px border-radius, fadeUp animation; unused auth layout variants deleted
- [x] **DS-05**: Sidebar shows CDRRMO icon + "IRMS" text (replacing "Laravel Starter Kit"); navigation section labels use Space Mono uppercase 9px with 2px letter-spacing
- [x] **DS-06**: Content area behind sidebar pages uses t-bg background (#f4f6f9 light / #0f172a dark) for visual depth against t-surface sidebar
- [x] **DS-07**: Settings pages and Dashboard use design system tokens for typography, elevation, and color (no hardcoded neutral-*/zinc-* classes)
- [x] **DS-08**: Admin data tables follow design system table pattern: t-surface background, Level 1 shadow, 7px border-radius, Space Mono column headers, t-border borders, role/priority badges using design system color tokens with color-mix()
- [x] **DS-09**: Incidents pages (Index, Create, Queue, Show) use design system tokens for tables, cards, priority/status badges, and typography
- [x] **DS-10**: Analytics pages (Dashboard, Heatmap, Reports) use design system card pattern, Space Mono KPI labels, and design system elevation
- [x] **DS-11**: Dispatch Console uses design system color/font tokens in panel chrome while preserving existing layout structure, map styling, and custom UX
- [x] **DS-12**: Responder Station uses design system color/font tokens while preserving existing mobile layout, touch targets, and purpose-built interfaces

### Units CRUD

- [x] **UNIT-01**: Admin can list all units in a data table with ID, callsign, type badge, status badge (colors matching dispatch map markers), crew count (assigned/capacity format), and agency
- [x] **UNIT-02**: Admin can create a unit by selecting a type; system auto-generates ID from type prefix + next sequence number (AMB-01, FIRE-02) and default callsign
- [x] **UNIT-03**: Admin can edit unit callsign, agency, crew capacity, status (Available/Offline only), shift, notes, and crew assignment
- [x] **UNIT-04**: Decommission action sets decommissioned_at timestamp, unassigns all crew members (sets unit_id to null), and displays unit with muted styling and "Decommissioned" badge
- [x] **UNIT-05**: Recommission action clears decommissioned_at and restores unit to Available status
- [x] **UNIT-06**: Crew assignment via inline multi-select syncs User.unit_id bidirectionally; soft warning badge when crew count exceeds crew_capacity (save not blocked)
- [x] **UNIT-07**: Non-admin users blocked from admin unit routes with 403 response
- [x] **UNIT-08**: Unit ID uniqueness enforced via type prefix + sequential numbering from max existing units of that type
- [x] **UNIT-09**: Admin status selection restricted to Available and Offline; workflow statuses (Dispatched, En Route, On Scene) controlled only by dispatch/responder workflow

### Bi-directional Communication

- [x] **COMM-01**: MessageSent event broadcasts on incident-level channel (`incident.{id}.messages`) and dispatch channel (`dispatch.incidents`) instead of user-level channel
- [x] **COMM-02**: Incident message channel authorization permits dispatch roles (operator, dispatcher, supervisor, admin) and responders whose unit is assigned to the incident
- [x] **COMM-03**: Dispatch sendMessage endpoint at POST `dispatch/{incident}/message` creates message and dispatches MessageSent event
- [x] **COMM-04**: Responder sendMessage dispatches updated MessageSent event with sender role and unit callsign in payload
- [x] **COMM-05**: Unauthorized users (unassigned responders, unauthenticated) cannot subscribe to incident message channels
- [x] **COMM-06**: Dispatch console shows collapsible Messages section in incident detail panel (above Timeline, collapsed by default, ~200px max height)
- [x] **COMM-07**: Messages section auto-expands when selecting incident with unread messages; clears unread count on expand
- [x] **COMM-08**: 7 dispatcher quick-reply chips ("Copy", "Stand by", "Proceed", "Return to station", "Backup en route", "Update status", "Acknowledged") plus free text input
- [x] **COMM-09**: Queue card shows unread message dot/count badge; topbar shows global MSGS count stat
- [x] **COMM-10**: Subtle audio cue (distinct from priority tones) plays for incoming messages on non-selected incidents; own messages do not trigger audio or increment unread
- [x] **COMM-11**: Incoming messages appear in dispatch Messages section in real-time via WebSocket with sender name + unit callsign identification
- [x] **COMM-12**: Responder ChatTab subscribes to `incident.{id}.messages` for true group chat (all participants see all messages)
- [x] **COMM-13**: Responder ChatTab displays unit callsign + name for sender identification in multi-unit incidents (e.g., "FIRE-01 . J. Cruz")

### PWA & Push Notifications

- [x] **MOBILE-01**: PWA Service Worker with app shell caching (JS, CSS, HTML, fonts, icons) via vite-plugin-pwa injectManifest strategy; web app manifest with CDRRMO branding; installable from browser; "New version available" update prompt
- [x] **MOBILE-02**: Web Push notifications via VAPID for background alerts: new assignment pushed to responder, P1 incident alert to dispatchers/operators, ack timeout warning to responder; push subscription management endpoints with custom in-app permission prompt

### Sentinel Rebrand

- [x] **REBRAND-01**: CSS token migration — Sentinel navy/blue palette in `--t-*` tokens (`--t-bg: #eff3fa` light, `#05101e` dark), both `:root` and `.dark` blocks; Shadcn components inherit via cascade (`--background: var(--t-bg)`)
- [x] **REBRAND-02**: Font migration — DM Mono replaces Space Mono as `--font-mono`; Bebas Neue added as `--font-display` for auth page title; Google Fonts URL loads all three (DM Sans + DM Mono + Bebas Neue)
- [x] **REBRAND-03**: SVG shield identity — full animated Sentinel shield on auth page (radar rings, crosshairs, eye, pulseRing + sweep animations); simplified 26x30 shield in sidebar (`AppLogo.vue`) and favicon (`public/favicon.svg`)
- [x] **REBRAND-04**: User-facing string rename — all "IRMS" / "CDRRMO Butuan City" application-code references replaced with "Sentinel"; `APP_NAME="Sentinel"` in `.env`; documented non-user-facing residuals (FHIR URNs, BFP API identifiers, CDRRMO agency name in UnitForm, `irms.test` Herd hostname, PHPDoc comments) retained as INFO-severity
- [x] **REBRAND-05**: PWA and push rebrand — `vite.config.ts` manifest `name: 'Sentinel - Incident Response Management System'`, `theme_color: '#042C53'`; 4 PNG icons regenerated on `#042C53` background (192, 512, maskable-512, apple-touch-180); `sw.ts` push notifications default to `'Sentinel'` title and tag
- [x] **REBRAND-06**: Hardcoded priority/status hex color sweep — MapLibre `useDispatchMap.ts` and `useAnalyticsMap.ts`, Chart.js `Dashboard.vue`, responder and intake components (`PriBadge`, `ChBadge`) all use Sentinel palette values (P1 `#E24B4A`, P2 `#EF9F27`, P3 `#1D9E75`, P4 `#378ADD`); zero instances of old palette (`#dc2626`, `#ea580c`, `#ca8a04`, `#16a34a`, `#2563eb`) in `resources/js/`

## v2 Requirements

### Mobile Enhancement

- ~~**MOBILE-01**: PWA Service Worker with offline caching for responder app~~ (Promoted to v1 as Phase 13 MOBILE-01)
- ~~**MOBILE-02**: Web Push notifications (VAPID) for background assignment alerts~~ (Promoted to v1 as Phase 13 MOBILE-02)
- **MOBILE-03**: Capacitor.js APK wrapper for Android distribution

### Advanced Features

- **ADV-01**: AI/ML triage classifier trained on accumulated incident data
- **ADV-02**: Multi-tenancy (stancl/tenancy) for province-wide LGU deployment
- **ADV-03**: Pinia state management for complex client-side state
- ~~**ADV-04**: Public-facing incident reporting portal~~ (Promoted to v1 as Phase 9 CITIZEN-01 through CITIZEN-10)
- **ADV-05**: Photo/video upload from responder field
- **ADV-06**: PAGASA weather overlay on dispatch map (live, not stubbed)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Full ePCR/NEMSIS compliance | US Medicare standard; 270 data elements irrelevant for Philippine CDRRMO. Simplified vitals (BP, HR, SpO2, GCS) + tags is sufficient |
| Automated dispatch (auto-assign) | Removing human judgment dangerous in Philippine context (informal settlements, flooding, road conditions). Recommend-then-confirm is correct |
| Custom map tile server | Infrastructure complexity with marginal benefit. Mapbox hosted basemap is sufficient |
| Video/image upload | Storage, bandwidth, moderation costs. Text-based scene documentation sufficient for v1 |
| Real-time chat (non-incident) | High complexity, not core to incident response value |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FNDTN-01 | Phase 1 | Complete |
| FNDTN-02 | Phase 1 | Complete |
| FNDTN-03 | Phase 1 | Complete |
| FNDTN-04 | Phase 1 | Complete |
| FNDTN-05 | Phase 1 | Complete |
| FNDTN-06 | Phase 1 | Complete |
| FNDTN-07 | Phase 1 | Complete |
| FNDTN-08 | Phase 1 | Complete |
| FNDTN-09 | Phase 3 | Complete |
| FNDTN-10 | Phase 3 | Complete |
| INTK-01 | Phase 2 | Complete |
| INTK-02 | Phase 2 | Complete |
| INTK-03 | Phase 2 | Complete |
| INTK-04 | Phase 2 | Complete |
| INTK-05 | Phase 2 | Complete |
| INTK-06 | Phase 2 | Complete |
| INTK-07 | Phase 2 | Complete |
| INTK-08 | Phase 2 | Complete |
| INTK-09 | Phase 2 | Complete |
| DSPTCH-01 | Phase 4 | Complete |
| DSPTCH-02 | Phase 4 | Complete |
| DSPTCH-03 | Phase 4 | Complete |
| DSPTCH-04 | Phase 4 | Complete |
| DSPTCH-05 | Phase 4 | Complete |
| DSPTCH-06 | Phase 4 | Complete |
| DSPTCH-07 | Phase 4 | Complete |
| DSPTCH-08 | Phase 4 | Complete |
| DSPTCH-09 | Phase 4 | Complete |
| DSPTCH-10 | Phase 4 | Complete |
| DSPTCH-11 | Phase 4 | Complete |
| RSPDR-01 | Phase 5 | Complete |
| RSPDR-02 | Phase 5 | Complete |
| RSPDR-03 | Phase 5 | Complete |
| RSPDR-04 | Phase 5 | Complete |
| RSPDR-05 | Phase 5 | Complete |
| RSPDR-06 | Phase 5 → Phase 15 (gap closure) | Pending |
| RSPDR-07 | Phase 5 | Complete |
| RSPDR-08 | Phase 5 | Complete |
| RSPDR-09 | Phase 5 | Complete |
| RSPDR-10 | Phase 5 → Phase 15 (gap closure) | Pending |
| RSPDR-11 | Phase 5 | Complete |
| INTGR-01 | Phase 6 | Complete |
| INTGR-02 | Phase 6 | Complete |
| INTGR-03 | Phase 6 | Complete |
| INTGR-04 | Phase 6 | Complete |
| INTGR-05 | Phase 6 | Complete |
| INTGR-06 | Phase 6 | Complete |
| INTGR-07 | Phase 6 | Complete |
| INTGR-08 | Phase 6 | Complete |
| INTGR-09 | Phase 6 | Complete |
| ANLTCS-01 | Phase 7 | Complete |
| ANLTCS-02 | Phase 7 | Complete |
| ANLTCS-03 | Phase 7 | Complete |
| ANLTCS-04 | Phase 7 | Complete |
| ANLTCS-05 | Phase 7 | Complete |
| ANLTCS-06 | Phase 7 | Complete |
| CITIZEN-01 | Phase 9 | Complete |
| CITIZEN-02 | Phase 9 | Complete |
| CITIZEN-03 | Phase 9 | Complete |
| CITIZEN-04 | Phase 9 | Complete |
| CITIZEN-05 | Phase 9 | Complete |
| CITIZEN-06 | Phase 9 | Complete |
| CITIZEN-07 | Phase 9 | Complete |
| CITIZEN-08 | Phase 9 | Complete |
| CITIZEN-09 | Phase 9 | Complete |
| CITIZEN-10 | Phase 9 | Complete |
| DS-01 | Phase 10 | Complete |
| DS-02 | Phase 10 | Complete |
| DS-03 | Phase 10 | Complete |
| DS-04 | Phase 10 | Complete |
| DS-05 | Phase 10 | Complete |
| DS-06 | Phase 10 | Complete |
| DS-07 | Phase 10 | Complete |
| DS-08 | Phase 10 | Complete |
| DS-09 | Phase 10 | Complete |
| DS-10 | Phase 10 | Complete |
| DS-11 | Phase 10 | Complete |
| DS-12 | Phase 10 | Complete |
| UNIT-01 | Phase 11 | Complete |
| UNIT-02 | Phase 11 | Complete |
| UNIT-03 | Phase 11 | Complete |
| UNIT-04 | Phase 11 | Complete |
| UNIT-05 | Phase 11 | Complete |
| UNIT-06 | Phase 11 | Complete |
| UNIT-07 | Phase 11 | Complete |
| UNIT-08 | Phase 11 | Complete |
| UNIT-09 | Phase 11 | Complete |
| COMM-01 | Phase 12 | Complete |
| COMM-02 | Phase 12 | Complete |
| COMM-03 | Phase 12 | Complete |
| COMM-04 | Phase 12 | Complete |
| COMM-05 | Phase 12 | Complete |
| COMM-06 | Phase 12 | Complete |
| COMM-07 | Phase 12 | Complete |
| COMM-08 | Phase 12 | Complete |
| COMM-09 | Phase 12 | Complete |
| COMM-10 | Phase 12 | Complete |
| COMM-11 | Phase 12 | Complete |
| COMM-12 | Phase 12 | Complete |
| COMM-13 | Phase 12 | Complete |
| MOBILE-01 | Phase 13 | Complete |
| MOBILE-02 | Phase 13 | Complete |
| OP-01 | Phase 8 | Complete |
| OP-02 | Phase 8 | Complete |
| OP-03 | Phase 8 | Complete |
| OP-04 | Phase 8 | Complete |
| OP-05 | Phase 8 | Complete |
| OP-06 | Phase 8 | Complete |
| OP-07 | Phase 8 | Complete |
| OP-08 | Phase 8 | Complete |
| OP-09 | Phase 8 | Complete |
| OP-10 | Phase 8 | Complete |
| OP-11 | Phase 8 | Complete |
| OP-12 | Phase 8 | Complete |
| OP-13 | Phase 8 | Complete |
| OP-14 | Phase 8 | Complete |
| OP-15 | Phase 8 | Complete |
| REBRAND-01 | Phase 14 | Complete |
| REBRAND-02 | Phase 14 | Complete |
| REBRAND-03 | Phase 14 | Complete |
| REBRAND-04 | Phase 14 | Complete |
| REBRAND-05 | Phase 14 | Complete |
| REBRAND-06 | Phase 14 | Complete |

**Coverage:**
- v1 requirements: 123 total (102 + 15 OP + 6 REBRAND backfilled in Phase 16)
- Mapped to phases: 123 (RSPDR-06 and RSPDR-10 reassigned to Phase 15 gap closure)
- Unmapped: 0

---
*Requirements defined: 2026-03-12*
*Last updated: 2026-04-17 after Phase 16 traceability backfill — OP-01..15 and REBRAND-01..06 added to registry and coverage table (Phase 15 gap closure for RSPDR-06 and RSPDR-10 tracked separately in Phase 15 artifacts)*
