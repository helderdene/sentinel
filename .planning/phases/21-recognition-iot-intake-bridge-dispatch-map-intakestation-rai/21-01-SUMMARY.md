---
phase: 21
plan: 1
subsystem: fras-bridge
tags:
  - nyquist-wave-0
  - broadcast-event
  - config
  - channel-auth
  - seeder
  - doc-amendment
requires: []
provides:
  - tests/Feature/Fras/FrasIncidentFactoryTest.php
  - tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php
  - tests/Feature/Fras/EscalateToP1Test.php
  - tests/Feature/Fras/IntakeStationFrasRailTest.php
  - App\Events\RecognitionAlertReceived
  - config('fras.recognition')
  - PrivateChannel('fras.alerts') gate
  - IncidentType code=person_of_interest
affects:
  - database/seeders/DatabaseSeeder.php
  - .planning/REQUIREMENTS.md (RECOGNITION-02, INTEGRATION-03)
tech_stack:
  added: []
  patterns:
    - ShouldBroadcast + ShouldDispatchAfterCommit + 11-key denorm broadcastWith()
    - pest()->group('fras') for subsystem-scoped test runs
    - updateOrCreate keyed on code for idempotent seeders
    - $dispatchRoles shared role set across fras.* channels
key_files:
  created:
    - tests/Feature/Fras/FrasIncidentFactoryTest.php
    - tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php
    - tests/Feature/Fras/EscalateToP1Test.php
    - tests/Feature/Fras/IntakeStationFrasRailTest.php
    - app/Events/RecognitionAlertReceived.php
    - database/seeders/PersonOfInterestIncidentTypeSeeder.php
  modified:
    - config/fras.php
    - routes/channels.php
    - database/seeders/DatabaseSeeder.php
    - .planning/REQUIREMENTS.md
decisions:
  - "[21-01]: person_of_interest seeder routes under IncidentCategory name='Crime / Security' (exact shipped spelling including spaces around slash), not 'Crime' as the plan skeleton suggested — dynamic lookup by name keeps seeder robust to category re-ordering"
  - "[21-01]: Dedup test pre-seeds Cache::add with the canonical factory key shape fras:incident-dedup:{camera_id}:{personnel_id} rather than mocking Cache — tests the real atomic contract under array driver, which Phase 20 already proved holds dedup semantics for FRAS"
  - "[21-01]: RecognitionEvent factory state composition uses ->for(Camera) ->for(Personnel) ->create([severity, similarity]) — Eloquent's polymorphic BelongsTo resolution on ->for() matches our fillable shape without having to pre-resolve FKs"
  - "[21-01]: FrasIncidentFactoryTest seeds the person_of_interest IncidentType inline in beforeEach rather than calling the seeder — RefreshDatabase does not run the full seeder chain in tests, and explicit inline setup is the idiomatic Pest pattern"
  - "[21-01]: Route name for escalate POST is intake.override-priority (confirmed by TriageIncidentTest usage on existing route); no new route added in this plan — Plan 03 extends the validation rules only"
  - "[21-01]: RecognitionAlertReceived payload test uses expect(...)->toHaveKeys(...) instead of asserting exact array equality — preserves future additive field migrations without breaking existing consumers; exact-value assertions live on the individual keys downstream"
metrics:
  duration: 13min
  completed_date: 2026-04-22
---

# Phase 21 Plan 1: Nyquist Wave 0 Test Scaffolding + RecognitionAlertReceived Contracts Summary

**One-liner:** Locked every downstream contract (4 failing-by-design Pest test files under `tests/Feature/Fras/`, `App\Events\RecognitionAlertReceived` broadcast class on `fras.alerts` private channel, `config/fras.php#recognition` section with confidence/dedup/priority knobs, idempotent `PersonOfInterestIncidentTypeSeeder`, and REQUIREMENTS.md text amendments D-26/D-27) so Wave 2 executors can reference finalized shapes without speculation.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Nyquist Wave 0 test scaffolding + person_of_interest seeder + DatabaseSeeder registration | 6909a70 | 6 files (4 new tests + 1 new seeder + DatabaseSeeder) |
| 2 | RecognitionAlertReceived event + fras.alerts channel + config recognition section + REQUIREMENTS amendments | 223900b | 4 files (1 new event + 3 mods) |

## Final RED Gate State (Expected — Nyquist Validation)

`php artisan test --compact --filter=Fras` after Plan 01 completion:

```
Tests: 17 failed, 90 passed (281 assertions)
```

### Failure breakdown (all expected; each closes in a later plan)

| Count | Failure Family | Closes In |
|-------|----------------|-----------|
| 11 | `Tests\Feature\Fras\FrasIncidentFactoryTest` — `BindingResolutionException: Target class [App\Services\FrasIncidentFactory] does not exist` | Plan 02 (FrasIncidentFactory implementation) |
| 3 | `Tests\Feature\Fras\EscalateToP1Test` — 422 validation failure on `trigger` field / missing manual_override default | Plan 03 (IntakeStationController::overridePriority trigger rule) |
| 3 | `Tests\Feature\Fras\IntakeStationFrasRailTest` — missing Inertia prop `recentFrasEvents` / `frasConfig.pulseDurationSeconds` | Plan 03 (IntakeStationController prop + HandleInertiaRequests share) |

### Green now (previously failing)

- `RecognitionAlertReceivedBroadcastTest`: 7/7 pass — payload shape (2 tests) + channel auth matrix (4 allowed roles + 1 responder denied)

## Artifacts Shipped

### Tests (`tests/Feature/Fras/`)

| File | Contract Locked |
|------|-----------------|
| `FrasIncidentFactoryTest.php` | 5 gates × 2 methods + payload + dedup + config-override + sensor path (11 `it()` blocks, D-06..D-08) |
| `RecognitionAlertReceivedBroadcastTest.php` | D-12 11-key denorm payload + 5-row fras.alerts channel auth matrix (7 `it()` blocks) |
| `EscalateToP1Test.php` | Supervisor escalate with trigger field + manual_override default + 422 on invalid trigger + dispatcher 403 (4 `it()` blocks, D-22) |
| `IntakeStationFrasRailTest.php` | `recentFrasEvents` prop shape (10 sub-keys) + empty-array baseline + `frasConfig.pulseDurationSeconds` shared prop (3 `it()` blocks, D-18) |

All 4 files declare `pest()->group('fras')` per FRAS-test convention (Plan 18-05 introduced).

### Event + Config + Channel

- `app/Events/RecognitionAlertReceived.php` — `final class` implementing `ShouldBroadcast + ShouldDispatchAfterCommit`, `PrivateChannel('fras.alerts')`, constructor `(public RecognitionEvent $event, public ?Incident $incident = null)`. `broadcastWith()` returns the exact 11-key denorm shape required by D-12.
- `config/fras.php#recognition` — 4 keys: `confidence_threshold=0.75`, `dedup_window_seconds=60`, `pulse_duration_seconds=3`, `priority_map.critical.{block=P2,missing=P2,lost_child=P1}`. All env-overridable per RECOGNITION-08. Verified via `php artisan config:show fras.recognition`.
- `routes/channels.php` — `Broadcast::channel('fras.alerts', fn (User $user) => in_array($user->role, $dispatchRoles))` placed directly after `fras.cameras`. Uses the existing `$dispatchRoles` variable (Operator/Dispatcher/Supervisor/Admin). Responders stay off the channel.

### Seeder

- `database/seeders/PersonOfInterestIncidentTypeSeeder.php` — `updateOrCreate(['code' => 'person_of_interest'], [...])`. Registered in `DatabaseSeeder::run()` directly after `IncidentTypeSeeder::class` so the `Crime / Security` IncidentCategory row exists for the FK lookup.
- Idempotency proven: ran the seeder twice, `IncidentType::where('code','person_of_interest')->count()` returns **1**.

### Documentation amendments

- `.planning/REQUIREMENTS.md` RECOGNITION-02 — added "against BOLO personnel (block / missing / lost_child categories; `allow` excluded)" clause per D-26.
- `.planning/REQUIREMENTS.md` INTEGRATION-03 — changed "4th channel rail" → "6th channel rail" and "SMS/App/IoT/Walk-in" → "Voice / SMS / App / IoT / Walk-in" per D-27.
- `.planning/ROADMAP.md` SC1 + SC6 already reflected the D-25/D-27 amendments (the ROADMAP was updated during planning; the belt-and-suspenders edit from the plan was a no-op).

## Discretionary Calls Logged

1. **IncidentCategory lookup name:** used `where('name', 'Crime / Security')` matching the shipped seeder string verbatim (the plan skeleton suggested plain `'Crime'`). Falling back to a null `incident_category_id` when the category row does not exist, rather than aborting — tests can still assert core attributes without a mandatory category FK.
2. **Seeder field shape:** matched `IncidentTypeSeeder` conventions exactly (`is_active` not `active`, `category` string column populated parallel to `incident_category_id` FK, `default_priority` as P2 string). These field shapes are enforced by the `IncidentType` fillable list and the shipped schema.
3. **Test isolation strategy:** `FrasIncidentFactoryTest::beforeEach` inline-seeds the `person_of_interest` IncidentType using the same `updateOrCreate` shape as the production seeder — keeps tests independent of seeder chain wiring and removes any RefreshDatabase/seeder ordering fragility.
4. **SUMMARY did not re-edit ROADMAP SC lines:** the SC1 text ("block-list, missing, or lost-child personnel") and SC6 text ("6th channel rail") were already in place in `.planning/ROADMAP.md` — the plan's D-25/D-27 edits landed pre-execution during planning. Task 2 verified via grep and made no further change to those lines.

## Deviations from Plan

**None** — plan executed exactly as written. No auto-fixes (Rule 1-3) were applied; no architectural change was required (Rule 4). The only friction point was the `IncidentCategory` name spelling (`'Crime / Security'` not `'Crime'`), resolved inline during seeder authoring without deviation — both the plan skeleton and the shipped codebase were inspected, shipped spelling was used.

## DatabaseSeeder Call Order

```php
$this->call([
    AdminUserSeeder::class,
    OperatorUserSeeder::class,
    IncidentTypeSeeder::class,              // seeds IncidentCategory 'Crime / Security'
    PersonOfInterestIncidentTypeSeeder::class,  // NEW — FKs to the category above
    ChecklistTemplateSeeder::class,
    UnitSeeder::class,
    BarangaySeeder::class,
    AgencySeeder::class,
    IncidentSeeder::class,
]);
```

Order matters: `IncidentTypeSeeder` runs first to create the `Crime / Security` IncidentCategory row that `PersonOfInterestIncidentTypeSeeder` FKs against.

## Self-Check: PASSED

**Files exist:**
- FOUND: tests/Feature/Fras/FrasIncidentFactoryTest.php
- FOUND: tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php
- FOUND: tests/Feature/Fras/EscalateToP1Test.php
- FOUND: tests/Feature/Fras/IntakeStationFrasRailTest.php
- FOUND: app/Events/RecognitionAlertReceived.php
- FOUND: config/fras.php (recognition section)
- FOUND: routes/channels.php (fras.alerts gate)
- FOUND: database/seeders/PersonOfInterestIncidentTypeSeeder.php

**Commits exist:**
- FOUND: 6909a70 (Task 1)
- FOUND: 223900b (Task 2)

**Contract grep checks:**
- FOUND: `PrivateChannel('fras.alerts')` in app/Events/RecognitionAlertReceived.php
- FOUND: `ShouldDispatchAfterCommit` in app/Events/RecognitionAlertReceived.php
- FOUND: `'recognition' =>` in config/fras.php
- FOUND: `FRAS_CONFIDENCE_THRESHOLD` + `FRAS_DEDUP_WINDOW_SECONDS` in config/fras.php
- FOUND: `fras.alerts` in routes/channels.php
- FOUND: `BOLO personnel` + `6th channel rail` in REQUIREMENTS.md
- FOUND: `block-list, missing, or lost-child` + `6th channel rail` in ROADMAP.md

**Config resolution verified:** `php artisan config:show fras.recognition.confidence_threshold` returns `0.75`.

**Seeder idempotency verified:** Two sequential runs of `php artisan db:seed --class=Database\\Seeders\\PersonOfInterestIncidentTypeSeeder` produce `IncidentType::where('code','person_of_interest')->count() === 1`.

**Broadcast test greens verified:** `php artisan test --compact --filter=RecognitionAlertReceivedBroadcastTest` — 7 passed, 31 assertions.
