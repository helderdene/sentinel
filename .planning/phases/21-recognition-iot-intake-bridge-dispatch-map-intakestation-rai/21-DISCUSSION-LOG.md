# Phase 21: Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-22
**Phase:** 21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai
**Areas discussed:** Incident trigger rules + payload, FrasIncidentFactory shape & dedup, Map pulse + fras.alerts, IntakeStation rail + Escalate-to-P1

---

## Gray Area Selection

| Option | Description | Selected |
|--------|-------------|----------|
| Incident trigger rules + payload | Categories, IncidentType, coordinates, severity→priority map | ✓ |
| FrasIncidentFactory shape & dedup | Service class, controller refactor, gate placement, dedup storage | ✓ |
| Map pulse + fras.alerts channel | Channel auth, event payload, animation mechanism, duration | ✓ |
| IntakeStation rail + Escalate-to-P1 | Rail count, data source, button placement, gate | ✓ |

**All four areas selected.**

---

## Incident trigger rules + payload

### Which Personnel categories trigger Incident creation?

| Option | Description | Selected |
|--------|-------------|----------|
| block + missing + lost_child | All three BOLO categories create Incidents. `allow` never does. SC1 text amended. | ✓ |
| block only (strict SC1 reading) | Only `block` creates Incidents; missing + lost_child become manual promotes. | |
| All categories, priority depends on category | Any enrolled-personnel Critical match (including `allow`) creates Incident. | |

**User's choice:** block + missing + lost_child (Recommended).
**Notes:** SC1 amendment to "block-list OR missing OR lost_child personnel" flagged for planning; RECOGNITION-02 same amendment.

### What IncidentType should recognition-born Incidents use?

| Option | Description | Selected |
|--------|-------------|----------|
| New 'Person of Interest' type, category-derived | Single type; category label lands in notes + timeline event_data. | ✓ |
| Three new types (one per BOLO category) | Separate IncidentTypes for block/missing/lost_child; more granular reporting. | |
| Reuse existing 'Other' or closest match | No seeder; loosely-typed FRAS incidents. | |

**User's choice:** New 'Person of Interest' type, category-derived (Recommended).
**Notes:** Single type keeps dispatch filtering simple; category detail preserved in notes + timeline for reporting/analytics.

### Incident.coordinates + barangay_id — copy camera's location verbatim?

| Option | Description | Selected |
|--------|-------------|----------|
| Copy camera.location + camera.barangay_id verbatim | Reuse Phase 20 cached barangay assignment. | ✓ |
| Copy location only; reverse-barangay at Incident time | Defensive against boundary drift. | |
| Leave location null (location_text only) | Skip map rendering for FRAS incidents. | |

**User's choice:** Copy verbatim (Recommended).
**Notes:** Operational barangay-drift risk accepted as negligible at CDRRMO cadence; saves a PostGIS query per incident.

### Default severity→priority map shape in config/fras.php?

| Option | Description | Selected |
|--------|-------------|----------|
| Full map: severity × category → priority | Critical × block/missing → P2; Critical × lost_child → P1. | ✓ |
| Flat: severity → priority only | Critical→P2 for all categories; operator escalates manually. | |
| Locked Critical→P2 in code, config only for thresholds | Violates RECOGNITION-08 spirit. | |

**User's choice:** Full map: severity × category → priority (Recommended).
**Notes:** Lost child defaults to P1 (safeguarding urgency); configurable without deploy.

---

## FrasIncidentFactory shape & dedup

### Service class structure?

| Option | Description | Selected |
|--------|-------------|----------|
| One service, two public methods | createFromSensor + createFromRecognition on one FrasIncidentFactory. | ✓ |
| Two services + shared internal helper | SensorIncidentFactory + RecognitionIncidentFactory + IoTIncidentWriter. | |
| Static factory methods on Incident model | Incident::createFromSensor / createFromRecognition. | |

**User's choice:** One service, two public methods (Recommended).
**Notes:** Matches "single load-bearing integration seam" v2.0 roadmap language; both paths share write logic; single testable service.

### Dedup storage mechanism?

| Option | Description | Selected |
|--------|-------------|----------|
| Redis cache key with TTL | Cache::add atomic; matches Phase 20 AckHandler pattern. | ✓ |
| DB query on recognition_events.incident_id | Audit-clean; costs indexed query + composite index. | |
| Hybrid: cache fallback to DB | Redundant at CDRRMO volume. | |

**User's choice:** Redis cache key with TTL (Recommended).
**Notes:** `Cache::add("fras:incident-dedup:{camera_id}:{personnel_id}", true, ttl)` — atomic, no new index, no cleanup job.

### IoTWebhookController refactor boundary?

| Option | Description | Selected |
|--------|-------------|----------|
| Thin controller: validate + delegate + respond | Factor lines 56–92 into factory. | ✓ |
| Controller calls factory with raw Request | Factory does validation too. | |
| Extract a FormRequest + thin delegate | Most Laravel-idiomatic; adds a class. | |

**User's choice:** Thin controller: validate + delegate + respond (Recommended).
**Notes:** Existing IoTWebhookControllerTest must pass unchanged (RECOGNITION-03 SC3).

### Where do severity/confidence/category/dedup gates live?

| Option | Description | Selected |
|--------|-------------|----------|
| Inside FrasIncidentFactory::createFromRecognition() | Handler always persists + calls factory; factory runs all gates. | ✓ |
| In RecognitionHandler, factory gets pre-gated events | Handler owns gates; factory is pure write. | |
| Dedicated RecognitionGate service between them | Three-class decomposition. | |

**User's choice:** Inside FrasIncidentFactory::createFromRecognition() (Recommended).
**Notes:** All incident-creation logic in one testable service; factory also owns the Warning-severity fras.alerts broadcast.

---

## Map pulse + fras.alerts channel

### Channel wiring for recognition broadcasts?

| Option | Description | Selected |
|--------|-------------|----------|
| New `fras.alerts` channel now | Authorized in Phase 21; Phase 22 consumes. | ✓ |
| Reuse `fras.cameras` for map pulse, defer fras.alerts | Forces Phase 22 rewiring; violates RECOGNITION-05. | |
| Public channel (unauthed) | Violates DPA posture. | |

**User's choice:** New `fras.alerts` channel now (Recommended).
**Notes:** Roles operator/dispatcher/supervisor/admin; responders excluded (DPA role-gating preview).

### RecognitionAlertReceived event payload shape?

| Option | Description | Selected |
|--------|-------------|----------|
| Full denormalized payload | event_id + camera + severity + personnel + confidence + incident_id. | ✓ |
| Slim payload (IDs only) + consumer fetches detail | More HTTP round-trips; fails 50 events/sec load test. | |
| Denormalized but no camera_location | Marginal savings; forces IntakeStation rail to lookup. | |

**User's choice:** Full denormalized payload (Recommended).
**Notes:** Matches IncidentCreated's eager-loaded pattern; zero follow-up HTTP per event.

### Dispatch map pulse animation mechanism?

| Option | Description | Selected |
|--------|-------------|----------|
| Feature-state toggle + Mapbox paint expression | GPU-accelerated; 60fps; glued to feature. | ✓ |
| Extra animated circle layer on top | Simpler paint expression; extra layer. | |
| DOM marker overlay with CSS animation | DOM markers don't stay glued under zoom/pan. | |

**User's choice:** Feature-state toggle + Mapbox paint expression (Recommended).
**Notes:** `map.setFeatureState({source:'cameras', id:cameraId}, {pulsing:true})` + paint case-expression; timer clears after N seconds.

### Pulse duration + re-trigger behavior?

| Option | Description | Selected |
|--------|-------------|----------|
| 3s pulse, re-trigger resets timer | Handles bursts gracefully; no flicker. | ✓ |
| 5s pulse, stacked (each alert extends) | Longer visibility; could pulse indefinitely. | |
| 2s pulse, coalesced per-minute | Most conservative; risks missing legitimate second-match. | |

**User's choice:** 3s pulse, re-trigger resets timer (Recommended).
**Notes:** Configurable via `config('fras.recognition.pulse_duration_seconds', 3)`.

---

## IntakeStation rail + Escalate-to-P1

### IntakeStation rail count reconciliation?

| Option | Description | Selected |
|--------|-------------|----------|
| 6 rails: add FRAS as 6th | Keep Voice/SMS/App/IoT/Walk-in; FRAS 6th. Amend "4th" → "6th". | ✓ |
| 5 rails: FRAS replaces Voice | Voice deprecated; affects IncidentChannel enum. | |
| 5 rails: FRAS nests inside IoT rail | Toggle within IoT rail; harder to scan. | |

**User's choice:** 6 rails: add FRAS as 6th alongside Voice (Recommended).
**Notes:** Requires INTEGRATION-03 + ROADMAP SC6 text amendments during planning.

### What drives the FRAS rail's data stream?

| Option | Description | Selected |
|--------|-------------|----------|
| RecognitionEvents (Critical+Warning), click routes to Incident if exists | Matches intake mental model: rails show inbound signals. | ✓ |
| Incidents only (channel=IoT + source=fras_recognition) | Warning events invisible on IntakeStation. | |
| Both, separated by tab within the rail | UX inconsistency with other rails. | |

**User's choice:** RecognitionEvents Critical+Warning (Recommended).
**Notes:** "CREATED INCIDENT" pill + click-through preserves the Incident linkage without forcing different data shape.

### Escalate-to-P1 button placement + scope?

| Option | Description | Selected |
|--------|-------------|----------|
| Dedicated button on incidents/Show.vue for fras_recognition | Conditional on event_data.source === 'fras_recognition' AND priority !== 'P1'. | ✓ |
| New dedicated route + gate `escalate-fras-incident` | Duplicates 90% of override-priority. | |
| Extend override-priority modal with 'Quick: P1' | Available on ALL incidents; less targeted. | |

**User's choice:** Dedicated button on Incident Show for fras_recognition incidents (Recommended).
**Notes:** Hits existing `intake.override-priority` route; audit differentiated via `event_data.trigger = 'fras_escalate_button'`.

### Who can click Escalate-to-P1?

| Option | Description | Selected |
|--------|-------------|----------|
| Reuse override-priority gate: supervisor + admin | v1.0 invariant preserved; dispatchers can't change priority. | ✓ |
| New gate: dispatcher + supervisor + admin | Forces new route + gate. | |
| Operator + supervisor + admin | Narrow scope change. | |

**User's choice:** Reuse override-priority gate: supervisor + admin only (Recommended).
**Notes:** Phase 22+ can widen if operational feedback shows bottleneck.

---

## Claude's Discretion

- Exact icon design for `IntakeIconFras.vue`
- CSS token `--t-ch-fras` color value
- Pulse animation visual tuning (icon scale, color shift, halo, transition curve)
- Rail card visual layout details (thumbnail aspect, chip placement, badge colors)
- Face thumbnail URL strategy (D-20 recommendation: signed route now)
- `FrasIncidentFactory` property caching for `IncidentType::where('code','person_of_interest')`
- Ring-buffer size for FRAS rail Echo subscription (default 50)
- `FrasEventDetailModal.vue` location (components/intake/ vs components/fras/)
- Whether `OverridePriorityRequest` extends inline or via new request class

## Deferred Ideas

- Phase 22 `fras_access_log` audit integration
- Dispatcher gate widening for Escalate-to-P1
- "Promote to Incident" action (Phase 22 owns)
- Rail ring-buffer size tuning
- Pulse animation visual design refinement
- `allow`-category DPA audit logging
- Load-test SC6 automation (50 events/sec/camera)
- RecognitionEvent.incident_id backfill (not performed)
- Severity × category priority map tuning (2-week production review)
- IntakeStation channel order rearrangement
- Warning severity creating Incidents via operator override (Phase 22)
- `trigger` → full audit enum (once more triggers land)
