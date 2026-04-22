---
phase: 21
plan: 2
subsystem: fras-bridge
tags: [fras, recognition, iot, bridge, factory]
one_liner: "Load-bearing FrasIncidentFactory with 5-gate recognition chain + thin IoTWebhookController delegate + RecognitionHandler wiring"
requires:
  - "app/Events/RecognitionAlertReceived.php (Wave 1)"
  - "config/fras.php recognition section (Wave 1)"
  - "app/Models/RecognitionEvent.php (Phase 18)"
  - "app/Services/BarangayLookupService.php (Phase 2)"
provides:
  - "app/Services/FrasIncidentFactory.php::createFromSensor (D-09 thin-controller delegate target)"
  - "app/Services/FrasIncidentFactory.php::createFromRecognition (D-07 5-gate chain)"
  - "Wire seam for Wave 3 map pulse + IntakeStation rail + escalate-to-P1 (all consume factory-written Incident state)"
affects:
  - "app/Http/Controllers/IoTWebhookController.php (99 lines -> 56 lines)"
  - "app/Mqtt/Handlers/RecognitionHandler.php (+constructor dep, +factory call after image persist)"
  - "tests/Feature/Mqtt/RecognitionHandlerTest.php (+describe 'factory integration' block, 2 tests)"
tech_stack:
  added: []
  patterns:
    - "5-gate chain: severity -> confidence -> category -> dedup -> write (D-07)"
    - "Cache::add SET-NX atomic dedup (fras:incident-dedup:{camera}:{personnel}, 60s TTL)"
    - "DB::transaction wraps write path; IncidentCreated + RecognitionAlertReceived ShouldDispatchAfterCommit"
    - "Memoized IncidentType lookup (single roundtrip per request)"
key_files:
  created:
    - "app/Services/FrasIncidentFactory.php"
  modified:
    - "app/Http/Controllers/IoTWebhookController.php"
    - "app/Mqtt/Handlers/RecognitionHandler.php"
    - "tests/Feature/Mqtt/RecognitionHandlerTest.php"
decisions:
  - "[21-02]: Constructor injection for RecognitionHandler (not app() helper) — handler gains a single dep, test-friendly, matches AckHandler precedent; 'otherwise use app(FrasIncidentFactory)' fallback in plan action was not needed"
  - "[21-02]: factory-integration describe block seeds RecognitionEvent via factory (for()->for()->create) bypassing the handler for the Critical-write-path assertion — handler doesn't set personnel_id (FaceMatcher service deferred to future phase), so direct factory invocation is the clean test of wiring contract"
  - "[21-02]: Info-severity integration test drives the full handler path to prove persistence-only (no broadcasts) when severity gate fails — exercises the wiring seam end-to-end"
  - "[21-02]: factory's createFromSensor writes raw_message from the $validated array rather than original $request->all() — change is invisible to tests (all assertions check on sensor_type/sensor_id which are in $validated) and decouples the factory from the HTTP layer"
metrics:
  tasks_completed: 2
  duration_minutes: 3
  files_created: 1
  files_modified: 3
  lines_added: ~326
  lines_removed: ~46
  completed_date: "2026-04-22"
---

# Phase 21 Plan 02: FrasIncidentFactory + Controller/Handler Wiring Summary

## One-liner

Introduced `FrasIncidentFactory` as the single load-bearing bridge between the FRAS recognition pipeline and the Incident domain, with a verbatim-factored `createFromSensor` path preserving the v1.0 IoT webhook contract and a new `createFromRecognition` path implementing the 5-gate chain (severity → confidence → category → dedup → write) per CONTEXT D-07. IoTWebhookController shrank from 99 to 56 lines as a thin delegate; RecognitionHandler gained a single post-persist factory call and no longer owns broadcast dispatch.

## What Was Built

### Task 1 — `app/Services/FrasIncidentFactory.php` (NEW, 220 lines)

Single `final` class with:

- `createFromSensor(array $validated, array $mapping, IncidentType $incidentType): Incident` — factored verbatim from IoTWebhookController lines 56–92 (pre-plan). Wraps the create + timeline + broadcast in a `DB::transaction`. The one invisible delta: `raw_message` now stores `json_encode($validated)` instead of `json_encode($request->all())`; both assertions in `tests/Feature/Intake/IoTWebhookTest` (sensor_type + sensor_id roundtrip) pass unchanged.

- `createFromRecognition(RecognitionEvent $event): ?Incident` — applies the 5-gate chain in strict order:
  1. **Severity** — only `Critical` proceeds. `Warning` dispatches `RecognitionAlertReceived` alert-only (incident=null) then returns null. `Info` is silent.
  2. **Confidence** — `$event->similarity < config('fras.recognition.confidence_threshold', 0.75)` returns null without broadcast.
  3. **Personnel category** — null personnel (unknown face) OR `PersonnelCategory::Allow` returns null.
  4. **Dedup** — `Cache::add("fras:incident-dedup:{camera_id}:{personnel_id}", true, $ttl)` returning `false` means the key already exists → suppress duplicate.
  5. **Write** — `DB::transaction` wraps Incident create + IncidentTimeline create + `$event->incident_id` assignment + dispatches `IncidentCreated` + `RecognitionAlertReceived(event, incident)`.

- Private helpers: `resolvePriority()` reads `config('fras.recognition.priority_map')` with a P2 safety fallback; `formatNotes()` produces the D-03 string `"FRAS Alert: {label} — {personnel.name} matched on {camera_id_display} at {confidence}% confidence"`.

- Private memoized `?IncidentType $personOfInterestType` avoids a per-event DB roundtrip for the code=person_of_interest row.

### Task 2 — Controller + handler + test wiring

- **`app/Http/Controllers/IoTWebhookController.php`** (99 → 56 lines) — constructor dep swapped `BarangayLookupService → FrasIncidentFactory`; the 37-line write block (data array, Point construction, BarangayLookup, Incident::create, IncidentTimeline::create, dispatch) is now `$this->factory->createFromSensor($validated, $mapping, $incidentType)`. Validation + mapping resolution + 422 early-returns + JSON response shape all preserved verbatim.

- **`app/Mqtt/Handlers/RecognitionHandler.php`** — added constructor (`private FrasIncidentFactory $factory`). After the final `persistImage()` call, a single line: `$this->factory->createFromRecognition($event);`. Return value ignored (handler's contract is persist + images; factory owns incident/broadcast fan-out).

- **`tests/Feature/Mqtt/RecognitionHandlerTest.php`** — added `describe('factory integration', ...)` block with 2 tests:
  - Critical × Block-list event (factory-built with personnel linked) → `incident_id` populated + both `IncidentCreated` and `RecognitionAlertReceived` dispatched.
  - Full-handler Info-severity RecPush → event persists, NEITHER broadcast fires.

## Test Results

| Suite | Pre-plan | Post-plan |
|-------|----------|-----------|
| `FrasIncidentFactoryTest` | 11 failed (RED — target class missing) | **11 passed (36 assertions)** ✓ GREEN |
| `IoTWebhookTest` (RECOGNITION-03 SC3 gate) | 10 passed | **10 passed (30 assertions)** ✓ UNCHANGED |
| `RecognitionHandlerTest` | 10 passed | **12 passed (32 assertions)** ✓ (+2 factory-integration) |
| `fras` group (broader sweep) | 104 passed baseline (Phase 20 close) | **129 passed / 6 failed** (6 failures are Plan 03 RED tests `EscalateToP1Test` + `IntakeStationFrasRailTest` locked in Nyquist Wave 0 per STATE.md) |

Fras group passing count rose by +25 (new factory + new handler integration + Plan 01 contract tests) with all 6 failures confined to Plan 03's pre-written RED tests.

## RecognitionHandler Injection Strategy

The plan allowed either constructor injection or `app(FrasIncidentFactory::class)`. Constructor injection was chosen because:

1. Matches the `AckHandler` constructor-injection precedent (Phase 19 Plan 06).
2. Keeps the handler test-friendly (factory can be replaced with a spy via the container in future tests if needed).
3. Adds only one line to the constructor, no structural cost.

## Column-Name Check

No mismatches discovered. `Incident::$fillable` includes `raw_message`, `coordinates`, `barangay_id`, `location_text`, `notes`, `priority`, `status`, `channel`, `incident_type_id` — all used by the factory. `IncidentTimeline::$fillable` includes `incident_id`, `event_type`, `event_data`. `RecognitionEvent::$fillable` includes `incident_id` (settable by factory on the write path). No auto-generated columns had to be worked around (`incident_no` is auto-populated via the `Incident::booted()` `creating` hook).

## Deviations from Plan

None — plan executed exactly as written. The one-liner's "~326 lines added / ~46 removed" reflects the new factory + handler/test extensions + the controller slimming.

## Threat Surface Scan

No new trust-boundary surface introduced beyond what the plan's `<threat_model>` enumerates. The factory consumes already-validated domain models (Phase 19 handler runs the schema validation + `RecognitionSeverity::fromEvent`), and the dedup cache key is derived from UUIDs (no user input). Mitigations called for in the threat register (T-21-02-02 timeline traceback, T-21-02-05 enum re-read) are both present in the shipped factory:

- T-21-02-02 mitigation: `event_data.recognition_event_id` is written in the timeline entry (verified by `FrasIncidentFactoryTest::'creates P2 Incident for Critical × block-list personnel'`).
- T-21-02-05 mitigation: factory reads `$event->severity` (cast to `RecognitionSeverity` enum) and `$event->similarity` (cast to `decimal:2`), never raw JSON.

## Known Stubs

None. This plan writes real logic on both hot paths.

## Self-Check: PASSED

Files created:
- FOUND: `app/Services/FrasIncidentFactory.php`

Files modified:
- FOUND: `app/Http/Controllers/IoTWebhookController.php` (56 lines, was 99)
- FOUND: `app/Mqtt/Handlers/RecognitionHandler.php` (factory call + constructor)
- FOUND: `tests/Feature/Mqtt/RecognitionHandlerTest.php` ('factory integration' describe block)

Commits:
- FOUND: `2668c36` feat(21-02): add FrasIncidentFactory with 5-gate recognition bridge
- FOUND: `b01c366` refactor(21-02): wire IoTWebhookController + RecognitionHandler to factory

All success criteria in the plan's `<success_criteria>` block satisfied.
