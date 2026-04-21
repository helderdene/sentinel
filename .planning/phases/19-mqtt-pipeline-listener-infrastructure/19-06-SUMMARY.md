---
phase: 19-mqtt-pipeline-listener-infrastructure
plan: 06
subsystem: infra
tags: [mqtt, mosquitto, supervisor, composer, concurrently, runbook, ops, docs, pest]

# Dependency graph
requires:
  - phase: 19-mqtt-pipeline-listener-infrastructure
    provides: "irms:mqtt-listen command (Plan 19-04) + mqtt log channel (Plan 19-01) + MqttListenerWatchdog (Plan 19-05)"
provides:
  - "composer run dev now starts 6 concurrent processes (added mqtt color #f59e0b)"
  - "docs/operations/irms-mqtt.md: production Supervisor block, deploy protocol, smoke test runbook, troubleshooting"
  - "Pitfall 6 mitigation — listener runs under its own [program:irms-mqtt], NOT under Horizon"
  - "Pitfall 7 mitigation — deploy protocol mandates supervisorctl restart irms-mqtt:* + log-line verification"
  - "Regression protection: 10 Pest tests (4 composer + 6 runbook) locking D-16 and Pitfalls 6/7"
affects: [phase-20-camera-admin, phase-21-frasincidentfactory, phase-22-retention-dpa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dedicated Supervisor program per long-lived listener (mirror of [program:irms-horizon]/[program:irms-reverb])"
    - "Runbook-as-regression-test: Pest tests grep markdown for required markers (Pitfall ID strings, deploy commands, smoke-test headers)"
    - "6-process composer run dev with color-coded streams per process"

key-files:
  created:
    - "docs/operations/irms-mqtt.md"
    - "tests/Feature/Mqtt/ComposerScriptTest.php"
    - "tests/Feature/Mqtt/OperationsDocTest.php"
  modified:
    - "composer.json (scripts.dev: +6th concurrently target irms:mqtt-listen, color #f59e0b, name mqtt)"

key-decisions:
  - "Supervisor block uses autorestart=unexpected (not true) — clean exit via --max-time=3600 is intentional hourly rotation, crashes also trigger restart"
  - "stopwaitsecs=30 for MQTT listener (vs 3600 for Horizon) — MQTT messages are tiny and ack-fast; 30s is ample for graceful disconnect"
  - "Pitfall 6 called out explicitly in runbook §3 ('Never put this under Horizon') to prevent future ops drift"
  - "Smoke test uses facesluiceId=irms-smoketest (unknown camera) to exercise full listener path without polluting real camera state; expected Heartbeat for unknown camera warning is the success signal"

patterns-established:
  - "Operations runbook structure: Overview → Dev Prereqs → Prod Supervisor → Deploy Protocol → Smoke Test → Troubleshooting → Deferred (mirrors docs/operations/laravel-13-upgrade.md §1-10)"
  - "Pest doc-regression tests use pest()->group('mqtt') + file_get_contents(base_path()) + ->toContain() for literal marker assertions"

requirements-completed: [MQTT-01]

# Metrics
duration: 6min
completed: 2026-04-21
---

# Phase 19 Plan 06: Operational Surface — composer run dev + Supervisor runbook Summary

**Wired MQTT listener into the 6-process `composer run dev` (D-16, color `#f59e0b`), authored `docs/operations/irms-mqtt.md` covering production `[program:irms-mqtt]` Supervisor block (Pitfall 6), `supervisorctl restart irms-mqtt:*` deploy protocol (Pitfall 7), and post-deploy `mosquitto_pub` smoke test — all locked by 10 Pest regression tests.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-04-21T12:10:44Z
- **Completed:** 2026-04-21T12:16:44Z
- **Tasks:** 2 of 3 executed autonomously (Task 3 is a human-verify checkpoint — see below)
- **Files modified:** 4 (1 composer.json MOD + 1 runbook NEW + 2 tests NEW)

## Accomplishments

- **D-16 landed in composer.json:** `scripts.dev` now declares 6 concurrently targets (`server`, `reverb`, `horizon`, `logs`, `vite`, **`mqtt`**). The `mqtt` stream is color `#f59e0b` (amber) and runs `php artisan irms:mqtt-listen` — so local devs who forgot to start Mosquitto see the failure loud + early (listener crashes + `--kill-others` brings the rest of the stack down with it).
- **Production runbook published:** `docs/operations/irms-mqtt.md` (7 sections, 270 lines). Mirrors the structure of `docs/operations/laravel-13-upgrade.md` §8 and covers:
  - §2 Mosquitto install for macOS (`brew install mosquitto`) + Linux (`apt-get install mosquitto mosquitto-clients`).
  - §3 `[program:irms-mqtt]` Supervisor block with `stopwaitsecs=30`, `autorestart=unexpected`, `stopsignal=TERM`, `numprocs=1`, rotating 50 MB logs. Calls out Pitfall 6 verbatim: "Never put this under Horizon".
  - §4 Deploy protocol (Pitfall 7): `supervisorctl restart irms-mqtt:*` added AFTER Horizon restart and AFTER migrations, followed by a `tail -n 20 | grep "MQTT listener started"` verification step.
  - §5 `smoke test runbook` with a `mosquitto_pub -t mqtt/face/heartbeat -m '{"facesluiceId":"irms-smoketest"}'` one-liner.
  - §6 Troubleshooting (connection-refused, stale watchdog key, duplicate RecPush expected).
  - §7 Deferred links to Phases 20–22.
- **Regression lock:** 10 Pest tests (4 ComposerScriptTest + 6 OperationsDocTest), all green. Future edits that remove the D-16 color/name or Pitfall 6/7 markers will fail the suite.

## Task Commits

Each task was committed atomically (worktree used `--no-verify` per parallel executor convention):

1. **Task 1: composer.json scripts.dev + docs/operations/irms-mqtt.md runbook** — `7b17225` (feat)
2. **Task 2: ComposerScriptTest + OperationsDocTest regression tests** — `3d84e3c` (test)

Plan metadata commit will be created by the orchestrator after merge.

## Files Created/Modified

- `composer.json` — `scripts.dev` concurrently command: added 6th color `#f59e0b`, 6th command `php artisan irms:mqtt-listen`, 6th name `mqtt`.
- `docs/operations/irms-mqtt.md` — 270-line ops runbook (7 sections) covering dev prerequisites, production Supervisor block, deploy protocol, smoke test, troubleshooting, deferred.
- `tests/Feature/Mqtt/ComposerScriptTest.php` — 4 Pest tests: valid JSON, `irms:mqtt-listen` present, `#f59e0b` color, `,mqtt --kill-others` names tail.
- `tests/Feature/Mqtt/OperationsDocTest.php` — 6 Pest tests: doc exists, `[program:irms-mqtt]`, `supervisorctl restart irms-mqtt:*`, `smoke test runbook` literal, `mosquitto_pub`, macOS-or-Linux Mosquitto install prereq.

## Decisions Made

- **`autorestart=unexpected` vs `autorestart=true`:** Picked `unexpected` because the listener intentionally exits cleanly after `--max-time=3600` (MQTT-04 hourly rotation). A `true` setting would still restart on clean exit, but `unexpected` documents the design intent more clearly for future ops readers — clean exit IS part of the spec, not a failure mode.
- **`stopwaitsecs=30`:** 30 seconds is far shorter than Horizon's 3600 s. MQTT messages are individually tiny (<1 KB) and brokers ack within milliseconds; 30 s leaves plenty of time to flush an in-flight ack + disconnect gracefully before SIGKILL.
- **Runbook §5 header literal `smoke test runbook`:** Required as a literal substring for tooling/Pest grep (explicit plan requirement). Section heading is `## 5. Post-Deploy smoke test runbook` — literal phrase is present within the heading.
- **Sentinel smoke-test payload `facesluiceId: irms-smoketest`:** Unknown-camera path is the intended Phase 19 smoke signal; it exercises listener → TopicRouter → HeartbeatHandler without polluting camera state. A real registered camera would mask pipeline failures by logging success against the wrong evidence.

## Deviations from Plan

None — plan executed exactly as written. Both Task 1 and Task 2 followed the plan's `<action>` blocks verbatim; all acceptance criteria pass on the first run.

## Issues Encountered

- **Vendor not present in worktree:** `vendor/bin/pint` and `php artisan` required a `composer install`. Resolved by running `composer install --no-interaction --prefer-dist --no-progress` in the worktree before Task 2's Pint + test step. No code impact.
- **No others.**

## User Setup Required

None — no external service configuration required beyond the Mosquitto install that the runbook itself documents for developers.

## Checkpoint — pending user verification

Task 3 is a `checkpoint:human-verify` gate. It was not executed in this worktree because:
1. `composer run dev` launches long-running processes that would block the agent.
2. Visual/functional verification of the colored process streams and the dispatch console banner requires a human at a terminal and browser.

**After this worktree merges, the orchestrator should present the following verification steps to the user:**

1. **Confirm Mosquitto is running locally:**
   - macOS: `brew services list | grep mosquitto` (start with `brew services start mosquitto` if needed)
   - Linux: `systemctl status mosquitto`
2. **Start the 6-process dev stack:**
   ```bash
   composer run dev
   ```
   - Expect 6 colored process streams (`server`, `reverb`, `horizon`, `logs`, `vite`, `mqtt`).
   - The `mqtt` stream (amber `#f59e0b`) should print `MQTT listener started` shortly after startup.
   - No `Connection refused` errors from the listener.
3. **Publish a test message from another terminal:**
   ```bash
   mosquitto_pub -h localhost -p 1883 -t mqtt/face/heartbeat -m '{"facesluiceId":"irms-smoketest"}'
   ```
   - Expect in `storage/logs/mqtt-$(date +%Y-%m-%d).log`: `Heartbeat for unknown camera` warning with `device_id=irms-smoketest` (expected — no such camera registered).
4. **Verify watchdog + banner (optional end-to-end):**
   - Seed one Camera: `php artisan tinker --execute 'App\Models\Camera::factory()->create();'`
   - In another terminal: `php artisan schedule:work`.
   - Kill the `mqtt` process (SIGTERM to its PID or Ctrl+C if running standalone).
   - Wait 90–120 s — expect red banner "MQTT listener silent" on `/dispatch`.
   - Restart the listener + publish a message — banner clears within ~30 s.
5. **Review the runbook:** open `docs/operations/irms-mqtt.md` — confirm clarity of §2 (dev prereq), §3 (Supervisor block), §4 (deploy protocol), §5 (smoke test), §6 (troubleshooting).

**Resume signal:** User types "approved" if all 5 checks pass, OR describes gaps/issues so a gap-closure plan can be opened.

## Next Phase Readiness

- Phase 19 operational surface complete (MQTT-01 end-to-end).
- Pitfalls 6 and 7 both structurally mitigated with docs + regression tests.
- Wave 5 is the final wave of Phase 19 (pending only the human-verify checkpoint above). Phase 19 is ready to close after the user approves.
- Phase 20 (camera admin CRUD) will naturally remove the "unknown camera" warning path from the smoke test — documented in §7 Deferred.

## Self-Check

Verifying all claimed files and commits exist:

- `composer.json` (modified) — FOUND
- `docs/operations/irms-mqtt.md` — FOUND
- `tests/Feature/Mqtt/ComposerScriptTest.php` — FOUND
- `tests/Feature/Mqtt/OperationsDocTest.php` — FOUND
- Commit `7b17225` (Task 1) — FOUND in git log
- Commit `3d84e3c` (Task 2) — FOUND in git log
- Pest run `php artisan test --compact tests/Feature/Mqtt/ComposerScriptTest.php tests/Feature/Mqtt/OperationsDocTest.php` — 10/10 green

## Self-Check: PASSED

---
*Phase: 19-mqtt-pipeline-listener-infrastructure*
*Plan: 06*
*Completed: 2026-04-21*
