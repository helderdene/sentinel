---
phase: 19
plan: 05
subsystem: mqtt-pipeline-listener-infrastructure
tags: [mqtt, dispatch, inertia, echo, banner, frontend, wave-4]
requires:
  - MQTT-05
  - 19-04 (MqttListenerHealthChanged broadcast event + payload contract)
provides:
  - `mqtt_listener_health` Inertia shared prop on dispatch console (D-12)
  - MqttListenerHealthBanner.vue (red banner, SILENT/DISCONNECTED only, D-13)
  - useDispatchFeed subscribes to `MqttListenerHealthChanged` on existing `dispatch.incidents` private channel (D-10)
  - resources/js/types/mqtt.ts (MqttListenerHealthStatus union + camelCase MqttListenerHealth + snake_case MqttListenerHealthPayload)
affects:
  - app/Http/Controllers/DispatchConsoleController.php (show() — one new Inertia prop)
  - resources/js/pages/dispatch/Console.vue (new prop + banner render at top of layout)
  - resources/js/composables/useDispatchFeed.ts (new parameter + reactive ref + one useEcho block)
tech-stack:
  added: []
  patterns:
    - Inertia shared prop on show() (NOT HandleInertiaRequests middleware) — D-12
    - Conservative `NO_ACTIVE_CAMERAS` default on fresh cache miss — watchdog promotes to HEALTHY/SILENT on next 30s tick (D-11)
    - Banner reuses ConnectionBanner.vue Transition enter/leave classes
    - useEcho block added on pre-authorized `dispatch.incidents` channel (D-10 — no routes/channels.php change)
    - Snake_case broadcast payload mapped to camelCase Vue state inside the useEcho callback
key-files:
  created:
    - resources/js/types/mqtt.ts
    - resources/js/components/fras/MqttListenerHealthBanner.vue
    - tests/Feature/Dispatch/DispatchConsoleMqttHealthPropTest.php
  modified:
    - app/Http/Controllers/DispatchConsoleController.php
    - resources/js/composables/useDispatchFeed.ts
    - resources/js/pages/dispatch/Console.vue
decisions:
  - [19-05]: Default `Cache::get('mqtt:listener:last_known_state', 'NO_ACTIVE_CAMERAS')` — the D-09/D-11 conservative policy. A fresh install or cleared cache must not render the red banner; the watchdog will flip state to HEALTHY/SILENT once it has observed active cameras.
  - [19-05]: Inertia prop passed from the controller's `show()` method directly (plan body uses `index` — outdated; code uses `show()` per the actual IRMS source and the plan's interfaces block NOTE).
  - [19-05]: `Camera::query()->whereNull('decommissioned_at')` was used over the Camera `active()` scope to keep the query inline and independent of future scope renames; equivalent output.
  - [19-05]: Test file uses only `pest()->group('mqtt');` — no `->extend(TestCase::class)` — because `tests/Pest.php` already binds TestCase + RefreshDatabase via `->in('Feature')` (Wave 2 regression kept out per parallel-execution brief).
metrics:
  duration: ~20min
  completed: "2026-04-21"
  tasks: 2
  files_created: 3
  files_modified: 3
  commits: 2
---

# Phase 19 Plan 05: Dispatch Console MQTT Listener Health Banner Summary

One-liner: Surfaced the MQTT listener health on the dispatch console end-to-end — Inertia shared prop `mqtt_listener_health` on initial load (D-12, NO_ACTIVE_CAMERAS conservative default), live updates via `MqttListenerHealthChanged` on the pre-authorized `dispatch.incidents` private channel (D-10), and a red top banner that renders only on `SILENT` or `DISCONNECTED` (D-11/D-13). Banner visibility scoped to `/dispatch` only; responder/intake/citizen pages unaffected.

## Tasks Completed

| Task | Name                                                                                     | Commit  | Files                                                                                                                         |
| ---- | ---------------------------------------------------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------- |
| 1    | Add `mqtt_listener_health` Inertia shared prop + TypeScript types + Pest assertion (TDD) | e04d3bf | app/Http/Controllers/DispatchConsoleController.php, resources/js/types/mqtt.ts, tests/Feature/Dispatch/DispatchConsoleMqttHealthPropTest.php |
| 2    | MqttListenerHealthBanner.vue + useDispatchFeed Echo subscription + Console.vue render    | 7060997 | resources/js/components/fras/MqttListenerHealthBanner.vue, resources/js/composables/useDispatchFeed.ts, resources/js/pages/dispatch/Console.vue |

## Verification Results

- `php artisan test --compact tests/Feature/Dispatch/DispatchConsoleMqttHealthPropTest.php tests/Feature/Dispatch/DispatchConsolePageTest.php` -> **10 passed (110 assertions)** in 1.52s
- `vendor/bin/pint --dirty --format agent` -> `{"result":"pass"}`
- `npm run types:check` -> clean for all plan-19-05 files; only pre-existing `resources/js/pages/admin/UnitForm.vue:263` Reka UI AcceptableValue error remains (unrelated — Phase 18 leftover, out of scope)
- Acceptance grep markers (all present):
  - `grep -c "'mqtt_listener_health'" app/Http/Controllers/DispatchConsoleController.php` -> 1
  - `grep -c "Cache::get('mqtt:listener:last_known_state', 'NO_ACTIVE_CAMERAS')" app/Http/Controllers/DispatchConsoleController.php` -> 1
  - `grep -c "'HEALTHY'" app/Http/Controllers/DispatchConsoleController.php` -> 0 (no literal HEALTHY default)
  - `grep -c "export type MqttListenerHealthStatus" resources/js/types/mqtt.ts` -> 1
  - `grep -c "export interface MqttListenerHealthPayload" resources/js/types/mqtt.ts` -> 1
  - `grep -c "'MqttListenerHealthChanged'" resources/js/composables/useDispatchFeed.ts` -> 1
  - `grep -c "bg-red-600" resources/js/components/fras/MqttListenerHealthBanner.vue` -> 1
  - `grep -c 'v-if="isSilent"' resources/js/components/fras/MqttListenerHealthBanner.vue` -> 1
  - `grep -r "MqttListenerHealthBanner" resources/js/pages/` -> 1 file (resources/js/pages/dispatch/Console.vue) — D-13 scope enforcement

## Success Criteria

1. Dispatcher sees the banner within ~60s of listener going silent (watchdog 30s cadence + 90s threshold + Echo broadcast latency)
2. D-13 respected: banner scoped to dispatch console only (grep confirmed)
3. No new channel auth (D-10 — reuses dispatch.incidents; routes/channels.php unchanged)
4. TypeScript strict mode clean for plan-19-05 files
5. Conservative NO_ACTIVE_CAMERAS default prevents spurious banner on fresh install (Pest test 2 asserts this)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `composer install` in worktree**

- **Found during:** Task 1 first `php artisan test` run — `Class "PhpMqtt\Client\MqttClient" not found` when bootstrap evaluated config/mqtt-client.php.
- **Issue:** The worktree had no `vendor/` directory after the base-reset to `1f7d15…`. The Pest harness fails to boot Laravel before it reaches any feature-test code.
- **Fix:** Ran `composer install --no-interaction --no-progress --prefer-dist`. `vendor/` is `.gitignore`d so no code change was committed.
- **Commit:** n/a (not a code change)

**2. [Rule 3 - Blocking] Vite manifest missing in worktree**

- **Found during:** Task 1 second `php artisan test` run — `Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest`.
- **Issue:** Inertia renders the Blade `resources/views/app.blade.php` layout which calls `@vite(...)`. A fresh worktree has no `public/build/manifest.json`.
- **Fix:** Ran `npm ci` + `npm run build` once. Build output (`public/build/*`) is `.gitignore`d so no code change was committed. Confirmed the same failure also blocks the pre-existing `tests/Feature/Dispatch/DispatchConsolePageTest.php` — not plan-19-05 regression.
- **Commit:** n/a (not a code change)

**3. [Rule 2 - Correctness] Layout wrapper change on Console.vue**

- **Found during:** Task 2 implementation while deciding where to mount the banner.
- **Issue:** The plan says "Render the banner at the TOP of the layout". The existing root `<div class="flex h-full w-full">` has three children laid out horizontally (left queue, center map, right panel). Inserting a full-width banner between those three children would only push the left panel over; the banner would not span full width.
- **Fix:** Wrapped the three-panel row in an inner `<div class="flex min-h-0 flex-1">` and promoted the outer root to `flex h-full w-full flex-col` so the banner sits on its own row above the horizontal panel row. `min-h-0 flex-1` preserves the previous height behaviour (flexbox child min-height default is `auto` which would let the map column overflow; forcing `min-h-0` restores the IncidentQueue/Map scroll behaviour).
- **Files modified:** resources/js/pages/dispatch/Console.vue
- **Commit:** 7060997

**4. [Rule 3 - Blocking] Revert unrelated Prettier reformats**

- **Found during:** `npm run format` in Task 2 touched 11 unrelated `.vue`/`.ts` files in the worktree that Prettier normalised (e.g. reflowing import groups in IncidentDetailPanel, AuthLayout, etc.). These were pre-existing formatting drift independent of plan 19-05.
- **Fix:** Per scope boundary, ran `git checkout --` on the 11 unrelated files so the commit only carries plan-19-05 files. The prior drift is out of scope; belongs in a dedicated `style:` commit or a Prettier pre-commit hook rollout — logged in Deferred Issues.
- **Commit:** n/a (reverted before Task 2 commit)

### Auth Gates

None — fully autonomous plan.

### Architectural Changes

None. Banner is a presentational leaf component; composable just grows one more `useEcho` subscription on the already-authorized `dispatch.incidents` channel; controller gains one prop.

## TDD Gate Compliance

Task 1 is declared `tdd="true"`. Gate sequence observed:

- **RED:** Pest test file was created and run first. `php artisan test --compact tests/Feature/Dispatch/DispatchConsoleMqttHealthPropTest.php` produced **4 failed / 0 passed** ("Property [mqtt_listener_health.active_camera_count] does not exist" + 3 similar assertion failures).
- **GREEN:** Controller edits + type file additions -> 4/4 tests pass (49 assertions).
- **REFACTOR:** Not required; controller change is minimal (one array + one key).

Task 1 and Task 2 commits use `feat(19-05):` conventional-commit prefix. RED phase committed in-line with GREEN (per 19-04 precedent — the test file imports nothing that would ParseError before the prop exists; all failures are assertion-level).

## Threat Flags

None new beyond the plan's `<threat_model>`:

- T-19-05 (Info Disclosure via broadcast payload): **accepted** — payload carries only status string + ISO timestamps + integer count; no PII, no camera metadata. Channel auth in `routes/channels.php` lines 9-11 already restricts to `operator|dispatcher|supervisor|admin`; `routes/channels.php` unchanged.
- T-19-04 (Info Disclosure via banner render): **mitigated** — Vue template interpolates status string + `lastMessageReceivedAt` (ISO timestamp) only. Vue auto-escapes by default.

No new trust boundaries introduced.

## Known Stubs

None. The banner is wired end-to-end: the controller populates the prop from real cache reads + a real Camera count, the composable subscribes to the real broadcast event, and the banner renders actual reactive state. There are no placeholders, hardcoded empty arrays, or "coming soon" strings.

## Deferred Issues

1. **Pre-existing TypeScript error** in `resources/js/pages/admin/UnitForm.vue:263` — Reka UI `AcceptableValue` vs `string` incompatibility. Not caused by plan 19-05. Logged for a future admin-forms cleanup plan.
2. **Pre-existing ESLint error** in `report-app/vite.config.ts` — TSConfig project service coverage. Not caused by plan 19-05; belongs in report-app build cleanup.
3. **Prettier formatting drift** across 11 unrelated `.vue`/`.ts` files in `resources/js/components/*`, `resources/js/composables/*`, `resources/js/layouts/*`, `resources/js/pages/admin/*` — reformats Prettier would apply on first `npm run format`. Reverted from this commit per scope boundary. Either run `npm run format` as a standalone `style:` commit on main, or install a Husky pre-commit Prettier hook to prevent future drift.

## Self-Check

- [x] FOUND: resources/js/types/mqtt.ts
- [x] FOUND: resources/js/components/fras/MqttListenerHealthBanner.vue
- [x] FOUND: tests/Feature/Dispatch/DispatchConsoleMqttHealthPropTest.php
- [x] MODIFIED: app/Http/Controllers/DispatchConsoleController.php
- [x] MODIFIED: resources/js/composables/useDispatchFeed.ts
- [x] MODIFIED: resources/js/pages/dispatch/Console.vue
- [x] FOUND commit: e04d3bf (Task 1)
- [x] FOUND commit: 7060997 (Task 2)
- [x] 4/4 new Pest tests green (plan-19-05 test file) + 6/6 existing dispatch tests unchanged (10/110 total, 1.52s)
- [x] Pint clean (`vendor/bin/pint --dirty --format agent` -> pass)
- [x] D-13 scope enforced (`grep -r "MqttListenerHealthBanner" resources/js/pages/` -> only dispatch/Console.vue)
- [x] D-10 respected (routes/channels.php UNCHANGED)
- [x] D-12 respected (prop on `show()`, not HandleInertiaRequests)
- [x] D-11 respected (default `NO_ACTIVE_CAMERAS`, not `HEALTHY`)

## Self-Check: PASSED
