---
phase: 19
plan: 04
subsystem: mqtt-pipeline-listener-infrastructure
tags: [mqtt, listener, watchdog, broadcast, schedule, supervisor, wave-3]
requires:
  - MQTT-01
  - MQTT-03
  - MQTT-04
  - MQTT-05
  - 19-01 (config/fras.php topic_prefix, mqtt log channel, subscriber connection)
  - 19-02 (TopicRouter)
provides:
  - App\Events\MqttListenerHealthChanged (ShouldBroadcast + ShouldDispatchAfterCommit on PrivateChannel('dispatch.incidents'))
  - App\Console\Commands\FrasMqttListenCommand (irms:mqtt-listen {--max-time=3600})
  - App\Console\Commands\FrasMqttListenerWatchdogCommand (irms:mqtt-listener-watchdog)
  - Schedule::command('irms:mqtt-listener-watchdog')->everyThirtySeconds()
  - Cache keys: mqtt:listener:last_known_state, mqtt:listener:last_state_since
affects:
  - routes/console.php (append-only — new Schedule::command entry)
tech-stack:
  added: []
  patterns:
    - Broadcast event reuses existing dispatch.incidents channel auth (routes/channels.php unchanged per D-10)
    - pcntl_signal(SIGTERM|SIGINT|SIGALRM) → $mqtt->interrupt() for clean Supervisor stop + hourly max-time rotation (MQTT-04)
    - Watchdog transition guard — dispatch event only on HEALTHY↔SILENT↔NO_ACTIVE_CAMERAS state changes (no re-broadcast on steady state per D-07)
    - MQTT facade mocked via Mockery::mock(MqttClient::class) + MQTT::shouldReceive('connection') for broker-free exit-code harness
key-files:
  created:
    - app/Events/MqttListenerHealthChanged.php
    - app/Console/Commands/FrasMqttListenCommand.php
    - app/Console/Commands/FrasMqttListenerWatchdogCommand.php
    - tests/Feature/Mqtt/MqttListenerHealthChangedTest.php
    - tests/Feature/Mqtt/MqttListenerWatchdogTest.php
    - tests/Feature/Mqtt/ListenerCommandRegistrationTest.php
    - tests/Feature/Mqtt/ListenerCommandMaxTimeTest.php
  modified:
    - routes/console.php
decisions:
  - [19-04]: Watchdog uses `now()->diffInSeconds(Carbon::parse($lastMessageAt), true)` with the absolute-value flag — without it a future-dated Carbon returns a negative gap, and `-100 < 90` would incorrectly classify SILENT as HEALTHY during clock skew. The `true` argument forces a positive gap and matches D-07's "gap" semantics.
  - [19-04]: Schedule registration test switched from `$this->artisan('schedule:list')->assertSuccessful()` to `Artisan::call('schedule:list') + Artisan::output()`. The Pest PendingCommand helper's own output buffer is separate from the Artisan facade's, and `Artisan::output()` is empty after a `$this->artisan(...)` call — `Artisan::call(...)` populates the facade buffer so the string assertion works.
  - [19-04]: Test files follow project convention `pest()->group('mqtt');` (no `pest()->extend(TestCase::class)`) because `tests/Pest.php` already binds `TestCase` + `RefreshDatabase` via `->in('Feature')`. Adding `->extend(TestCase::class)` in a test file triggers Pest's "test case already in use" error from the main tree context (Wave 2 regression).
metrics:
  duration: ~18min
  completed: "2026-04-21"
  tasks: 3
  files_created: 7
  files_modified: 1
  commits: 3
---

# Phase 19 Plan 04: Listener Command + Watchdog + Broadcast Event Summary

One-liner: Shipped `irms:mqtt-listen` (subscribes to 4 topic patterns, routes via TopicRouter, supports SIGTERM/SIGINT/SIGALRM-driven exit via `--max-time=3600` for MQTT-04 hourly rotation), `irms:mqtt-listener-watchdog` (scheduled every 30s, computes HEALTHY/SILENT/NO_ACTIVE_CAMERAS state, dispatches MqttListenerHealthChanged only on transitions per D-07), and the broadcast event itself on the pre-authorized `dispatch.incidents` channel (D-10, no channel auth edits).

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | MqttListenerHealthChanged event + FrasMqttListenCommand + broadcast-shape test | d45101a | app/Events/MqttListenerHealthChanged.php, app/Console/Commands/FrasMqttListenCommand.php, tests/Feature/Mqtt/MqttListenerHealthChangedTest.php |
| 2 | FrasMqttListenerWatchdogCommand + 30s schedule + state-transition tests | 594744c | app/Console/Commands/FrasMqttListenerWatchdogCommand.php, routes/console.php, tests/Feature/Mqtt/MqttListenerWatchdogTest.php |
| 3 | Artisan registration test + MQTT-04 --max-time exit-code harness | bc6eba5 | tests/Feature/Mqtt/ListenerCommandRegistrationTest.php, tests/Feature/Mqtt/ListenerCommandMaxTimeTest.php |

## Verification Results

- `php artisan test --compact tests/Feature/Mqtt/MqttListenerWatchdogTest.php tests/Feature/Mqtt/MqttListenerHealthChangedTest.php tests/Feature/Mqtt/ListenerCommandRegistrationTest.php tests/Feature/Mqtt/ListenerCommandMaxTimeTest.php` → **19 passed (47 assertions)** in 1.23s
- `php artisan list | grep -E "irms:mqtt-listen|irms:mqtt-listener-watchdog"` → both commands registered
- `php artisan schedule:list` → `* *  *  * * 30s  php artisan irms:mqtt-listener-watchdog  Next Due: N seconds from now`
- `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}` on every Task commit
- Acceptance grep markers (all present):
  - `new PrivateChannel('dispatch.incidents')` in MqttListenerHealthChanged.php (D-10)
  - `ShouldBroadcast, ShouldDispatchAfterCommit` in MqttListenerHealthChanged.php
  - `MQTT::connection('subscriber')` + `max-time=3600` + `pcntl_signal(SIGTERM` + `router->dispatch` + `Log::channel('mqtt')` (×2) in FrasMqttListenCommand.php
  - `whereNull('decommissioned_at')` + `NO_ACTIVE_CAMERAS` + `SILENCE_THRESHOLD_SECONDS = 90` + `MqttListenerHealthChanged::dispatch` + `if ($previous === $state)` in FrasMqttListenerWatchdogCommand.php
  - `everyThirtySeconds` + `irms:mqtt-listener-watchdog` in routes/console.php

## Success Criteria

1. ✓ MQTT-01: `irms:mqtt-listen` registered, subscribes to 4 topic patterns, routes every message through injected TopicRouter
2. ✓ MQTT-04: `--max-time=3600` default + SIGALRM-driven clean exit; programmatic harness with mocked MQTT facade proves exit code 0 within 2.0s for `--max-time=1`; SIGTERM + SIGINT handlers installed for graceful Supervisor stop
3. ✓ MQTT-05: watchdog command + 30s schedule; state transitions HEALTHY↔SILENT↔NO_ACTIVE_CAMERAS dispatch MqttListenerHealthChanged exactly once per change; payload shape locked to D-11 contract by 4 broadcast-shape tests
4. ✓ No new channel auth — routes/channels.php unmodified (D-10 enforced); event reuses the pre-authorized `dispatch.incidents` channel

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Carbon::diffInSeconds absolute-value flag**

- **Found during:** Task 2 first Pest run
- **Issue:** The plan's snippet used `now()->diffInSeconds(Carbon::parse($lastMessageAt))` without the second argument. In Carbon v3 (installed via Laravel 12), `diffInSeconds()` returns a signed integer; when `$lastMessageAt` is in the past, the gap is negative, and `-100 < 90` would incorrectly classify SILENT as HEALTHY. In the plan's snippet this would break Tests 3 and 4 (SILENT transition + missing-cache-key → SILENT) because the sign flips vs. the plan's intent.
- **Fix:** Passed `true` as the second argument: `now()->diffInSeconds(Carbon::parse($lastMessageAt), true)`. This returns the absolute gap, matching the plan's D-07 `gap ≥ 90` semantics.
- **Files modified:** app/Console/Commands/FrasMqttListenerWatchdogCommand.php
- **Commit:** 594744c

**2. [Rule 3 - Blocking] Schedule registration test used wrong Artisan output buffer**

- **Found during:** Task 2 RED→GREEN transition (test 7 failed even after schedule was wired)
- **Issue:** The plan suggested `$this->artisan('schedule:list')->assertSuccessful()` followed by `Artisan::output()`. Pest 4's PendingCommand wraps the command in its own BufferedOutput and calls `assertSuccessful()`; `Artisan::output()` (the facade) is a separate channel and is empty after the helper runs. The assertion `expect($output)->toContain('irms:mqtt-listener-watchdog')` failed against an empty string.
- **Fix:** Switched the test to `Artisan::call('schedule:list')` + `Artisan::output()`, which populates the facade buffer directly. Exit code is now asserted explicitly.
- **Files modified:** tests/Feature/Mqtt/MqttListenerWatchdogTest.php
- **Commit:** 594744c

**3. [Rule 3 - Blocking] composer install in worktree**

- **Found during:** Initial execution setup
- **Issue:** The worktree had no `vendor/` directory. The plan's verification (`php artisan list`, `php artisan test`) cannot run without it.
- **Fix:** Ran `composer install --no-interaction --no-progress --prefer-dist` once at the start of execution. vendor/ + composer.lock are already `.gitignore`d.
- **Files modified:** None (vendor/ gitignored)
- **Commit:** n/a (not a code change)

**4. [Rule 2 - Correctness] touch database/database.sqlite + php artisan migrate --force**

- **Found during:** Attempt to invoke `schedule:list` from CLI for diagnostic
- **Issue:** `withoutOverlapping()` uses the Laravel Cache store which, in this config, defaults to the `database` cache driver. Without the sqlite file + migrations, the CLI inspection of schedule:list hits a QueryException.
- **Fix:** Created `database/database.sqlite` and ran migrations on it. This is local-diagnostic only — Pest Feature tests use the in-memory SQLite via RefreshDatabase and are not affected. The created sqlite file is `.gitignore`d (it does not appear in `git status`).
- **Files modified:** None tracked (sqlite file is gitignored)

### Auth Gates

None — fully autonomous plan.

### Architectural Changes

None.

## TDD Gate Compliance

Task 2 followed a clean RED→GREEN cycle:
- RED: test file written first; initial `php artisan test` run showed **7/7 failing** (`CommandNotFoundException: "The command 'irms:mqtt-listener-watchdog' does not exist."` + missing schedule assertion).
- GREEN: Command class + schedule entry added → **7/7 passing** after the two auto-fixes above.
- No REFACTOR commit — the watchdog implementation is already minimal.

Task 1 and Task 3 are non-TDD in the strict sense:
- Task 1 creates the event class (Data-like) and the listen command together with its broadcast-shape test — they ship in a single commit because the test imports `MqttListenerHealthChanged` and would ParseError without the class.
- Task 3 is a test-only task (`test()` prefix) that exercises existing code from Tasks 1+2.

All three commits use conventional-commit prefixes (`feat(...)`, `feat(...)`, `test(...)`).

## Threat Flags

None new beyond the plan's threat model:

- T-19-01 (broker spoofing) — **transferred** to Mosquitto auth in Plan 19-06 (out of scope here)
- T-19-04 (log injection) — **mitigated** (structured `['topics' => $topics, 'max_time' => ...]` array context, no interpolated strings on Log::channel('mqtt') calls)
- T-19-05 (broadcast info disclosure) — **accepted** (pre-existing `dispatch.incidents` channel auth in routes/channels.php lines 9-11 already enforces operator|dispatcher|supervisor|admin; broadcastWith payload is status + counts + timestamps — no PII, no image data)

No new trust boundaries introduced.

## Known Stubs

None. The listener command and watchdog command are both fully wired — there are no placeholders, no hardcoded empty arrays flowing to UI, no "coming soon" strings. The handlers they route to (RecognitionHandler, AckHandler, HeartbeatHandler, OnlineOfflineHandler) are stubs created in Plan 19-02 and are explicitly being implemented in parallel Plan 19-03 (Wave 3 sibling — files_modified lists disjoint from this plan per the orchestrator brief).

## Deferred Issues

None from this plan. (Wave 2 deferred a pre-existing `tests/Feature/Mqtt/MqttClientConfigTest.php` worktree-path issue — re-running that test in the current worktree **now passes** because `tests/Pest.php`'s binding works when vendor is present; that Wave 2 `->extend(TestCase::class)` regression is not introduced here because this plan's test files use bare `pest()->group('mqtt')` per the parallel-execution brief.)

## Self-Check

- [x] FOUND: app/Events/MqttListenerHealthChanged.php
- [x] FOUND: app/Console/Commands/FrasMqttListenCommand.php
- [x] FOUND: app/Console/Commands/FrasMqttListenerWatchdogCommand.php
- [x] FOUND: tests/Feature/Mqtt/MqttListenerHealthChangedTest.php
- [x] FOUND: tests/Feature/Mqtt/MqttListenerWatchdogTest.php
- [x] FOUND: tests/Feature/Mqtt/ListenerCommandRegistrationTest.php
- [x] FOUND: tests/Feature/Mqtt/ListenerCommandMaxTimeTest.php
- [x] MODIFIED: routes/console.php (Schedule::command entry)
- [x] FOUND commit: d45101a (Task 1)
- [x] FOUND commit: 594744c (Task 2)
- [x] FOUND commit: bc6eba5 (Task 3)
- [x] 19/19 Pest tests green (47 assertions)
- [x] `php artisan list` lists both `irms:mqtt-listen` and `irms:mqtt-listener-watchdog`
- [x] `php artisan schedule:list` lists `irms:mqtt-listener-watchdog` with 30s cadence
- [x] routes/channels.php UNCHANGED (D-10 preserved)
- [x] Pint clean across all three commits

## Self-Check: PASSED
