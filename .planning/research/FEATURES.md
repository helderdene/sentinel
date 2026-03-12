# Feature Landscape

**Domain:** Emergency Incident Response Management System (CAD/Dispatch + Field Response + Analytics)
**Context:** CDRRMO Butuan City, Caraga Region XIII, Philippines
**Researched:** 2026-03-12
**Overall confidence:** HIGH (domain well-established; Philippine compliance requirements verified against RA 10121 and NDRRMC frameworks)

---

## Table Stakes

Features dispatchers and responders expect from any system replacing manual radio-log workflows. Missing any of these means the system is unusable or will be rejected by operators.

### Incident Lifecycle Core

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Incident creation form with type/priority/location | Fundamental CAD function -- every incident needs structured intake | Medium | Multi-channel intake (voice, walk-in, SMS) but single unified form. Spec defines 8 categories, 40+ incident types. |
| Auto-generated incident number (INC-YYYY-NNNNN) | Operators need a short, speakable reference for radio/phone coordination | Low | Sequential per year; must be unique and never reused. Critical for radio callouts. |
| Priority classification (P1-P4) with color coding | Industry standard. Dispatchers triage by severity; color provides instant visual scan | Low | Auto-suggest from incident type keywords; dispatcher always has final override. Red/Orange/Amber/Green per spec. |
| Incident status workflow (Pending -> Dispatched -> Acknowledged -> En Route -> On Scene -> Resolving -> Resolved) | The entire dispatch model depends on knowing where each incident stands. Without status tracking, you have no system. | Medium | Each transition must timestamp automatically. Timestamps feed KPIs (response time, scene arrival). |
| Incident timeline / audit log | Every action on an incident must be traceable. Required for after-action review, legal liability, and NDRRMC compliance | Medium | Append-only log: status changes, messages, assignments, notes. Actor + timestamp on every entry. |
| Location capture with map pin | Dispatchers must see WHERE incidents are. Text addresses alone are ambiguous in Philippine barangay geography | High | Geocoding via Mapbox API with Philippines filter. PostGIS ST_Contains for auto-barangay assignment. Fallback to manual pin-drop. |
| Caller/reporter information fields | Voice/walk-in reports need caller name and contact for follow-up. SMS channel captures phone automatically | Low | Optional fields -- IoT and app channels may not have caller info. |
| Incident notes / free-text details | Dispatchers capture details that structured fields cannot express | Low | Free text with timestamps. Must be editable by dispatcher throughout lifecycle. |

### Dispatch Console

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Real-time map with incident and unit markers | The defining feature of a dispatch console. Without a live map, dispatchers revert to radio + paper | High | MapLibre GL JS with WebGL-rendered markers (not HTML overlays). All incidents and units visible simultaneously. Spec defines layered circle markers with priority-colored halos. |
| Dispatch queue with priority ordering | Dispatchers need a prioritized list of incidents awaiting assignment. P1 at top, FIFO within same priority | Medium | Must update in real-time as new incidents arrive. Queue count visible at all times. |
| Unit assignment workflow (select incident -> pick unit -> assign) | Core dispatch action. Must be fast -- 3 clicks or fewer from queue to assignment | Medium | Filter available units by proximity and type. Show ETA. Support multi-unit assignment per incident. |
| Unit status display (Available/En Route/On Scene/Offline) | Dispatchers must instantly see which units are free. Status determines assignability | Medium | Color-coded markers on map + status panel. Updated in real-time via WebSocket. |
| Real-time unit GPS tracking on map | Dispatchers track responder movement to verify progress and estimate arrival. Without this, dispatchers call responders by radio to ask "where are you?" | High | GPS updates every 10s (en route), 60s (on scene). Broadcast via Laravel Reverb. Unit markers animate smoothly between positions. |
| Assignment acknowledgement timer (90 seconds) | If a responder does not confirm receipt, the dispatcher must know immediately to reassign | Medium | Visual countdown. Audio alert on expiry. Auto-escalation suggestion if no acknowledgement. |
| Audio/visual alerts for new high-priority incidents | P1 incidents must interrupt the dispatcher's attention immediately. Missing a cardiac arrest because it appeared silently in a queue is unacceptable | Medium | Web Audio API. Distinct tones per priority level. Red screen flash for P1. Configurable volume. |
| Session metrics (active incidents, units deployed, pending count) | Dispatcher needs a dashboard header showing operational tempo at a glance | Low | Real-time counters. Session-scoped (shift-based). |

### Responder Mobile Workflow

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Assignment receipt with incident details | Responder must see what they are responding to: type, location, priority, notes | Medium | Push via WebSocket. Toast notification with audio cue for P1. Full incident card with all relevant fields. |
| Assignment acknowledgement action | Dispatcher needs confirmation that the responder received and accepted. Closes the 90s timer loop | Low | Single tap. Must work on mobile. Timestamp captured. |
| Status transition buttons (Acknowledged -> En Route -> On Scene -> Resolving -> Resolved) | Responder drives the incident forward. Each tap updates dispatch map in real-time | Low | Large touch targets (44px minimum per WCAG). Status-aware: only show valid next transitions. |
| Navigation to incident location | Responder needs directions. Deep-link to Google Maps/Waze with incident coordinates is the minimum viable approach | Low | "NAVIGATE" button. Opens native maps app with lat/lng. Embedded mini-map with route polyline is a differentiator (see below). |
| Bi-directional messaging with dispatch | Responders and dispatchers must communicate about the incident without relying on radio alone | Medium | Quick-reply chips for common messages ("Requesting backup", "Patient stable", "ETA 2 minutes"). Free text for complex messages. Real-time via WebSocket. |
| Outcome/closure form | Responder must document how the incident ended: treated on scene, transported, DOA, false alarm | Medium | Mandatory outcome selection before closure. Hospital picker for transport cases. Closure notes field. |

### Roles and Access Control

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Role-based access (dispatcher, responder, supervisor, admin) | Dispatcher console and responder mobile are fundamentally different interfaces. A responder must never see the full dispatch queue; a dispatcher must not close incidents in the field | Medium | Four roles per spec. Extend Fortify (already in place) with role/permission middleware. Policy-based authorization on all model mutations. |
| User-unit association | Responders must be linked to their assigned unit (AMB-01, RESCUE-02, etc.) so assignments route to the correct device | Low | User belongs to unit. Unit has type, agency, crew count. |
| Login with session persistence | Responders on mobile cannot re-authenticate every time the screen locks | Low | Existing Fortify auth. Session duration: 12h responder, 24h dispatcher per spec. |

### Data Foundation

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| PostgreSQL + PostGIS database | Spatial queries (proximity search, barangay boundary lookup, heatmaps) are impossible without PostGIS. This is not optional for a Philippine LGU dispatch system where barangay boundaries matter | High | Migration from SQLite (dev) to PostgreSQL (production). PostGIS extension. Barangay polygon table with GIST index. |
| Barangay reference data with boundary polygons | Butuan City has 86 barangays. Every incident must be assigned to a barangay for reporting compliance (DILG monthly reports, NDRRMC SitRep) | Medium | Import boundary polygons from PhilGIS or PSA. Pre-populate risk levels. Used by geocoding, heatmaps, and compliance reports. |
| Units data model with GPS, status, type, agency | The dispatch system needs to know what units exist, where they are, and whether they are available | Medium | Seed with CDRRMO fleet data. Support ambulance, fire, rescue, police types. Agency field for mutual aid (BFP, PNP units). |
| Incident data model with full lifecycle timestamps | Every KPI depends on precise timestamps: dispatched_at, acknowledged_at, en_route_at, on_scene_at, resolved_at | Medium | JSONB for vitals. TEXT[] for assessment tags. Geography column for coordinates. All per spec schema. |

---

## Differentiators

Features that set IRMS apart from manual radio-log workflows and basic spreadsheet tracking. Not expected by operators on day one, but deliver significant value once table stakes are solid.

### Dispatch Intelligence

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Proximity-based unit recommendation | System automatically ranks available units by distance to incident, saving the dispatcher from mentally calculating "which ambulance is closest?" | High | PostGIS ST_DWithin or ST_Distance query. Show ranked list with distance/ETA. Dispatcher still makes final choice. |
| ETA calculation via road network | Straight-line distance is misleading. Road-network ETA gives dispatchers confidence in the assignment and the responder a realistic arrival estimate | Medium | Mapbox Directions API. Display on both dispatch console and responder navigation tab. Update as unit moves. |
| Embedded responder navigation mini-map | Instead of just deep-linking to Google Maps, show an inline MapLibre map with animated route polyline and live ETA countdown | High | MapLibre GL JS mini-map in responder view. Animated dashed route line. Unit position + incident pulse ring. Scene timer starts on arrival. |
| Mutual aid protocol workflow | When no suitable CDRRMO units are available, the system guides the dispatcher through requesting assistance from BFP, PNP, DSWD, or adjacent LGUs -- with contact info and radio channels | Medium | Modal with suggested agencies based on incident type. Contact directory. Mutual aid request logged to incident timeline. |
| 3D map with pitch and terrain | Butuan City has significant elevation variation (river delta to foothills). A pitched 3D view provides better spatial awareness than flat 2D | Medium | MapLibre GL JS pitch at 45 degrees, zoom 13. Custom dark/light vector tile styles. Already specified in the project spec. |

### Responder Scene Documentation

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Contextual arrival checklists per incident type | Ensures responders follow protocol-specific steps (AED check for cardiac, spinal precautions for accidents). Reduces human error under stress. Checklist completion % visible to dispatch | Medium | 4 checklist templates (cardiac, accident, fire, default). Animated checkboxes with progress bar. Progress broadcast to dispatch. |
| Patient vitals capture (BP, HR, SpO2, GCS) | Structured vitals data enables clinical handoff to hospitals. Stored as JSONB for flexible querying | Medium | Numeric fields with validation ranges. Pre-populated placeholders. Auto-saved. Not a full ePCR (see anti-features) -- simplified for CDRRMO scope. |
| Quick assessment tags (toggle chips) | Faster than free-text for common findings: "Conscious", "Bleeding", "Fracture", "Burns". Enables analytics on injury patterns | Low | 11 predefined tags per spec. Toggle chips with visual feedback. Stored as TEXT[] array. |
| Resource request from field | Responder can request additional ambulance, fire unit, police backup, rescue boat, medical officer, or medevac directly from the app -- eliminating radio relay delays | Medium | 6 resource types per spec. Request creates timeline entry and dispatch notification. Dispatch acknowledges or denies. |
| Auto-generated incident report PDF on closure | Eliminates manual report writing. Every resolved incident produces a standardized PDF with all data captured during response | Medium | DomPDF or similar. Incident summary card: ID, type, priority, scene time, checklist %, vitals, tags, outcome, notes. Stored as report_pdf_url. |

### Multi-Channel Intake

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| IoT sensor webhook ingestion | Automated flood gauges, fire alarms, and weather stations create incidents without human reporting. Faster than waiting for a phone call about rising water | High | Webhook endpoint with HMAC-SHA256 validation. Sensor types: flood_gauge, fire_alarm, weather_station, seismic, cctv_motion. Auto-classify priority based on threshold exceedance. |
| SMS inbound parsing (Semaphore) | Public reports via text message in a country where SMS remains a primary communication channel. Keyword-based auto-classification | High | Semaphore API integration (stubbed initially). Inbound webhook. Keyword classifier for incident type suggestion. Auto-reply SMS on incident creation. |
| Channel monitor panel | Left panel showing live feed from all 5 channels. Unacknowledged messages highlighted. Gives dispatchers awareness of incoming volume | Medium | Real-time feed via WebSocket. Channel badges with pending count. Click to pull into triage form. |

### Analytics and Compliance

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| KPI dashboard (response time, scene arrival, resolution rate, unit utilization, false alarm rate) | Supervisors and the Mayor's Office need data-driven oversight. Manual calculation of response times from radio logs is impractical | Medium | Five core KPIs per spec with formulas and benchmarks. Filterable by date range, incident type, priority, barangay. |
| Incident heatmap (choropleth by barangay) | Visualizes incident density geographically. Informs resource pre-positioning and risk-level assessments per barangay | High | PostGIS spatial aggregation. MapLibre GL JS fill layer colored by count. Filters: type, priority, date range. Exportable as PNG or GeoJSON. |
| DILG monthly incident report (automated) | Replaces manually compiled paper reports submitted to DILG Regional Office on the 1st of each month. Automation ensures consistent, timely compliance | Medium | Aggregate incidents by type, priority, barangay, outcome. Generate PDF + CSV. Schedule via Laravel scheduler (1st of month). |
| NDRRMC Situation Report (on P1 closure) | RA 10121 requires LGUs to report significant incidents to NDRRMC/OCD. Auto-generating SitReps from structured incident data eliminates a multi-hour manual process | High | Map IRMS incident record to NDRRMC XML template. POST to NDRRMC Disaster Reporting API (stubbed). Fallback: formatted PDF emailed to OCD Caraga. |
| Quarterly and annual performance reports | CDRRMO Chief and Mayor's Office need periodic summaries for budget justification, council reporting, and performance evaluation | Medium | Aggregate KPIs over period. Trend analysis (response times improving/degrading). PDF with charts. |

### Real-Time Infrastructure

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Laravel Reverb WebSocket server | All real-time features (GPS tracking, status updates, messaging, new incident alerts) depend on sub-500ms message delivery. Reverb is the spec-mandated solution | High | 8+ broadcast event types. Private channels for unit assignments. Presence channels for dispatch console. Horizontal scaling via Redis pub/sub. |
| Optimistic UI updates with WebSocket confirmation | Responder taps "En Route" and sees immediate feedback before server confirms. Reduces perceived latency on spotty mobile networks | Medium | Optimistic state update in Vue. Revert on server error. Confirm on WebSocket broadcast receipt. |

---

## Anti-Features

Features to explicitly NOT build in v1. Each has a specific reason for exclusion.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Full ePCR/NEMSIS compliance | NEMSIS has 270 data elements designed for US Medicare reimbursement. Philippine EMS does not use NEMSIS. Building full ePCR adds massive complexity with zero local value | Capture simplified vitals (BP, HR, SpO2, GCS) and assessment tags. Sufficient for CDRRMO clinical handoff and NDRRMC reporting. |
| AI/ML triage classification | Requires training data (thousands of classified incidents) that does not exist yet. Building ML before the system generates data is premature optimization | Use rule-based keyword matching for auto-priority suggestion (P1-P4). Collect structured data now; ML becomes feasible in 12+ months when dataset exists. |
| PWA Service Worker / offline mode | Offline-capable apps require complex sync logic (conflict resolution, queue management, stale data handling). The responder workflow must work correctly online first | Build as responsive web app. Ensure fast load on 4G. Add PWA shell + offline caching as a future milestone after the online workflow is proven stable. |
| Capacitor.js native APK wrapper | Adding a native app wrapper before the web experience is solid creates two maintenance targets with shared bugs | Web-first. Revisit native wrapper only if web app proves inadequate on Android/iOS WebView. |
| Multi-tenancy (multiple LGU support) | Single-LGU deployment (Butuan City) must be proven first. Multi-tenancy adds database isolation, tenant routing, and config complexity with no immediate user | Deploy for Butuan City. If other LGUs adopt, evaluate multi-tenancy architecture then. |
| Pinia state management | Inertia v2 props + Vue composables may handle state needs. Adding Pinia prematurely creates a second source of truth for server-provided data | Start with Inertia props and reactive composables. Introduce Pinia only if complex client-side state emerges (e.g., map viewport state, offline queue). |
| Real external API integrations | No API keys, agreements, or endpoint credentials exist yet for Semaphore, PAGASA, hospital EHR, NDRRMC, BFP, PNP | Build integration layer with stub/mock endpoints. Interface contracts defined. Swap stubs for real implementations when agreements are in place. |
| Web Push notifications (VAPID) | Requires HTTPS service worker registration, VAPID key management, and browser permission UX. Adds complexity to the responder onboarding flow | Use in-app WebSocket notifications and audio alerts. Web Push can be added later for background notifications when app is not in foreground. |
| Public-facing incident reporting portal | A public portal introduces unauthenticated traffic, spam/abuse risk, moderation burden, and PII handling for citizens. It also requires a different UX optimized for non-expert users | All channels route through dispatchers (SMS parsed, voice transcribed, IoT auto-ingested). Public portal is a v2 feature after internal operations are stable. |
| Automated dispatch (auto-assign nearest unit) | Removing human judgment from dispatch decisions is risky in the Philippine context where road conditions, informal settlements, and flooding invalidate distance-based routing | Recommend units ranked by proximity. Dispatcher always makes final assignment decision. Log recommendation vs. actual choice for future ML training data. |
| Custom map tile server | Self-hosting map tiles adds significant infrastructure complexity (storage, rendering pipeline, updates) with marginal benefit over Mapbox-hosted tiles | Use Mapbox Streets v12 hosted basemap. MapLibre GL JS renders the tiles. Revisit self-hosting only if Mapbox costs become prohibitive at scale. |
| Video/image upload from responders | Media upload from field requires storage infrastructure, bandwidth management on 4G, content moderation, and significantly increases server costs | Text-based scene documentation (notes, vitals, tags, checklists). Photo/video upload can be a differentiator in v2. |

---

## Feature Dependencies

```
PostgreSQL + PostGIS
    |
    +---> Barangay boundary polygons
    |         |
    |         +---> Geocoding with auto-barangay assignment
    |         +---> Incident heatmap (choropleth)
    |         +---> DILG monthly report (by barangay)
    |
    +---> Spatial queries
              |
              +---> Proximity-based unit recommendation
              +---> ETA calculation (combined with Mapbox Directions)

Laravel Reverb WebSocket
    |
    +---> Real-time unit GPS tracking
    |         |
    |         +---> Unit marker animation on dispatch map
    |
    +---> Assignment push to responder
    |         |
    |         +---> Acknowledgement timer (90s)
    |
    +---> Bi-directional messaging
    +---> New incident alerts (audio + visual)
    +---> Status change broadcasts
    +---> Session metrics (real-time counters)

Role-based access control
    |
    +---> Dispatcher console access
    +---> Responder mobile access
    +---> Supervisor analytics access
    +---> Admin user management

Incident data model (with timestamps)
    |
    +---> Incident timeline / audit log
    +---> KPI calculations (response time, scene arrival)
    +---> Auto-generated PDF report
    +---> NDRRMC SitRep generation
    +---> DILG monthly report

Incident creation + dispatch queue
    |
    +---> Unit assignment workflow
              |
              +---> Responder assignment receipt
              +---> Status transition workflow
              +---> Scene documentation (checklists, vitals, tags)
              +---> Outcome/closure form
                        |
                        +---> PDF report generation
                        +---> KPI metric capture

MapLibre GL JS map
    |
    +---> Incident markers (WebGL circles)
    +---> Unit markers (WebGL circles)
    +---> Barangay boundary overlay
    +---> Incident heatmap layer
    +---> Responder navigation mini-map
```

---

## MVP Recommendation

### Phase 1: Build These First (Foundation + Intake)

Prioritize in this order:

1. **PostgreSQL + PostGIS migration** with barangay boundary data -- everything spatial depends on this
2. **Role-based access control** -- dispatcher/responder/supervisor/admin roles determine the entire UI structure
3. **Incident data model** with full lifecycle timestamps and incident timeline
4. **Units data model** with status, type, GPS coordinates, agency
5. **Incident creation form** (triage form) with geocoding and auto-barangay assignment
6. **Auto-priority classifier** (rule-based keyword matching, P1-P4)
7. **Dispatch queue** with priority ordering

### Phase 2: Build These Second (Dispatch Console)

8. **MapLibre GL JS dispatch map** with incident and unit WebGL markers
9. **Unit assignment workflow** (select incident, pick unit, assign)
10. **Laravel Reverb WebSocket** for real-time broadcasts
11. **Real-time unit GPS tracking** on dispatch map
12. **Assignment acknowledgement timer** (90 seconds)
13. **Audio/visual alerts** for new incidents by priority
14. **Session metrics** dashboard header

### Phase 3: Build These Third (Responder Mobile)

15. **Assignment receipt** with incident details via WebSocket
16. **Status transition workflow** (Acknowledged -> En Route -> On Scene -> Resolving -> Resolved)
17. **Navigation** (deep-link to Google Maps, then embedded mini-map)
18. **Bi-directional messaging** with dispatch
19. **Scene documentation** (checklists, vitals, assessment tags)
20. **Outcome/closure form** with hospital picker
21. **Resource request** from field

### Phase 4: Build These Fourth (Integration + Analytics)

22. **Stubbed integration layer** (SMS, geocoding, directions, weather, hospital, NDRRMC, BFP, PNP)
23. **Auto-generated PDF** on incident closure
24. **KPI dashboard** with five core metrics
25. **Incident heatmap** (choropleth by barangay)
26. **DILG monthly report** automation
27. **NDRRMC SitRep** generation
28. **Quarterly/annual reports**

### Defer These

- IoT sensor webhook ingestion -- until at least one physical sensor is deployed
- SMS inbound parsing -- until Semaphore API key is obtained
- Mutual aid protocol -- until inter-agency coordination agreements exist
- Embedded navigation mini-map -- until basic deep-link navigation is proven
- PAGASA weather overlay -- until API access is arranged

---

## Sources

- [911 CAD Software in 2025: Key Features, Vendors, and Emerging Trends](https://www.criticalcommunicationsreview.com/ccr/business-inside/115237/911-cad-software-in-2025-key-features-vendors-and-emerging-trends) -- HIGH confidence, industry overview
- [Computer Aided Dispatch Explained: Features, Uses, Benefits](https://www.ginasoftware.com/blog/computer-aided-dispatch/) -- HIGH confidence, comprehensive feature list
- [Law Enforcement CAD Systems (BJA/LEITSC)](https://bja.ojp.gov/sites/g/files/xyckuh186/files/media/document/leitsc_law_enforcement_cad_systems.pdf) -- HIGH confidence, US federal standards body
- [DHS CAD Systems TechNote](https://www.dhs.gov/sites/default/files/publications/CAD_TN_0911-508.pdf) -- HIGH confidence, US federal assessment
- [RA 10121: Philippine DRRM Act](https://lawphil.net/statutes/repacts/ra2010/ra_10121_2010.html) -- HIGH confidence, primary legislation
- [DROMIC Situation Reports (DSWD)](https://dromic.dswd.gov.ph/category/situation-reports/) -- MEDIUM confidence, operational examples
- [COA Assessment of DRRM at Local Level](https://web.coa.gov.ph/disaster_audit/doc/Local.pdf) -- HIGH confidence, audit body
- [UX Design for Crisis Situations (UXmatters)](https://www.uxmatters.com/mt/archives/2025/03/ux-design-for-crisis-situations-lessons-from-the-los-angeles-wildfires.php) -- MEDIUM confidence, UX patterns
- [NG 9-1-1 Call Taking UI Research (Frontiers)](https://www.frontiersin.org/journals/human-dynamics/articles/10.3389/fhumd.2022.670647/full) -- HIGH confidence, peer-reviewed
- [First Responder Mobile App Features (FirstDue)](https://www.firstdue.com/products/mobileresponder) -- MEDIUM confidence, vendor documentation
- [ePCR and EMS Reporting Guide (FirstDue)](https://www.firstdue.com/news/epcr-and-reporting) -- MEDIUM confidence, vendor documentation
- [Laravel Reverb Documentation](https://laravel.com/docs/12.x/reverb) -- HIGH confidence, official docs
- [MapLibre GL JS Documentation](https://maplibre.org/maplibre-gl-js/docs/) -- HIGH confidence, official docs
- [IRMS Technical Specification](docs/IRMS-Specification.md) -- PRIMARY SOURCE, 1126 lines covering all five layers
