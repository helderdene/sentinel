# Roadmap: IRMS

## Overview

IRMS transforms CDRRMO Butuan City's paper-based emergency workflow into a real-time digital system. The build follows a strict dependency chain: data foundation and RBAC first, then incident intake, then WebSocket infrastructure, then the dispatch map console, then the responder mobile workflow, then external integration stubs, and finally analytics. Each phase delivers a coherent, testable capability that unblocks the next. All external APIs are stubbed throughout; the entire core dispatch-to-response workflow runs without any third-party API keys.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Foundation** - PostgreSQL + PostGIS database, core data models, RBAC with 4 roles, barangay boundaries
- [x] **Phase 2: Intake** - Multi-channel incident triage, geocoding, priority classification, dispatch queue
- [x] **Phase 3: Real-Time Infrastructure** - Laravel Reverb WebSocket server, broadcast events, channel auth, reconnection strategy (completed 2026-03-13)
- [ ] **Phase 4: Dispatch Console** - 2D MapLibre map with WebGL markers, unit assignment, proximity ranking, audio alerts, session metrics
- [ ] **Phase 5: Responder Workflow** - Mobile-optimized assignment receipt, status transitions, GPS tracking, scene documentation, messaging
- [ ] **Phase 6: Integration Layer** - Stubbed external connectors (SMS, geocoding, directions, weather, hospital, government agencies)
- [ ] **Phase 7: Analytics** - KPI dashboard, incident heatmap, DILG/NDRRMC/quarterly/annual compliance reports
- [ ] **Phase 8: Operator Role & Intake Station** - 5th role (operator), TRIAGED status, full-screen intake station UI with design system

## Phase Details

### Phase 1: Foundation
**Goal**: The database, data models, role system, and reference data exist so all subsequent layers can store and query incidents, units, and spatial data correctly
**Depends on**: Nothing (first phase)
**Requirements**: FNDTN-01, FNDTN-02, FNDTN-03, FNDTN-04, FNDTN-05, FNDTN-06, FNDTN-07, FNDTN-08
**Success Criteria** (what must be TRUE):
  1. PostgreSQL with PostGIS extension is the application database; spatial queries (ST_Contains, ST_DWithin) execute successfully against barangay boundary polygons
  2. An admin user can assign roles (dispatcher, responder, supervisor, admin) to other users, and each role sees only the navigation and pages permitted by its role
  3. A responder user is associated with a specific unit (e.g., AMB-01); the unit record stores GPS coordinates as geography type with a GiST spatial index
  4. Creating an incident populates all lifecycle fields (timestamps, vitals JSONB, assessment_tags, coordinates geography) and appends an entry to the incident timeline audit log
  5. The 86 Butuan City barangay boundary polygons are seeded and a point-in-polygon query correctly identifies which barangay contains a given coordinate
**Plans**: 3 plans

Plans:
- [x] 01-01-PLAN.md -- Database foundation: PostgreSQL/PostGIS, migrations, models, enums, factories, seeders, RBAC middleware/gates
- [x] 01-02-PLAN.md -- Admin panel: User/role CRUD, incident type management, barangay metadata editing
- [x] 01-03-PLAN.md -- Role-based navigation: sidebar per role, Inertia shared props, placeholder pages

### Phase 2: Intake
**Goal**: Dispatchers can receive incident reports from multiple channels, triage them with auto-suggested priority, geocode locations to barangay boundaries, and view a priority-ordered dispatch queue
**Depends on**: Phase 1
**Requirements**: INTK-01, INTK-02, INTK-03, INTK-04, INTK-05, INTK-06, INTK-07, INTK-08, INTK-09
**Success Criteria** (what must be TRUE):
  1. A dispatcher can fill out the incident triage form with type (40+ types), caller info, location text, channel, and notes; submitting creates an incident with auto-generated INC-YYYY-NNNNN number
  2. The system suggests a priority level (P1-P4) based on incident type keywords with a confidence score; the dispatcher can accept or override it
  3. Typing a location address triggers geocoding (stubbed Mapbox) and auto-populates coordinates; PostGIS ST_Contains assigns the correct barangay from those coordinates
  4. The dispatch queue displays all triaged incidents ordered by priority (P1 first) then FIFO; new incidents appear without page refresh
  5. IoT sensor webhook and SMS inbound webhook endpoints accept payloads and auto-create incidents (both using stubbed integrations with HMAC validation for IoT)
**Plans**: 3 plans

Plans:
- [x] 02-01-PLAN.md -- Backend services: IncidentChannel enum, service contracts (Geocoding, SMS), PrioritySuggestionService, BarangayLookupService, IncidentController with CRUD + triage form validation, routes, and tests
- [x] 02-02-PLAN.md -- Frontend UI: Combobox component, triage form (Create.vue), priority selector, dispatch queue (Queue.vue), incidents list (Index.vue), incident detail (Show.vue), channel monitor widget, sidebar navigation
- [x] 02-03-PLAN.md -- Webhook endpoints: IoT sensor webhook with HMAC-SHA256 validation, SMS inbound webhook with keyword classifier, channel monitor count tests

### Phase 3: Real-Time Infrastructure
**Goal**: WebSocket infrastructure is operational so all subsequent layers can push and receive real-time updates without polling
**Depends on**: Phase 1
**Requirements**: FNDTN-09, FNDTN-10
**Success Criteria** (what must be TRUE):
  1. Laravel Reverb WebSocket server accepts connections with proper channel authorization; private and presence channels enforce role-based access
  2. Broadcast events (IncidentCreated, IncidentStatusChanged, UnitLocationUpdated, UnitStatusChanged, AssignmentPushed, MessageSent) are received by subscribed clients within 500ms
  3. When a client loses connection and reconnects, it calls a state-sync endpoint and receives the current state of all active incidents and units, with a visible "Reconnecting..." indicator during the gap
  4. Redis is configured and operational for cache, queue (Horizon), and Reverb pub/sub
**Plans**: 2 plans

Plans:
- [x] 03-01-PLAN.md -- Backend infrastructure: Reverb + Horizon + Redis config, 6 broadcast events, channel auth, state-sync endpoint, dev script, tests
- [x] 03-02-PLAN.md -- Frontend integration: Echo init, useWebSocket composable, ConnectionBanner, Queue.vue + ChannelMonitor.vue WebSocket replacement, P1/P2 audio alerts

### Phase 4: Dispatch Console
**Goal**: Dispatchers can see all incidents and units on a live 2D map, assign the nearest available unit to an incident, and track response progress in real-time with audio/visual alerts
**Depends on**: Phase 2, Phase 3
**Requirements**: DSPTCH-01, DSPTCH-02, DSPTCH-03, DSPTCH-04, DSPTCH-05, DSPTCH-06, DSPTCH-07, DSPTCH-08, DSPTCH-09, DSPTCH-10, DSPTCH-11
**Success Criteria** (what must be TRUE):
  1. A full-screen 2D MapLibre map renders centered on Butuan City with incident markers (colored by priority: red/orange/amber/green) and unit markers (colored by status: green/blue/yellow/gray) as WebGL circle layers
  2. Unit positions update on the map in real-time via WebSocket without page refresh; markers animate smoothly between GPS positions
  3. A dispatcher can select an incident, see available units ranked by proximity (PostGIS ST_DWithin) with distance and ETA, and assign one or more units; the assignment is pushed to the responder via WebSocket
  4. A 90-second acknowledgement timer counts down after assignment; audio alerts with distinct tones per priority level play; P1 incidents trigger a red screen flash
  5. Session metrics (total incidents, triaged/pending, active incidents, units available/deployed, average handle time) are visible in the console header and update in real-time
**Plans**: TBD

Plans:
- [ ] 04-01: TBD
- [ ] 04-02: TBD
- [ ] 04-03: TBD
- [ ] 04-04: TBD

### Phase 5: Responder Workflow
**Goal**: Field responders can receive assignments on mobile, navigate to scenes, document what they find, communicate with dispatch, and close incidents with structured outcome data
**Depends on**: Phase 4
**Requirements**: RSPDR-01, RSPDR-02, RSPDR-03, RSPDR-04, RSPDR-05, RSPDR-06, RSPDR-07, RSPDR-08, RSPDR-09, RSPDR-10, RSPDR-11
**Success Criteria** (what must be TRUE):
  1. A responder receives an assignment notification via WebSocket with audio cue and can acknowledge it with a single tap; the dispatch timer closes and the acknowledgement timestamp is captured
  2. The responder can transition through the full status workflow (Acknowledged, En Route, On Scene, Resolving, Resolved) with large touch targets (44px min); each transition broadcasts to dispatch in real-time
  3. The responder can view a navigation tab with Google Maps deep-link and embedded MapLibre mini-map showing route, unit position, incident location, and live ETA countdown
  4. On scene, the responder can complete arrival checklists (per incident type), capture patient vitals (BP, HR, SpO2, GCS), toggle assessment tags, send messages to dispatch (quick-reply chips + free text), and request additional resources
  5. The responder must select an outcome (Treated On Scene, Transported to Hospital, Refused Treatment, DOA, False Alarm) before closure; closure auto-generates an incident report PDF with all captured data
**Plans**: TBD

Plans:
- [ ] 05-01: TBD
- [ ] 05-02: TBD
- [ ] 05-03: TBD
- [ ] 05-04: TBD

### Phase 6: Integration Layer
**Goal**: All external API integrations are architecturally wired behind PHP interfaces with working stub implementations, ready to swap for real connectors when API agreements are in place
**Depends on**: Phase 2, Phase 4, Phase 5
**Requirements**: INTGR-01, INTGR-02, INTGR-03, INTGR-04, INTGR-05, INTGR-06, INTGR-07, INTGR-08, INTGR-09
**Success Criteria** (what must be TRUE):
  1. Every external integration (Mapbox, Semaphore, PAGASA, Hospital EHR, NDRRMC, BFP, PNP) has a PHP interface bound in the service container; swapping stub for real implementation requires zero business logic changes
  2. The stubbed Mapbox geocoding connector resolves location text to coordinates (returning test data); the stubbed Mapbox directions connector returns ETA for a coordinate pair
  3. The stubbed Semaphore SMS connector logs outbound messages and parses inbound webhooks with keyword classification for incident type suggestion
  4. The stubbed government connectors (NDRRMC SitRep XML, BFP fire sync, PNP e-Blotter) and hospital EHR connector (HL7 FHIR R4) log their payloads; the PAGASA weather connector returns sample advisory data
**Plans**: TBD

Plans:
- [ ] 06-01: TBD
- [ ] 06-02: TBD
- [ ] 06-03: TBD

### Phase 7: Analytics
**Goal**: Supervisors and the Mayor's Office can view operational KPIs, identify incident hotspots on a heatmap, and generate compliance reports required by DILG and NDRRMC
**Depends on**: Phase 1, Phase 4, Phase 5
**Requirements**: ANLTCS-01, ANLTCS-02, ANLTCS-03, ANLTCS-04, ANLTCS-05, ANLTCS-06
**Success Criteria** (what must be TRUE):
  1. A KPI dashboard displays five core metrics (average response time, average scene arrival time, resolution rate, unit utilization, false alarm rate) filterable by date range, incident type, priority, and barangay
  2. An incident heatmap renders a choropleth map colored by incident density per barangay with filters for type, priority, and date range (30/90/365 days); the map is exportable as PNG
  3. On the 1st of each month, the system auto-generates a DILG monthly incident report (PDF + CSV) aggregating incidents by type, priority, barangay, and outcome
  4. On P1 incident closure, an NDRRMC Situation Report is auto-generated (stubbed XML submission with PDF email fallback); quarterly and annual performance reports with trend analysis are available on demand as PDF
**Plans**: TBD

Plans:
- [ ] 07-01: TBD
- [ ] 07-02: TBD
- [ ] 07-03: TBD

### Phase 8: Implement operator role and intake layer UI
**Goal**: Operators can log in and land directly on a full-screen intake station where they triage incoming incidents from a live feed, classify them with priority and location, and push them to the dispatch queue -- all in real-time with WebSocket updates
**Depends on**: Phase 2, Phase 3
**Requirements**: OP-01, OP-02, OP-03, OP-04, OP-05, OP-06, OP-07, OP-08, OP-09, OP-10, OP-11, OP-12, OP-13, OP-14, OP-15
**Success Criteria** (what must be TRUE):
  1. Operator role exists as 5th role with intake-specific gates; operator login redirects to /intake (not dashboard)
  2. TRIAGED status exists between PENDING and DISPATCHED; triage form transitions incidents from PENDING to TRIAGED
  3. Full-screen intake station renders three columns: channel feed (left, 296px), triage form (center, flex), dispatch queue (right, 304px)
  4. Left panel shows PENDING incidents arriving via WebSocket with filter tabs (All/Pending/Triaged)
  5. Clicking a feed card pre-fills the center triage form; submitting transitions to TRIAGED and moves to dispatch queue
  6. Supervisor/admin see Override Priority, Recall, and Session Log; operator does not
  7. Design system adopted app-wide: DM Sans + Space Mono fonts, color tokens, dark mode
**Plans**: 4 plans

Plans:
- [ ] 08-01-PLAN.md -- Backend foundation: Operator role, TRIAGED status, 6 intake gates, IntakeStationController, triage endpoint, Fortify redirect, channel auth, tests
- [ ] 08-02-PLAN.md -- Design system and layout shell: Tailwind tokens, fonts, IntakeLayout, IntakeTopbar, IntakeStatusbar, custom SVG icons, badge components
- [ ] 08-03-PLAN.md -- Core intake workflow: IntakeStation page, ChannelFeed (left panel), TriageForm (center panel), composables, priority picker
- [ ] 08-04-PLAN.md -- Dispatch queue and supervisor features: DispatchQueuePanel (right panel), SessionMetrics, PriorityBreakdown, Override/Recall, SessionLog, visual verification

## Progress

**Execution Order:**
Phases execute in numeric order: 1 > 2 > 3 > 4 > 5 > 6 > 7 > 8
(Phase 8 depends on Phases 2 and 3, can run before Phases 4-7)

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation | 3/3 | Complete | 2026-03-12 |
| 2. Intake | 3/3 | Complete | 2026-03-13 |
| 3. Real-Time Infrastructure | 2/2 | Complete | 2026-03-13 |
| 4. Dispatch Console | 0/4 | Not started | - |
| 5. Responder Workflow | 0/4 | Not started | - |
| 6. Integration Layer | 0/3 | Not started | - |
| 7. Analytics | 0/3 | Not started | - |
| 8. Operator Role & Intake Station | 0/4 | Planning complete | - |
