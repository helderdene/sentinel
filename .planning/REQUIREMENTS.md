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
- [ ] **FNDTN-09**: Laravel Reverb WebSocket server configured with channel authorization and presence channels
- [ ] **FNDTN-10**: Redis configured for cache, queue (Horizon), and Reverb pub/sub

### Intake

- [ ] **INTK-01**: Dispatcher can create incident with type (40+ types across 8 categories), priority (P1-P4), location, caller info, channel, and notes
- [ ] **INTK-02**: System auto-generates unique incident number (INC-YYYY-NNNNN) on creation
- [ ] **INTK-03**: System auto-suggests priority (P1-P4) based on incident type keywords with confidence score; dispatcher can override
- [ ] **INTK-04**: Location text is geocoded via Mapbox API with Philippines filter; coordinates auto-populated
- [ ] **INTK-05**: PostGIS ST_Contains query auto-assigns barangay from geocoded coordinates; dispatcher can manually correct
- [ ] **INTK-06**: Dispatch queue displays all triaged incidents ordered by priority (P1 first) then FIFO within same priority
- [ ] **INTK-07**: IoT sensor webhook endpoint accepts alerts with HMAC-SHA256 validation; auto-creates incidents from threshold exceedances (stubbed sensor integration)
- [ ] **INTK-08**: SMS inbound webhook parses incoming messages with keyword classifier for incident type suggestion; auto-reply on creation (stubbed Semaphore integration)
- [ ] **INTK-09**: Channel monitor panel shows live feed from all 5 channels with unacknowledged message highlighting and pending count badges

### Dispatch

- [ ] **DSPTCH-01**: 2D dispatch map rendered with MapLibre GL JS, zoom 13, centered on Butuan City, with custom dark/light vector tile styles
- [ ] **DSPTCH-02**: Incident markers rendered as WebGL circle layers (halo, pulse rings, border, pin, dot) colored by priority (P1 red, P2 orange, P3 amber, P4 green)
- [ ] **DSPTCH-03**: Unit markers rendered as WebGL circle layers (glow, border, body, ring, dot) colored by status (available green, en route blue, on scene yellow, offline gray)
- [ ] **DSPTCH-04**: Unit GPS positions update in real-time via Reverb WebSocket (every 10s en route, 60s on scene); markers animate smoothly between positions
- [ ] **DSPTCH-05**: Dispatcher can select incident from queue or map and assign one or more available units
- [ ] **DSPTCH-06**: System ranks available units by proximity using PostGIS ST_DWithin; displays distance and ETA (Mapbox Directions API, stubbed)
- [ ] **DSPTCH-07**: Assignment pushed to responder via WebSocket with full incident payload
- [ ] **DSPTCH-08**: 90-second acknowledgement timer starts on assignment; visual countdown and audio alert on expiry with reassign/escalate suggestion
- [ ] **DSPTCH-09**: Audio alerts via Web Audio API with distinct tones per priority level; P1 triggers red screen flash
- [ ] **DSPTCH-10**: Session metrics displayed in console header: total incidents, triaged/pending, active incidents, units available/deployed, average handle time
- [ ] **DSPTCH-11**: Mutual aid modal with suggested agencies (BFP, PNP, DSWD, adjacent LGU, DOH) based on incident type; contact info, radio channel, and request logged to timeline

### Responder

- [ ] **RSPDR-01**: Responder receives assignment via WebSocket with toast notification and audio cue; full incident card with type, priority, location, notes
- [ ] **RSPDR-02**: Responder can acknowledge assignment with single tap; timestamp captured and dispatch timer closed
- [ ] **RSPDR-03**: Responder can transition status (Acknowledged > En Route > On Scene > Resolving > Resolved) with large touch targets (44px min); each transition broadcasts to dispatch via WebSocket
- [ ] **RSPDR-04**: Navigation deep-link to Google Maps with incident coordinates; embedded MapLibre mini-map with animated route polyline, unit position, incident pulse ring, and live ETA countdown
- [ ] **RSPDR-05**: Bi-directional messaging with dispatch; 8 preset quick-reply chips plus free text; message history persists for incident duration
- [ ] **RSPDR-06**: Contextual arrival checklists per incident type (cardiac, road accident, structure fire, default) with animated checkboxes and progress bar; completion % broadcast to dispatch
- [ ] **RSPDR-07**: Patient vitals form: blood pressure (mmHg), heart rate (bpm), SpO2 (%), GCS score (3-15) with validation ranges and placeholders
- [ ] **RSPDR-08**: Quick assessment tags as toggle chips: Conscious, Breathing, Bleeding, Unresponsive, Fracture, Burns, Shock, Chest Pain, Head Trauma, Airway Compromised, Anaphylaxis
- [ ] **RSPDR-09**: Outcome selection required before closure: Treated On Scene, Transported to Hospital (with hospital picker), Patient Refused Treatment, Declared DOA, Stand Down/False Alarm
- [ ] **RSPDR-10**: Resource request from field: 6 types (additional ambulance, fire unit, police backup, rescue boat, medical officer, medevac); request creates timeline entry and dispatch notification
- [ ] **RSPDR-11**: Auto-generated incident report PDF on closure: ID, type, priority, scene time, checklist %, vitals, tags, outcome, hospital, notes

### Integration

- [ ] **INTGR-01**: All external integrations behind PHP interfaces bound in service container; stub implementations log calls; real implementations plug in without business logic changes
- [ ] **INTGR-02**: Stubbed Mapbox Geocoding connector for forward geocoding with Philippines country filter
- [ ] **INTGR-03**: Stubbed Mapbox Directions connector for road-network ETA calculation
- [ ] **INTGR-04**: Stubbed Semaphore SMS connector for inbound parsing and outbound acknowledgement/status messages
- [ ] **INTGR-05**: Stubbed PAGASA Weather connector for rainfall, wind, and flood advisory overlay data
- [ ] **INTGR-06**: Stubbed Hospital EHR connector (HL7 FHIR R4) for patient pre-notification on transport outcome
- [ ] **INTGR-07**: Stubbed NDRRMC connector for SitRep XML submission on P1 closure
- [ ] **INTGR-08**: Stubbed BFP connector for bidirectional fire incident sync
- [ ] **INTGR-09**: Stubbed PNP e-Blotter connector for criminal incident auto-blotter entry

### Analytics

- [ ] **ANLTCS-01**: KPI dashboard with 5 metrics: average response time, average scene arrival time, resolution rate, unit utilization, false alarm rate — filterable by date range, type, priority, barangay
- [ ] **ANLTCS-02**: Incident heatmap as choropleth map colored by incident density per barangay; filters for type, priority, date range (30/90/365 days); exportable as PNG
- [ ] **ANLTCS-03**: DILG monthly incident report auto-generated on 1st of each month as PDF + CSV; incidents aggregated by type, priority, barangay, outcome
- [ ] **ANLTCS-04**: NDRRMC Situation Report auto-generated on P1 incident closure; IRMS record mapped to NDRRMC XML template (stubbed submission); fallback PDF emailed to OCD Caraga
- [ ] **ANLTCS-05**: Quarterly performance report with KPI trends, incident volume charts, response time analysis as PDF
- [ ] **ANLTCS-06**: Annual statistical summary for Mayor's Office with year-over-year comparison as PDF

## v2 Requirements

### Mobile Enhancement

- **MOBILE-01**: PWA Service Worker with offline caching for responder app
- **MOBILE-02**: Web Push notifications (VAPID) for background assignment alerts
- **MOBILE-03**: Capacitor.js APK wrapper for Android distribution

### Advanced Features

- **ADV-01**: AI/ML triage classifier trained on accumulated incident data
- **ADV-02**: Multi-tenancy (stancl/tenancy) for province-wide LGU deployment
- **ADV-03**: Pinia state management for complex client-side state
- **ADV-04**: Public-facing incident reporting portal
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
| FNDTN-09 | Phase 3 | Pending |
| FNDTN-10 | Phase 3 | Pending |
| INTK-01 | Phase 2 | Pending |
| INTK-02 | Phase 2 | Pending |
| INTK-03 | Phase 2 | Pending |
| INTK-04 | Phase 2 | Pending |
| INTK-05 | Phase 2 | Pending |
| INTK-06 | Phase 2 | Pending |
| INTK-07 | Phase 2 | Pending |
| INTK-08 | Phase 2 | Pending |
| INTK-09 | Phase 2 | Pending |
| DSPTCH-01 | Phase 4 | Pending |
| DSPTCH-02 | Phase 4 | Pending |
| DSPTCH-03 | Phase 4 | Pending |
| DSPTCH-04 | Phase 4 | Pending |
| DSPTCH-05 | Phase 4 | Pending |
| DSPTCH-06 | Phase 4 | Pending |
| DSPTCH-07 | Phase 4 | Pending |
| DSPTCH-08 | Phase 4 | Pending |
| DSPTCH-09 | Phase 4 | Pending |
| DSPTCH-10 | Phase 4 | Pending |
| DSPTCH-11 | Phase 4 | Pending |
| RSPDR-01 | Phase 5 | Pending |
| RSPDR-02 | Phase 5 | Pending |
| RSPDR-03 | Phase 5 | Pending |
| RSPDR-04 | Phase 5 | Pending |
| RSPDR-05 | Phase 5 | Pending |
| RSPDR-06 | Phase 5 | Pending |
| RSPDR-07 | Phase 5 | Pending |
| RSPDR-08 | Phase 5 | Pending |
| RSPDR-09 | Phase 5 | Pending |
| RSPDR-10 | Phase 5 | Pending |
| RSPDR-11 | Phase 5 | Pending |
| INTGR-01 | Phase 6 | Pending |
| INTGR-02 | Phase 6 | Pending |
| INTGR-03 | Phase 6 | Pending |
| INTGR-04 | Phase 6 | Pending |
| INTGR-05 | Phase 6 | Pending |
| INTGR-06 | Phase 6 | Pending |
| INTGR-07 | Phase 6 | Pending |
| INTGR-08 | Phase 6 | Pending |
| INTGR-09 | Phase 6 | Pending |
| ANLTCS-01 | Phase 7 | Pending |
| ANLTCS-02 | Phase 7 | Pending |
| ANLTCS-03 | Phase 7 | Pending |
| ANLTCS-04 | Phase 7 | Pending |
| ANLTCS-05 | Phase 7 | Pending |
| ANLTCS-06 | Phase 7 | Pending |

**Coverage:**
- v1 requirements: 56 total
- Mapped to phases: 56
- Unmapped: 0

---
*Requirements defined: 2026-03-12*
*Last updated: 2026-03-12 after roadmap phase assignment*
