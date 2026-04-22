---
phase: 20-camera-personnel-admin-enrollment
plan: 06
subsystem: fras-scheduled-commands
tags: [fras, scheduler, watchdog, expire-sweep, console, mqtt]
requirements: [CAMERA-05, PERSONNEL-06]
dependency_graph:
  requires:
    - plan-20-01 (Camera model, CameraStatus enum, CameraStatusChanged event, config/fras.cameras.*)
    - plan-20-02 (CameraEnrollmentService::deleteFromAllCameras)
    - plan-20-03 (CameraEnrollment status enum / row semantics)
  provides:
    - App\Console\Commands\CameraWatchdogCommand (irms:camera-watchdog — everyMinute)
    - App\Console\Commands\PersonnelExpireSweepCommand (irms:personnel-expire-sweep — hourly)
    - routes/console.php Schedule::command registrations for both
    - config/database.php pgsql 'timezone' => 'UTC' (fixes pre-existing TIMESTAMPTZ skew)
  affects:
    - plan-20-07 (dispatch map camera layer consumes CameraStatusChanged broadcasts driven by this watchdog)
    - Phase 22 (DPA audit table can replace the mqtt-channel fras.personnel.expired log when persistence is formalised)
tech-stack:
  added: []
  patterns:
    - State-machine transition-only dispatch (guard: $camera->status !== $newStatus) — mitigates T-20-06-01 broadcast storm
    - PG session timezone pinned to UTC via database.php 'timezone' key — closes pre-existing Eloquent Y-m-d H:i:s / Asia/Manila session skew
    - Schedule::command(...)->withoutOverlapping() for both commands — mitigates T-20-06-06 concurrent-tick row modification
    - Cache TTL parity with ack_timeout_minutes — carried over from plan-20-02 enrollment path (no new config keys)
key-files:
  created:
    - app/Console/Commands/CameraWatchdogCommand.php
    - app/Console/Commands/PersonnelExpireSweepCommand.php
    - tests/Feature/Fras/CameraWatchdogTest.php
    - tests/Feature/Fras/PersonnelExpireSweepTest.php
    - .planning/phases/20-camera-personnel-admin-enrollment/deferred-items.md
  modified:
    - routes/console.php (Schedule::command registrations for both new commands after existing mqtt-listener-watchdog)
    - config/database.php (pgsql 'timezone' => 'UTC' — pre-existing gap; see Deviations)
decisions:
  - "20-06-D1: Pinned pgsql session timezone to UTC via config/database.php 'timezone' => env('DB_TIMEZONE', 'UTC') — pre-existing PG Asia/Manila session reinterpretation of Eloquent's Y-m-d H:i:s strings produced an 8hr skew between last_seen_at writes and watchdog reads. Documented in tests/Feature/Mqtt/HeartbeatHandlerTest.php L25-28 as 'pre-existing config gap tracked for later'. Plan 20-06 cannot function correctly without the fix — watchdog would mark every heartbeated camera as Offline."
  - "20-06-D2: Used Carbon::parse('...', 'UTC') + Carbon::setTestNow(Carbon) instead of the plan's literal string form — with 'timezone' => 'UTC' on the pgsql connection the literal form would also work, but explicit UTC parse is robust to future database.php changes and eliminates a class of timezone-coupled test flakes."
  - "20-06-D3: CameraEnrollment row transition in PersonnelExpireSweepCommand uses bulk CameraEnrollment::where(...)->update(['status' => Done]) rather than per-row + broadcast. FRAS has no ACK for delete operations (D-14), so there is no EnrollmentProgressed to dispatch — the state flip is purely bookkeeping. Broadcast noise would be 0-value (the personnel row is already decommissioned and will disappear from admin UI via scopeActive)."
  - "20-06-D4: Log::channel('mqtt')->info('fras.personnel.expired', ...) chosen over a dedicated expire channel — reuses the existing mqtt daily log file (config/logging.php line 76). Phase 22 DPA audit will formalize persistent storage; this plan writes to the same channel FRAS uses for every other lifecycle event, keeping a single audit trail."
  - "20-06-D5: Watchdog uses $now->diffInSeconds($camera->last_seen_at, absolute: true) to collapse sign — covers the edge case where system clock skew makes last_seen_at > now() briefly during ops (would otherwise produce a large negative diff and mis-rank). absolute: true is the Carbon 2 signature; already in use in FrasMqttListenerWatchdogCommand L31."
metrics:
  duration: ~22 minutes
  completed_date: 2026-04-21
  tasks_completed: 2
  files_created: 4
  files_modified: 2
  tests_added: 11 (6 watchdog + 5 expire-sweep)
  assertions_added: 21
---

# Phase 20 Plan 06: Scheduled Commands — Summary

Shipped the two self-maintenance commands closing CAMERA-05 and PERSONNEL-06. `irms:camera-watchdog` runs every minute and transitions camera rows between Online/Degraded/Offline based on heartbeat gap, dispatching `CameraStatusChanged` only on transition. `irms:personnel-expire-sweep` runs hourly and unenrolls personnel whose `expires_at` has passed, publishing `DeletePersons` MQTT per online camera and soft-decommissioning the row. Both registered via `Schedule::command(...)->withoutOverlapping()` in `routes/console.php`.

Full fras group: **104 passed (306 assertions) in 4.93s**. No regressions vs baseline (99 passed before this plan).

## Requirements Addressed

- **CAMERA-05** — Camera lifecycle state machine: online (gap ≤30s) / degraded (≤90s) / offline (>90s), broadcasts on transition
- **PERSONNEL-06** — BOLO watch-list expiry sweep: decommission + MQTT delete + enrollment cleanup

## Task 1: CameraWatchdogCommand (commit 5bfd601)

Single-artifact command that scans `Camera::whereNull('decommissioned_at')->get()` each tick:

| Gap (seconds) | New Status | Source of thresholds |
|---|---|---|
| ≤ 30 | Online | `config('fras.cameras.degraded_gap_s')` (default 30) |
| 31-90 | Degraded | same + `config('fras.cameras.offline_gap_s')` (default 90) |
| > 90 | Offline | offline_gap_s ceiling |
| null `last_seen_at` | Offline | `PHP_INT_MAX` sentinel |

Transition-only dispatch: `if ($camera->status !== $newStatus) { update + CameraStatusChanged::dispatch($camera->fresh()); }`. Steady-state is silent — 3 Online cameras at t=10:00:10 (gap=10s, still Online) produce 0 broadcasts. Covered by the 6th test case.

### Tests (tests/Feature/Fras/CameraWatchdogTest.php — 6 cases, 11 assertions)

1. **Below threshold — steady Online** (gap=20s, status unchanged, 0 broadcasts)
2. **Online → Degraded** (gap=45s, status flipped, 1 broadcast matching payload)
3. **Degraded → Offline** (gap=120s, status flipped, 1 broadcast)
4. **null `last_seen_at`** — treated as Offline (never-heartbeated camera)
5. **Decommissioned skipped** — `last_seen_at = now()->subSeconds(200)` but `decommissioned_at = 1 day ago`, status stays Online, 0 broadcasts
6. **Steady state silent** — 3 Online cameras at gap=10s produce 0 broadcasts

### Time handling

Uses `Carbon::setTestNow(Carbon::parse('...', 'UTC'))` to pin the test clock in UTC. Required because of the PG session timezone fix (see Deviations — without it, Eloquent's `Y-m-d H:i:s` serialisation was reinterpreted by PG as Asia/Manila local time, adding an 8hr skew to every `last_seen_at` round-trip).

## Task 2: PersonnelExpireSweepCommand + Schedule registrations (commit 72136f6)

Hourly sweep with four responsibilities:

1. Query `Personnel::whereNotNull('expires_at')->where('expires_at', '<', now())->whereNull('decommissioned_at')->get()`
2. For each: `CameraEnrollmentService::deleteFromAllCameras($personnel)` (publishes DeletePersons MQTT per online camera)
3. `$personnel->update(['decommissioned_at' => now()])`
4. `CameraEnrollment::where('personnel_id', $personnel->id)->update(['status' => Done])` (bookkeeping flip — Done represents successful unenroll since FRAS has no delete ACK per D-14)
5. `Log::channel('mqtt')->info('fras.personnel.expired', ...)` — audit trail on the existing daily mqtt log

### Schedule registration

`routes/console.php` — appended two `Schedule::command` calls after the existing `irms:mqtt-listener-watchdog` registration:

```php
Schedule::command('irms:camera-watchdog')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Flip camera status between online/degraded/offline based on heartbeat gap');

Schedule::command('irms:personnel-expire-sweep')
    ->hourly()
    ->withoutOverlapping()
    ->description('Unenroll personnel whose BOLO expiry has passed');
```

`php artisan schedule:list` confirms both entries:

```
* *  *  * *      php artisan irms:camera-watchdog  ...
0 *  *  * *      php artisan irms:personnel-expire-sweep  ...
```

### Tests (tests/Feature/Fras/PersonnelExpireSweepTest.php — 5 cases, 10 assertions)

1. **Expired unenroll + decommission** — `expires_at = now()->subHour()`, 2 online cameras → MQTT publish count ≥2, decommissioned_at set
2. **Enrollment rows → Done** — pre-existing Syncing row flips to Done after sweep
3. **Future expires_at untouched** — `expires_at = now()->addDays(30)` → 0 MQTT, decommissioned_at still null
4. **Null expires_at untouched** — permanent watch-list entry → 0 MQTT, decommissioned_at still null
5. **Already decommissioned skipped** — `decommissioned_at = yesterday` → `MQTT::shouldReceive->never()` + decommissioned_at timestamp unchanged

Tests use `MQTT::shouldReceive('connection->publish')` with `->zeroOrMoreTimes()` / `->atLeast()` / `->never()` cardinality variants — php-mqtt does not ship a `::fake()` method, Mockery is the only path.

## Deviations from Plan

**1. [Rule 3 — Blocking] PG session timezone fix (config/database.php)**

- **Found during:** Task 1 GREEN phase (3 of 6 tests flipping to Offline regardless of gap)
- **Issue:** Eloquent's default INSERT serialisation for `datetime` columns uses `Y-m-d H:i:s` with no timezone suffix. With PG session TZ = Asia/Manila (the environment default), PG reinterprets the Eloquent-written string as Manila local time, shifting the stored UTC instant by 8 hours. On read, Carbon hydrates a `+08:00`-flagged instant that is 8h earlier than the write-time UTC. The watchdog's `now()->diffInSeconds($stored, absolute: true)` returned 28820s+ for every camera, flipping all to Offline.
- **Fix:** Added `'timezone' => env('DB_TIMEZONE', 'UTC')` to the pgsql connection in `config/database.php`. This issues `SET time zone 'UTC'` on each connection, making PG interpret Eloquent's no-TZ strings as UTC. Verified pre-existing (documented in `tests/Feature/Mqtt/HeartbeatHandlerTest.php` L25-28 as "pre-existing config gap tracked for later"). Applied as Rule 3 — this plan cannot function correctly without it, because watchdog threshold semantics rely on last_seen_at round-tripping the same UTC instant.
- **Files modified:** `config/database.php` (lines 100-105)
- **Commit:** 5bfd601

**2. [Out of scope — logged to deferred-items.md] Pre-existing MQTT AckHandlerTest failures**

- **Found during:** Post-Task-1 regression check via `php artisan test --compact tests/Feature/Mqtt/`
- **Issue:** 2 cases in `tests/Feature/Mqtt/AckHandlerTest.php` fail with `BadMethodCallException: Received Mockery_n_Psr_Log_LoggerInterface::warning(), but no expectations were specified`. The Phase 19 scaffold's test assumed info-only logging, but Plan 20-03 rewrote `AckHandler::handle()` to emit `warning` on unknown-camera paths. The `ack.json` fixture exercises exactly that path.
- **Verified pre-existing** via `git stash && php artisan test tests/Feature/Mqtt/AckHandlerTest.php` on base commit 29af187 — same 2 failures.
- **Out of scope** for Plan 20-06. Logged to `.planning/phases/20-camera-personnel-admin-enrollment/deferred-items.md` for a later cleanup pass (Plan 20-03 should have updated these legacy MQTT-group tests alongside its fras-group rewrite).

## Output Section Questions Answered

1. **Default thresholds (30s / 90s) matched config?** ✅ Yes — `config('fras.cameras.degraded_gap_s', 30)` and `config('fras.cameras.offline_gap_s', 90)` are read from `config/fras.php` which defaults both to 30/90 per `FRAS_CAMERA_DEGRADED_GAP_S` / `FRAS_CAMERA_OFFLINE_GAP_S` env vars (plan 20-01 authored these). Test cases at gap=20s / 45s / 120s prove the three zones.

2. **Transition-only dispatch confirmed?** ✅ Yes — the `if ($camera->status !== $newStatus)` guard is the only path that reaches `CameraStatusChanged::dispatch`. Test 6 explicitly creates 3 Online cameras and asserts `Event::assertNotDispatched(CameraStatusChanged::class)` after the watchdog runs at gap=10s. T-20-06-01 broadcast storm mitigation verified.

3. **`Carbon::setTestNow` vs `freezeTime()`?** Used `Carbon::setTestNow(Carbon::parse('...', 'UTC'))` per Phase 17 learning — `freezeTime()` pins to test-start Carbon::now() which varies run-to-run; `setTestNow` with an explicit UTC parse is byte-deterministic.

4. **`MQTT::fake()` available?** ❌ No — php-mqtt does not ship a `::fake()` method. Tests use `MQTT::shouldReceive('connection->publish')` with Mockery cardinality variants (`->zeroOrMoreTimes()`, `->atLeast()`, `->never()`). This mirrors the pattern already established in `CameraEnrollmentServiceTest` (plan 20-02) and `PersonnelObserverTest` (plan 20-03).

5. **Schedule conflicts with existing registrations?** None. Only `irms:mqtt-listener-watchdog` (everyThirtySeconds) was in `routes/console.php` pre-plan. New registrations are appended; `php artisan schedule:list` shows all three co-existing cleanly.

## Verification

- `php artisan test --compact tests/Feature/Fras/CameraWatchdogTest.php` → **6 passed, 11 assertions** in 0.81s
- `php artisan test --compact tests/Feature/Fras/PersonnelExpireSweepTest.php` → **5 passed, 10 assertions** in 0.79s
- `php artisan test --compact --group=fras` → **104 passed, 306 assertions** in 4.93s (was 99 passed before; +5 new, no regressions)
- `php artisan list | grep 'irms:camera-watchdog'` → 1 match (`Flip camera status between online/degraded/offline...`)
- `php artisan schedule:list | grep -E 'irms:camera-watchdog|irms:personnel-expire-sweep'` → 2 matches (everyMinute + hourly)
- `vendor/bin/pint --dirty --format agent` → pass (one `no_blank_lines_after_phpdoc` fix on CameraWatchdogTest)
- Grep `rg -n "config\('fras\.cameras\.degraded_gap_s'" app/Console/Commands/CameraWatchdogCommand.php` → 1 match
- Grep `rg -n '\\$camera->status !== \\$newStatus' app/Console/Commands/CameraWatchdogCommand.php` → 1 match
- Grep `rg -n 'CameraStatusChanged::dispatch' app/Console/Commands/CameraWatchdogCommand.php` → 1 match
- Grep `rg -n "Schedule::command\\('irms:camera-watchdog'\\)->everyMinute\\(\\)" routes/console.php` → 1 match
- Grep `rg -n "Schedule::command\\('irms:personnel-expire-sweep'\\)->hourly\\(\\)" routes/console.php` → 1 match
- Grep `rg -n 'deleteFromAllCameras' app/Console/Commands/PersonnelExpireSweepCommand.php` → 1 match
- Grep `rg -n "Log::channel\\('mqtt'\\)->info\\('fras\\.personnel\\.expired'" app/Console/Commands/PersonnelExpireSweepCommand.php` → 1 match

## Commits

| Task | Hash | Summary |
|---|---|---|
| 1 | 5bfd601 | feat(20-06): add CameraWatchdogCommand with transition-only dispatch (+ pgsql UTC fix) |
| 2 | 72136f6 | feat(20-06): add PersonnelExpireSweepCommand + register both schedules |

## Self-Check: PASSED

**Files verified present:**
- `app/Console/Commands/CameraWatchdogCommand.php` — FOUND
- `app/Console/Commands/PersonnelExpireSweepCommand.php` — FOUND
- `tests/Feature/Fras/CameraWatchdogTest.php` — FOUND
- `tests/Feature/Fras/PersonnelExpireSweepTest.php` — FOUND
- `routes/console.php` (modified) — FOUND
- `config/database.php` (modified) — FOUND
- `.planning/phases/20-camera-personnel-admin-enrollment/deferred-items.md` — FOUND
- `.planning/phases/20-camera-personnel-admin-enrollment/20-06-SUMMARY.md` — FOUND (this file)

**Commits verified in git log:**
- 5bfd601 (Task 1) — FOUND
- 72136f6 (Task 2) — FOUND
