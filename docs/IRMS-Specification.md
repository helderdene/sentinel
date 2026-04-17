# IRMS — Incident Response Management System
## Technical Specification Document

**Client:** City Disaster Risk Reduction and Management Office (CDRRMO), Butuan City  
**Region:** Caraga (Region XIII)  
**Prepared by:** HDSystem (HyperDrive System), Butuan City  
**Version:** 1.0  
**Date:** March 2025  
**Status:** Active Development

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Overview](#2-system-overview)
3. [Architecture](#3-architecture)
4. [Layer Specifications](#4-layer-specifications)
   - 4.1 [Intake Layer](#41-intake-layer)
   - 4.2 [Dispatch Layer](#42-dispatch-layer)
   - 4.3 [Responder Layer](#43-responder-layer)
   - 4.4 [Integration Layer](#44-integration-layer)
   - 4.5 [Analytics Layer](#45-analytics-layer)
5. [Data Models](#5-data-models)
6. [API Reference](#6-api-reference)
7. [Real-time Communication](#7-real-time-communication)
8. [Priority & Classification System](#8-priority--classification-system)
9. [User Roles & Permissions](#9-user-roles--permissions)
10. [Mobile Application](#10-mobile-application)
11. [External Integrations](#11-external-integrations)
12. [Infrastructure & Deployment](#12-infrastructure--deployment)
13. [Security](#13-security)
14. [Technology Stack](#14-technology-stack)
15. [Development Roadmap](#15-development-roadmap)
16. [Non-Functional Requirements](#16-non-functional-requirements)

---

## 1. Executive Summary

The Incident Response Management System (IRMS) is a full-stack digital platform for the CDRRMO of Butuan City, designed to modernize and streamline the city's emergency response operations. The system replaces manual radio-log and paper-based dispatch workflows with a real-time, multi-channel platform connecting the public, dispatchers, and field responders.

### Goals

- Reduce average emergency response time across all incident categories
- Provide real-time situational awareness to dispatchers and supervisors
- Enable structured, data-driven after-action reviews and NDRRMC-compliant reporting
- Deliver a maintainable, owned codebase with no recurring license fees

### Scope

The IRMS covers the full incident lifecycle:

> **Report → Intake → Triage → Dispatch → Response → Resolution → Reporting**

The system is comprised of five operational layers: Intake, Dispatch, Responder, Integration, and Analytics — each with dedicated UI components and backend services.

---

## 2. System Overview

### 2.1 System Layers

| Layer | Primary Users | Key Function |
|---|---|---|
| Intake Layer | Dispatcher | Receive and triage incident reports from all channels |
| Dispatch Layer | Dispatcher | Assign units, track live positions, manage queue |
| Responder Layer | Field Responder | Receive assignments, navigate, log scene data, close incidents |
| Integration Layer | System | Connect with external agencies and national systems |
| Analytics Layer | Supervisor, Mayor's Office | KPI monitoring, reporting, compliance |

### 2.2 Incident Channels

The system accepts incident reports from five input channels:

| Channel | Source | Method |
|---|---|---|
| SMS | General public | Semaphore API gateway |
| Mobile App | General public / Reporters | Public-facing PWA |
| Voice / 911 | General public | Manual dispatcher entry while on call |
| IoT Sensors | Automated infrastructure | Webhook push (flood gauges, fire alarms, CCTV) |
| Walk-in / Radio | Barangay officials, tanods | Walk-in desk entry or radio transcription |

### 2.3 Incident Lifecycle

```
[Report Received]
      │
      ▼
[Intake: Verify & Validate]
      │
      ├─── Invalid ──► [Archive / Discard]
      │
      ▼
[Classify Type + Priority P1–P4]
      │
      ▼
[Geocode & Pin on Map]
      │
      ▼
[Add to Dispatch Queue]
      │
      ▼
[Select & Assign Unit(s)]
      │
      ▼
[Push Assignment to Responder]
      │
      ├─── No Ack (90s) ──► [Reassign / Escalate]
      │
      ▼
[Responder: Acknowledge → En Route → On Scene]
      │
      ▼
[Scene Assessment + Treatment]
      │
      ├─── Resources Needed ──► [Resource Request → Dispatch]
      │
      ▼
[Outcome: Transport / Treated / DOA / Stand Down]
      │
      ▼
[Close Incident]
      │
      ▼
[Auto-save Record + Generate Report]
      │
      ▼
[After-Action Review → Archive]
```

---

## 3. Architecture

### 3.1 Application Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Client Tier                          │
│  ┌─────────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │  Dispatch Console│  │  Intake UI   │  │  Responder PWA│  │
│  │  (Vue 3 / Inertia│  │  (Vue 3)     │  │  (Vue 3 / PWA)│  │
│  └────────┬────────┘  └──────┬───────┘  └───────┬───────┘  │
└───────────┼──────────────────┼──────────────────┼──────────┘
            │                  │                  │
┌───────────┼──────────────────┼──────────────────┼──────────┐
│           │         Application Tier             │          │
│  ┌────────▼──────────────────▼──────────────────▼───────┐  │
│  │              Laravel 11 Application Server            │  │
│  │  ┌───────────┐  ┌──────────┐  ┌────────────────────┐ │  │
│  │  │ REST API  │  │ Inertia  │  │  Laravel Reverb    │ │  │
│  │  │ (Sanctum) │  │ SSR      │  │  (WebSocket Server)│ │  │
│  │  └───────────┘  └──────────┘  └────────────────────┘ │  │
│  │  ┌───────────┐  ┌──────────┐  ┌────────────────────┐ │  │
│  │  │ Job Queue │  │ Scheduler│  │  Event System      │ │  │
│  │  │ (Redis)   │  │ (CRON)   │  │  (Broadcast)       │ │  │
│  │  └───────────┘  └──────────┘  └────────────────────┘ │  │
│  └────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
            │
┌───────────▼──────────────────────────────────────────────────┐
│                        Data Tier                              │
│  ┌────────────────────────┐   ┌──────────────────────────┐   │
│  │  PostgreSQL + PostGIS  │   │  Redis                   │   │
│  │  (Primary Database)    │   │  (Cache / Queue / Pub-Sub│   │
│  └────────────────────────┘   └──────────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

### 3.2 Real-time Architecture

```
Responder GPS Update
        │
        ▼
[Laravel API: POST /api/units/{id}/location]
        │
        ▼
[Broadcast: UnitLocationUpdated event]
        │
        ▼
[Laravel Reverb WebSocket]
        │
        ├──► Dispatch Console (unit marker moves on map)
        └──► Supervisor Dashboard (unit status panel)
```

---

## 4. Layer Specifications

### 4.1 Intake Layer

**Purpose:** Receive, validate, classify, and geocode all incoming incident reports before queuing for dispatch.

**UI Layout:** Three-panel desktop interface

| Panel | Content |
|---|---|
| Left (Channel Monitor) | Live feed from all 5 channels — unacknowledged messages highlighted |
| Center (Triage Form) | Structured incident form: type, location, caller info, details |
| Right (Dispatch Queue) | Prioritized queue of triaged incidents awaiting assignment |

#### 4.1.1 Incident Form Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `incident_type` | Enum | Yes | See Section 8.1 for full taxonomy |
| `priority` | Enum (P1–P4) | Yes | Auto-suggested, dispatcher confirms |
| `location_text` | String | Yes | Free text address |
| `latitude` | Decimal | Auto | PostGIS geocoded |
| `longitude` | Decimal | Auto | PostGIS geocoded |
| `barangay_id` | FK | Auto | Detected via PostGIS spatial query |
| `caller_name` | String | No | For voice/walk-in |
| `caller_contact` | String | No | Phone number |
| `channel` | Enum | Yes | SMS, APP, VOICE, IOT, WALKIN |
| `raw_message` | Text | No | Original SMS/IoT payload |
| `notes` | Text | No | Dispatcher notes |

#### 4.1.2 Auto-Priority Classifier

The system suggests a priority level based on incident type keywords:

| Priority | Trigger Types |
|---|---|
| P1 — Critical | Cardiac Arrest, Structure Fire, Drowning, Electrocution, Building Collapse |
| P2 — High | Road Accident, Assault, Stabbing, Gas Leak, Flood |
| P3 — Medium | Domestic Violence, Seizure, Chest Pain, Missing Person |
| P4 — Low | All others (informational, non-urgent) |

The dispatcher can override the suggested priority at any time. The confidence score (rule match strength) is shown alongside the suggestion.

#### 4.1.3 Geocoding

1. Address string submitted to **Mapbox Geocoding API** (primary) with Philippines country filter
2. If Mapbox fails, fallback to **PhilGIS** national geocoder
3. Resulting coordinates passed through **PostGIS `ST_Contains()`** query against `barangays` polygon table
4. Barangay assignment auto-populated; dispatcher can manually correct

#### 4.1.4 IoT Sensor Integration

Sensors push alerts via HTTP POST webhook to:

```
POST /api/webhooks/iot-sensor
Authorization: Bearer {sensor_token}
```

Payload schema:

```json
{
  "sensor_id": "FLOOD-GAUGE-001",
  "sensor_type": "flood_gauge",
  "location": { "lat": 8.9475, "lng": 125.5406 },
  "value": 2.85,
  "unit": "meters",
  "threshold": 2.0,
  "alert_level": "critical",
  "timestamp": "2025-03-12T10:30:00Z"
}
```

Supported sensor types: `flood_gauge`, `fire_alarm`, `weather_station`, `seismic`, `cctv_motion`

---

### 4.2 Dispatch Layer

**Purpose:** Provide dispatchers with a real-time 3D situational map, unit assignment tools, and communication interface.

#### 4.2.1 3D Map Console

Built on **MapLibre GL JS** with a WebGL rendering pipeline. No HTML marker overlays — all markers are rendered as WebGL layers for performance.

**Map Settings:**

| Setting | Value |
|---|---|
| Pitch | 45° |
| Zoom (default) | 13 |
| Center | Butuan City (8.9475° N, 125.5406° E) |
| Style | Custom dark/light vector tile style |
| Basemap | Mapbox Streets v12 |

**Incident Layer Stack** (source: `inc-pts`):

| Layer ID | Type | Purpose |
|---|---|---|
| `inc-halo` | Circle | Outer pulse ring (animated) |
| `inc-pa`, `inc-pb` | Circle | Secondary pulse rings |
| `inc-border` | Circle | Incident marker border |
| `inc-pin` | Circle | Main marker body (priority color) |
| `inc-dot` | Circle | Center dot |

Incident marker data properties:

| Property | Type | Description |
|---|---|---|
| `r` | String (hex) | Priority color code |
| `sel` | Integer (0/1) | Selected state (1 = selected) |
| `priority` | String | P1–P4 |
| `type` | String | Incident type label |

**Unit Layer Stack** (source: `unit-pts`):

| Layer ID | Type | Purpose |
|---|---|---|
| `unit-glow` | Circle | Status glow halo |
| `unit-border` | Circle | Unit marker border |
| `unit-body` | Circle | Unit body (status color) |
| `unit-ring` | Circle | Agency ring |
| `unit-dot` | Circle | Center indicator |

Unit status colors:

| Status | Color |
|---|---|
| Available | `#3bba8c` |
| En Route | `#4a9eff` |
| On Scene | `#f5c518` |
| Offline | `#2a3a50` |

#### 4.2.2 Assignment Workflow

1. Dispatcher selects incident from queue or map
2. System filters available units by: proximity, unit type match, agency jurisdiction
3. Dispatcher clicks unit(s) to assign — multiple units supported per incident
4. ETA calculated via **Mapbox Directions API** (road network)
5. Assignment pushed via WebSocket to responder device
6. 90-second acknowledgement timer starts — if no response, dispatcher is alerted

#### 4.2.3 Mutual Aid Protocol

Triggered when no suitable units are available:

1. System displays mutual aid modal with suggested agencies
2. Dispatcher selects agency: BFP, PNP, DSWD, Adjacent LGU, DOH
3. Contact information and radio channel displayed
4. Mutual aid request logged to incident timeline

#### 4.2.4 Audio Alert System

| Event | Alert |
|---|---|
| New P1 incident | High-priority alarm tone + red screen flash |
| New P2 incident | Medium tone |
| No unit acknowledgement (90s) | Warning tone |
| Mutual aid required | Distinct tone |

Implemented via **Web Audio API** with configurable alert sounds per priority.

#### 4.2.5 Session Metrics (Dispatch Dashboard)

Displayed in real-time on dispatch console header:

- Total incidents received (session)
- Triaged / Pending
- Active incidents
- Units available / deployed
- Average handle time (this session)

---

### 4.3 Responder Layer

**Purpose:** Field responder mobile application for receiving assignments, navigating to scene, documenting response, and closing incidents.

#### 4.3.1 Status Workflow

```
STANDBY → ACKNOWLEDGED → EN ROUTE → ON SCENE → RESOLVING → RESOLVED
```

| Status | Color | Description |
|---|---|---|
| STANDBY | `#4a6080` | Unit available, no assignment |
| ACKNOWLEDGED | `#4a9eff` | Assignment received and confirmed |
| EN ROUTE | `#4a9eff` | Unit moving to scene |
| ON SCENE | `#f5c518` | Unit arrived, scene operations active |
| RESOLVING | `#ff8c00` | Intervention complete, documenting |
| RESOLVED | `#3bba8c` | Incident closed |

Each status transition:
- Auto-creates a timeline entry with timestamp
- Broadcasts via WebSocket to dispatch console
- Updates unit marker color on dispatch map

#### 4.3.2 Tab Configuration

Tabs displayed to the responder are status-aware:

| Status | Tabs Available |
|---|---|
| STANDBY / ACKNOWLEDGED / EN ROUTE | Info · Nav · Comms |
| ON SCENE / RESOLVING | Info · Nav · Scene · Outcome · Comms |
| RESOLVED | Info · Nav · Report · Comms |

#### 4.3.3 Navigation Tab

- Embedded **MapLibre GL JS** mini-map with:
  - Animated route polyline (dashed, flowing animation)
  - Incident location pulse ring
  - Unit position indicator
  - ETA chip (live countdown while EN ROUTE)
- **NAVIGATE** button deep-links to Google Maps with incident coordinates
- Scene timer (counting up) displayed from moment of ON SCENE arrival

#### 4.3.4 Scene Tab (ON SCENE+)

**Contextual Arrival Checklist** — items differ per incident type:

| Incident Type | Checklist Items |
|---|---|
| Cardiac Arrest | AED ready, O2 supply, Airway management kit, IV access, Defibrillator pads, CPR board, Scene safety confirmed |
| Road Accident | Scene secured, Spinal precautions, Extrication equipment, Cervical collar, Tourniquet, Backboard |
| Structure Fire | BFP coordination established, Respiratory protection, Burn dressings, Triage area established |
| Default | Scene assessment, Patient contact, Safety confirmed, Equipment ready |

Checklist UI: animated checkboxes with progress bar (% complete shown to dispatch).

**Patient Vitals Form:**

| Field | Format | Placeholder |
|---|---|---|
| Blood Pressure | `mmHg` | `120/80` |
| Heart Rate | `bpm` | `72 bpm` |
| SpO₂ | `%` | `98%` |
| GCS Score | `3–15` | `15` |

**Quick Assessment Tags** (toggle chips):

`Conscious` · `Breathing` · `Bleeding` · `Unresponsive` · `Fracture` · `Burns` · `Shock` · `Chest Pain` · `Head Trauma` · `Airway Compromised` · `Anaphylaxis`

**Field Notes:** Free-text textarea, auto-saved locally every 30 seconds.

**Resource Request:** Button opens resource request sheet with 6 options:

| Resource | Agency |
|---|---|
| Additional Ambulance | CDRRMO |
| Fire Unit | BFP |
| Police Backup | PNP |
| Rescue Boat | CDRRMO |
| Medical Officer | DOH |
| Medevac Helicopter | PhilAF |

#### 4.3.5 Outcome Tab (RESOLVING)

Responder selects one outcome — action button is locked until a valid selection is made:

| Outcome | Follow-up Required |
|---|---|
| Treated On Scene | Closure notes |
| Transported to Hospital | Hospital selection + closure notes |
| Patient Refused Treatment | Closure notes |
| Declared DOA | Closure notes (BFP/PNP coordination flagged) |
| Stand Down / False Alarm | Closure notes |

Hospital picker (appears for Transport outcome):

- Caraga Regional Hospital
- Butuan Medical Center
- Polymedic General Hospital
- Nazareth General Hospital
- Others (free text)

#### 4.3.6 Resolved Summary (Auto-generated Report)

On closure, the app generates an incident summary card containing:

- Incident ID, type, priority
- Total scene time
- Checklist completion percentage
- All vitals recorded
- Assessment tags selected
- Outcome and hospital (if applicable)
- Closure notes

This summary is transmitted to the backend and triggers PDF report generation.

#### 4.3.7 Communication Tab

- Bi-directional messaging with DISPATCH
- Quick reply chips (8 preset messages, horizontally scrollable):
  - "On scene now", "Requesting backup", "Patient stable", "Transport to hospital", "Scene clear", "Returning to base", "Copy that", "ETA 2 minutes"
- Message history persists for incident duration
- Auto-reply simulation from DISPATCH for resource confirmations

#### 4.3.8 Notification Toast System

- Slides in from top of screen
- Auto-dismisses after 4 seconds
- Triggered by: status changes, dispatch messages, resource confirmations, new assignments
- P1 assignments use a more prominent toast with audio cue

---

### 4.4 Integration Layer

**Purpose:** Connect IRMS with external government systems and partner agencies.

#### 4.4.1 Hospital EHR Handoff

Pre-notify receiving hospital before patient arrival:

- Protocol: HL7 FHIR R4
- Endpoint: Caraga Regional Hospital HIMS API
- Payload: Patient vitals, assessment tags, incident type, ETA, unit ID
- Trigger: Outcome = "Transported to Hospital" + hospital selection

#### 4.4.2 NDRRMC Situation Report

Auto-generate and submit NDRRMC-format situation reports:

- Trigger: P1 incident closure, or on-demand by supervisor
- Format: NDRRMC XML template mapped from IRMS incident record
- Delivery: POST to NDRRMC Disaster Reporting API
- Fallback: Formatted PDF emailed to regional OCD

#### 4.4.3 BFP Fire Incident System

Bidirectional sync with Bureau of Fire Protection:

- Avoid duplicate incident creation for joint responses
- BFP fire incidents auto-mirrored into IRMS dispatch queue (with BFP branding)
- IRMS fire incidents pushed to BFP system via REST webhook

#### 4.4.4 PNP e-Blotter

For criminal incident types (assault, stabbing, homicide):

- Auto-create blotter entry in PNP e-Blotter system
- Payload: Incident summary, location, responder ID, timestamp
- Requires explicit dispatcher confirmation before submission

#### 4.4.5 PAGASA Weather Feed

Live weather data overlaid on dispatch map:

- Rainfall intensity (mm/hr) by station
- Wind speed and direction
- Active flood advisories (color-coded polygons)
- Trigger rule: Active flood advisory → auto-escalate all flood incidents to P2 minimum

#### 4.4.6 SMS Gateway (Semaphore)

| Direction | Purpose |
|---|---|
| Inbound | Public incident reports parsed by keyword classifier |
| Outbound | Acknowledgement SMS to reporter on incident creation |
| Outbound | Status updates to reporter (On Scene, Resolved) |

---

### 4.5 Analytics Layer

**Purpose:** Provide supervisors, the CDRRMO Chief, and the Mayor's Office with performance dashboards and compliance reports.

#### 4.5.1 KPI Metrics

| KPI | Formula | Benchmark |
|---|---|---|
| Average Response Time | `(EN_ROUTE timestamp) - (Incident created timestamp)` | ≤ 8 min (P1) |
| Average Scene Arrival Time | `(ON_SCENE timestamp) - (Dispatch assigned timestamp)` | ≤ 12 min (P1) |
| Resolution Rate | `Resolved / Total Closed × 100` | ≥ 95% |
| Unit Utilization | `Active time / Shift duration × 100` | 60–80% target |
| False Alarm Rate | `Discarded / Total Received × 100` | ≤ 10% |

#### 4.5.2 Incident Heatmap

- Choropleth map colored by incident density per barangay
- Filter controls: incident type, priority, date range (30/90/365 days)
- Built with **MapLibre GL JS** + **PostGIS** spatial aggregation queries
- Exportable as PNG or GeoJSON

#### 4.5.3 Automated Compliance Reports

| Report | Schedule | Recipient | Format |
|---|---|---|---|
| DILG Monthly Incident Report | 1st of each month | DILG Regional Office | PDF + CSV |
| NDRRMC Situational Report | On P1 closure | OCD Caraga | XML |
| Quarterly Performance Report | End of quarter | CDRRMO Chief | PDF |
| Annual Statistical Summary | January 1 | Mayor's Office | PDF |

---

## 5. Data Models

### 5.1 Incidents Table

```sql
CREATE TABLE incidents (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    incident_no     VARCHAR(20) UNIQUE NOT NULL,   -- e.g. INC-2025-00142
    type            VARCHAR(60) NOT NULL,
    priority        CHAR(2) NOT NULL CHECK (priority IN ('P1','P2','P3','P4')),
    status          VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    channel         VARCHAR(20) NOT NULL,
    location_text   TEXT,
    coordinates     GEOGRAPHY(POINT, 4326),
    barangay_id     INTEGER REFERENCES barangays(id),
    caller_name     VARCHAR(100),
    caller_contact  VARCHAR(30),
    raw_message     TEXT,
    notes           TEXT,
    assigned_unit   VARCHAR(20) REFERENCES units(id),
    dispatched_at   TIMESTAMPTZ,
    acknowledged_at TIMESTAMPTZ,
    en_route_at     TIMESTAMPTZ,
    on_scene_at     TIMESTAMPTZ,
    resolved_at     TIMESTAMPTZ,
    outcome         VARCHAR(50),
    hospital        VARCHAR(100),
    scene_time_sec  INTEGER,
    checklist_pct   SMALLINT,
    vitals          JSONB,
    assessment_tags TEXT[],
    closure_notes   TEXT,
    report_pdf_url  VARCHAR(255),
    created_by      INTEGER REFERENCES users(id),
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_incidents_coordinates ON incidents USING GIST (coordinates);
CREATE INDEX idx_incidents_priority ON incidents (priority);
CREATE INDEX idx_incidents_status ON incidents (status);
CREATE INDEX idx_incidents_created_at ON incidents (created_at);
```

### 5.2 Units Table

```sql
CREATE TABLE units (
    id          VARCHAR(20) PRIMARY KEY,    -- e.g. AMB-01
    name        VARCHAR(100) NOT NULL,
    type        VARCHAR(30) NOT NULL,       -- ambulance, fire, rescue, police
    agency      VARCHAR(30) NOT NULL,       -- CDRRMO, BFP, PNP
    status      VARCHAR(20) DEFAULT 'OFFLINE',
    coordinates GEOGRAPHY(POINT, 4326),
    location_at TIMESTAMPTZ,
    crew        SMALLINT DEFAULT 2,
    shift       VARCHAR(10),
    active      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_units_coordinates ON units USING GIST (coordinates);
```

### 5.3 Incident Timeline Table

```sql
CREATE TABLE incident_timeline (
    id          BIGSERIAL PRIMARY KEY,
    incident_id UUID REFERENCES incidents(id) ON DELETE CASCADE,
    event_type  VARCHAR(50) NOT NULL,
    event_data  JSONB,
    actor_type  VARCHAR(20),               -- user, unit, system
    actor_id    VARCHAR(50),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

### 5.4 Incident Messages Table

```sql
CREATE TABLE incident_messages (
    id          BIGSERIAL PRIMARY KEY,
    incident_id UUID REFERENCES incidents(id) ON DELETE CASCADE,
    from_id     VARCHAR(50) NOT NULL,
    from_type   VARCHAR(20) NOT NULL,      -- unit, dispatch, system
    body        TEXT NOT NULL,
    read_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

### 5.5 Users Table

```sql
CREATE TABLE users (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        VARCHAR(30) NOT NULL,      -- dispatcher, responder, supervisor, admin
    agency      VARCHAR(30),
    unit_id     VARCHAR(20) REFERENCES units(id),
    active      BOOLEAN DEFAULT TRUE,
    last_login  TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

### 5.6 Barangays Table

```sql
CREATE TABLE barangays (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    district    VARCHAR(50),
    city        VARCHAR(50) DEFAULT 'Butuan City',
    boundary    GEOGRAPHY(POLYGON, 4326),
    population  INTEGER,
    risk_level  VARCHAR(20)                -- low, medium, high, critical
);

CREATE INDEX idx_barangays_boundary ON barangays USING GIST (boundary);
```

---

## 6. API Reference

### 6.1 Authentication

All API endpoints require a Bearer token issued via Laravel Sanctum.

```
Authorization: Bearer {token}
```

Login:

```
POST /api/auth/login
Body: { email, password }
Response: { token, user, unit }
```

### 6.2 Incidents

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/incidents` | List incidents (filterable by status, priority, date) |
| `POST` | `/api/incidents` | Create new incident |
| `GET` | `/api/incidents/{id}` | Get single incident with timeline |
| `PATCH` | `/api/incidents/{id}` | Update incident fields |
| `POST` | `/api/incidents/{id}/assign` | Assign unit(s) to incident |
| `POST` | `/api/incidents/{id}/status` | Update incident status |
| `POST` | `/api/incidents/{id}/messages` | Send message on incident |
| `POST` | `/api/incidents/{id}/resources` | Submit resource request |
| `POST` | `/api/incidents/{id}/close` | Close incident with outcome |

### 6.3 Units

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/units` | List all units with current status |
| `GET` | `/api/units/nearby` | Units within radius (lat, lng, radius_km) |
| `PATCH` | `/api/units/{id}/location` | Update GPS coordinates |
| `PATCH` | `/api/units/{id}/status` | Update unit status |

### 6.4 Dispatch

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/dispatch/queue` | Current dispatch queue (prioritized) |
| `GET` | `/api/dispatch/metrics` | Live session metrics |
| `POST` | `/api/dispatch/mutual-aid` | Log mutual aid request |

### 6.5 Webhooks

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/webhooks/sms` | Semaphore SMS inbound |
| `POST` | `/api/webhooks/iot-sensor` | IoT sensor alert |
| `POST` | `/api/webhooks/bfp` | BFP fire incident sync |

---

## 7. Real-time Communication

All real-time events are broadcast via **Laravel Reverb** (Pusher-compatible WebSocket server).

### 7.1 Broadcast Events

| Event Class | Channel | Payload | Listeners |
|---|---|---|---|
| `IncidentCreated` | `incidents` | Full incident object | Dispatch console |
| `IncidentUpdated` | `incidents.{id}` | Changed fields + timestamp | Dispatch, Responder |
| `IncidentStatusChanged` | `incidents.{id}` | `{ old_status, new_status, timestamp }` | Dispatch, Supervisor |
| `UnitLocationUpdated` | `units` | `{ unit_id, lat, lng, timestamp }` | Dispatch map |
| `UnitStatusChanged` | `units.{id}` | `{ status, timestamp }` | Dispatch console |
| `AssignmentPushed` | `units.{id}.private` | Full assignment payload | Responder app |
| `MessageSent` | `incidents.{id}` | Message object | Responder, Dispatch |
| `ResourceRequested` | `incidents.{id}` | Resource request details | Dispatch console |

### 7.2 GPS Update Frequency

| Status | Update Interval |
|---|---|
| EN ROUTE | Every 10 seconds |
| ON SCENE | Every 60 seconds (stationary) |
| STANDBY / OFFLINE | On-demand only |

---

## 8. Priority & Classification System

### 8.1 Incident Type Taxonomy

| Category | Incident Types |
|---|---|
| **Medical** | Cardiac Arrest, Stroke, Respiratory Distress, Trauma, Seizure, Anaphylaxis, Obstetric Emergency, Poisoning, Drowning, Electrocution |
| **Fire** | Structure Fire, Grass/Brush Fire, Vehicle Fire, Electrical Fire, Industrial Fire |
| **Rescue** | Road Accident, Building Collapse, Water Rescue, Confined Space Rescue, High Angle Rescue, Entrapment |
| **Flood** | Flash Flood, Riverine Flood, Storm Surge |
| **Public Safety** | Assault, Stabbing, Shooting, Missing Person, Domestic Violence, Civil Unrest |
| **Hazmat** | Gas Leak, Chemical Spill, Fuel Leak |
| **Infrastructure** | Power Outage, Water Supply Failure, Road Obstruction |
| **Other** | Animal Incident, Noise Complaint, Request for Assistance |

### 8.2 Priority Color Codes

| Priority | Code | Color | Hex |
|---|---|---|---|
| P1 — Critical | Life-threatening, immediate response | Red | `#ff3b3b` |
| P2 — High | Serious risk, urgent response | Orange | `#ff8c00` |
| P3 — Medium | Urgent but stable | Amber | `#f5c518` |
| P4 — Low | Non-emergency, informational | Green | `#3bba8c` |

---

## 9. User Roles & Permissions

| Permission | Dispatcher | Responder | Supervisor | Admin |
|---|---|---|---|---|
| View dispatch map | ✅ | ❌ | ✅ | ✅ |
| Create incidents | ✅ | ❌ | ✅ | ✅ |
| Assign units | ✅ | ❌ | ✅ | ✅ |
| Update unit status | ❌ | ✅ | ✅ | ✅ |
| View own assignment | ✅ | ✅ | ✅ | ✅ |
| Close incident | ❌ | ✅ | ✅ | ✅ |
| View analytics | ❌ | ❌ | ✅ | ✅ |
| Generate reports | ❌ | ❌ | ✅ | ✅ |
| Manage users | ❌ | ❌ | ❌ | ✅ |
| System configuration | ❌ | ❌ | ❌ | ✅ |

---

## 10. Mobile Application

### 10.1 Technical Approach

| Aspect | Specification |
|---|---|
| Framework | Vue 3 PWA |
| Styling | Tailwind CSS utility classes, custom CSS for animations |
| State management | Pinia |
| Maps | MapLibre GL JS |
| Offline storage | Service Worker cache (static assets + last-viewed incidents) |
| Push notifications | Web Push API (VAPID) via Laravel |
| Background sync | Service Worker Background Sync API |

### 10.2 Responsive Breakpoints

| Breakpoint | Layout | Navigation |
|---|---|---|
| Mobile (< 640px) | Single column | Bottom nav bar (58px touch targets) |
| Tablet (640px–1023px) | Horizontal assignment strip above main | Bottom nav bar |
| Desktop (≥ 1024px) | Left sidebar (280px) + main content | Sidebar tabs |

### 10.3 PWA Configuration

```json
{
  "name": "IRMS Responder",
  "short_name": "IRMS",
  "display": "standalone",
  "orientation": "portrait-primary",
  "theme_color": "#0d1b2e",
  "background_color": "#04080f",
  "start_url": "/responder",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

### 10.4 Viewport Configuration

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0,
  viewport-fit=cover, maximum-scale=1"/>
```

Safe area insets applied to: top bar, bottom nav, input areas.

---

## 11. External Integrations

| System | Protocol | Auth | Phase |
|---|---|---|---|
| Semaphore SMS API | REST | API Key | 1 |
| Mapbox Geocoding API | REST | Access Token | 1 |
| Mapbox Directions API | REST | Access Token | 2 |
| PAGASA Weather API | REST | API Key | 4 |
| Caraga Regional Hospital HIMS | HL7 FHIR R4 | OAuth2 | 4 |
| NDRRMC Reporting API | REST / XML | API Key | 4 |
| BFP Incident System | REST (webhook) | HMAC Signature | 4 |
| PNP e-Blotter | REST | OAuth2 | 4 |

---

## 12. Infrastructure & Deployment

### 12.1 Server Specifications

| Component | Specification |
|---|---|
| Application Server | DigitalOcean Droplet — 4 vCPU, 8 GB RAM |
| Database | DigitalOcean Managed PostgreSQL — 2 vCPU, 4 GB RAM |
| Cache / Queue | DigitalOcean Managed Redis |
| File Storage | DigitalOcean Spaces (S3-compatible) |
| CDN | DigitalOcean CDN (auto-provisioned with Spaces) |
| SSL | Let's Encrypt (auto-renewed via Certbot) |

### 12.2 Web Server

- **Nginx** reverse proxy
- PHP-FPM 8.2 process manager
- Laravel Reverb WebSocket server on port `6001`
- Laravel Horizon for queue monitoring
- Supervisor for process management

### 12.3 Environment Variables

```ini
APP_ENV=production
APP_URL=https://irms.cdrrmo-butuan.gov.ph

DB_CONNECTION=pgsql
DB_HOST=managed-pg-host
DB_PORT=5432
DB_DATABASE=irms_production

REDIS_HOST=managed-redis-host
REDIS_PORT=6380

MAPBOX_ACCESS_TOKEN=pk.eyJ...
SEMAPHORE_API_KEY=...
PAGASA_API_KEY=...

REVERB_APP_ID=irms
REVERB_APP_KEY=...
REVERB_APP_SECRET=...

VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

### 12.4 CI/CD Pipeline

```
GitHub Push (main branch)
        │
        ▼
GitHub Actions — Run Tests (PHPUnit + Pest)
        │
        ▼
Build Frontend (npm run build)
        │
        ▼
Deploy via SSH (Envoyer / custom deploy script)
        │
        ▼
Run Migrations (php artisan migrate --force)
        │
        ▼
Restart Reverb + Horizon (Supervisor restart)
        │
        ▼
Smoke Test (curl health check endpoint)
```

---

## 13. Security

### 13.1 Authentication

- Laravel Sanctum token-based authentication for API
- Token expiry: 12 hours (responder), 24 hours (dispatcher)
- Refresh token flow for mobile PWA
- Brute-force protection: 5 failed logins → 15-minute lockout

### 13.2 Authorization

- Policy-based authorization (Laravel Policies) on all model mutations
- Middleware: `auth:sanctum`, `role:dispatcher`, `role:responder`, etc.
- All API routes explicitly declared — no implicit route exposure

### 13.3 Data Protection

- All data encrypted in transit (TLS 1.3)
- Database credentials via environment variables (never in code)
- PII fields (caller name, contact) stored with application-layer encryption
- Audit log for all incident create/update/delete actions
- Patient vitals classified as sensitive — access logged

### 13.4 Webhook Security

IoT and external webhooks validated via HMAC-SHA256 signature:

```php
$signature = hash_hmac('sha256', $request->getContent(), config('irms.webhook_secret'));
if (!hash_equals($signature, $request->header('X-Signature-256'))) {
    abort(401, 'Invalid signature');
}
```

### 13.5 Rate Limiting

| Endpoint Group | Limit |
|---|---|
| Auth (login) | 10 req / min |
| API (general) | 120 req / min per token |
| Webhook inbound | 60 req / min per sensor |
| GPS update | 1 req / 5 sec per unit |

---

## 14. Technology Stack

| Layer | Technology | Version | Purpose |
|---|---|---|---|
| Backend | Laravel | 11.x | Application framework, API, jobs |
| Frontend | Vue 3 | 3.4.x | Reactive UI components |
| SSR Bridge | Inertia.js | 1.x | Laravel ↔ Vue SPA bridge |
| Build Tool | Vite | 5.x | Frontend bundler |
| CSS | Tailwind CSS | 3.x | Utility-first styling |
| Database | PostgreSQL | 16.x | Primary relational database |
| Geospatial | PostGIS | 3.4.x | Spatial queries and indexing |
| Cache/Queue | Redis | 7.x | Queue backend, session cache |
| WebSocket | Laravel Reverb | 1.x | Real-time broadcast server |
| State Management | Pinia | 2.x | Vue 3 store |
| 3D Maps | MapLibre GL JS | 4.x | WebGL map rendering |
| Geocoding | Mapbox API | v6 | Address → coordinates |
| SMS | Semaphore | v4 | SMS gateway (Philippines) |
| PDF | Dompdf | 3.x | Report generation |
| ML/AI | Python + FastAPI | 3.11 / 0.100 | Predictive analytics service (Phase 5) |

---

## 15. Development Roadmap

| Phase | Name | Timeline | Status |
|---|---|---|---|
| Phase 0 | Foundation | Jan–Feb 2025 | ✅ Complete |
| Phase 1 | Intake Layer | Mar–Apr 2025 | ✅ Complete |
| Phase 2 | Dispatch Console | May–Jun 2025 | ✅ Complete |
| Phase 3 | Responder Mobile App | Jul–Sep 2025 | 🔄 In Progress (72%) |
| Phase 4 | Integration & APIs | Oct–Nov 2025 | ⏳ Planned |
| Phase 5 | Analytics & Reporting | Dec 2025–Jan 2026 | ⏳ Planned |

### Phase 3 Remaining Items

- [ ] Outcome + closure form backend integration (Laravel API)
- [ ] PWA Service Worker registration + offline caching
- [ ] Web Push API integration (VAPID) for background notifications
- [ ] Auto-generated incident report PDF on closure
- [ ] End-to-end testing (Playwright)

---

## 16. Non-Functional Requirements

### 16.1 Performance

| Metric | Target |
|---|---|
| API response time (p95) | < 200ms |
| Map render (initial load) | < 3 seconds on 4G |
| WebSocket message delivery | < 500ms |
| GPS update processing | < 1 second |
| Report PDF generation | < 5 seconds |

### 16.2 Availability

- System uptime target: **99.5%** (≈ 44 hours downtime/year)
- Planned maintenance: Off-peak hours (2:00–4:00 AM)
- No maintenance windows during declared states of calamity

### 16.3 Scalability

- Application server horizontally scalable behind Nginx load balancer
- Database read replicas for analytics queries
- Redis cluster for high-throughput queue at province scale

### 16.4 Browser & Device Support

| Client | Minimum Support |
|---|---|
| Dispatch Console | Chrome 110+, Edge 110+, desktop only |
| Responder App | Chrome 110+ on Android, Safari 16.4+ on iOS |
| Intake UI | Chrome 110+, Firefox 110+, desktop |
| Analytics Dashboard | Chrome 110+, Edge 110+, desktop |

### 16.5 Accessibility

- WCAG 2.1 Level AA compliance for public-facing components
- Minimum touch target: 44×44px on mobile
- All color-coded elements include text/icon redundancy (not color-only)

### 16.6 Data Retention

| Data Type | Retention Period |
|---|---|
| Incident records | 10 years (NDRRMC policy) |
| GPS track logs | 2 years |
| Message history | 5 years |
| Audit logs | 7 years |
| Archived false alarms | 1 year |

---

*Document maintained by HDSystem (HyperDrive System), Butuan City.*  
*For technical queries: [hdystem@butuan.gov.ph](mailto:hdystem@butuan.gov.ph)*  
*Last updated: March 2025*
