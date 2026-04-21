---
phase: 19
plan: 02
subsystem: mqtt-pipeline-listener-infrastructure
tags: [mqtt, topic-router, handler-contract, fras, pest, wave-2]
requires:
  - MQTT-02
  - 19-01 (config/fras.php topic_prefix, mqtt log channel)
provides:
  - App\Mqtt\Contracts\MqttHandler (single-method contract)
  - App\Mqtt\TopicRouter (regex dispatcher with liveness cache write)
  - App\Mqtt\Handlers\{Recognition,Ack,Heartbeat,OnlineOffline}Handler (empty stubs for 19-03)
  - Cache key mqtt:listener:last_message_received_at (canonical writer)
  - Pest group `mqtt` for subsystem-scoped runs
affects:
  - config/fras.php (read-only dependency — topic_prefix)
  - config/logging.php (read-only dependency — mqtt channel)
tech-stack:
  added: []
  patterns:
    - Regex-anchored topic routing (^...$) + preg_quote on config prefix to prevent env-var metachar injection (T-19-02 mitigation)
    - Structured array log context (['topic' => $topic]) prevents log-forgery via attacker-controlled topic (T-19-04 mitigation)
    - Liveness write BEFORE route match — any arriving message proves broker connectivity (D-05 intent, FRAS parity)
    - Mockery-instanced handlers via $this->app->instance(Class::class, $mock) + $this->mock(Class::class, ...) for per-pattern routing assertions
key-files:
  created:
    - app/Mqtt/Contracts/MqttHandler.php
    - app/Mqtt/Handlers/RecognitionHandler.php
    - app/Mqtt/Handlers/AckHandler.php
    - app/Mqtt/Handlers/HeartbeatHandler.php
    - app/Mqtt/Handlers/OnlineOfflineHandler.php
    - app/Mqtt/TopicRouter.php
    - tests/Feature/Mqtt/TopicRouterTest.php
  modified: []
decisions:
  - [19-02]: Handler stubs created alongside TopicRouter so the container can resolve them; real handler logic lands in Plan 19-03. Wave 2 parallelism is preserved because TopicRouter only needs the class symbols (app() resolution is runtime).
  - [19-02]: Top-level pest()->extend(TestCase::class)->group('mqtt') used instead of bare pest()->group('mqtt'). The Pest.php ->in('Feature') binding did not attach TestCase in this worktree context (facade-root error); explicit extend() is worktree-safe and still honors the 'mqtt' group.
  - [19-02]: `preg_quote(config('fras.mqtt.topic_prefix'), '#')` escapes regex metacharacters — prevents `FRAS_MQTT_TOPIC_PREFIX=.+` or similar env-var injection (T-19-02 mitigation beyond plan spec).
metrics:
  duration: ~12min
  completed: "2026-04-21"
  tasks: 2
  files_created: 7
  commits: 2
---

# Phase 19 Plan 02: TopicRouter + MqttHandler Contract Summary

One-liner: Ported FRAS's `TopicRouter` into `App\Mqtt\`, adapted to IRMS with config-driven topic prefix (`config('fras.mqtt.topic_prefix')` + `preg_quote`), liveness cache write on every arriving message (matched or unmatched per D-05), and `Log::channel('mqtt')` warning for unmatched topics — seven Pest tests (four per-pattern, one unmatched-warning, two liveness) prove the contract end-to-end.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | MqttHandler interface + 4 handler stubs + TopicRouter | efb5e06 | app/Mqtt/Contracts/MqttHandler.php, app/Mqtt/Handlers/{Recognition,Ack,Heartbeat,OnlineOffline}Handler.php, app/Mqtt/TopicRouter.php |
| 2 | Pest per-pattern TopicRouter test | 9fff584 | tests/Feature/Mqtt/TopicRouterTest.php |

## Verification Results

- `php artisan test --compact tests/Feature/Mqtt/TopicRouterTest.php` → **7 passed (9 assertions)**
- `php artisan tinker --execute 'echo get_class(app(\App\Mqtt\TopicRouter::class));'` → `App\Mqtt\TopicRouter` (container resolves without explicit binding)
- `php -l` clean on all 6 new PHP source files
- `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}` on both commits
- Acceptance grep markers:
  - `interface MqttHandler` in contract file → 1 match
  - `implements MqttHandler` across 4 handler files → 4 matches
  - `Cache::put('mqtt:listener:last_message_received_at'` → present in TopicRouter (Pint split it across lines; cache key string intact)
  - `Log::channel('mqtt')->warning('Unmatched MQTT topic'` → 1 match in TopicRouter
  - `config('fras.mqtt.topic_prefix')` → 1 match in TopicRouter
  - `Bump liveness BEFORE routing` reviewer-marker comment → 1 match
  - `pest()->...->group('mqtt')` + `Cache::has('mqtt:listener:last_message_received_at')` markers present in test file

## Success Criteria

1. ✓ MQTT-02: all 4 topic patterns route to correct handler; unmatched topic produces warning log on mqtt channel (7/7 Pest)
2. ✓ Liveness cache write precedes ALL handler invocation (matched OR unmatched) — TopicRouter is the canonical writer of `mqtt:listener:last_message_received_at` (tests 6 + 7 assert both paths)
3. ✓ Test group `mqtt` established for Phase 19 subsystem-scoped runs (pest()->extend(TestCase::class)->group('mqtt'))

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Pest TestCase binding required explicit `pest()->extend()` in worktree context**

- **Found during:** Task 2 first test run
- **Issue:** The plan specified `pest()->group('mqtt');` at file head. Under the worktree's absolute path (`.claude/worktrees/agent-a36ab216/tests/Feature/Mqtt/...`), Pest's `->in('Feature')` binding in `tests/Pest.php` did not attach `Tests\TestCase`, causing `$this->mock()` to fail with "Call to undefined method" and later facade calls to fail with "A facade root has not been set." All 7 tests failed.
- **Fix:** Changed `pest()->group('mqtt');` to `pest()->extend(TestCase::class)->group('mqtt');` and imported `Tests\TestCase`. This explicit extend() is worktree-safe, still honors the `mqtt` group, and is consistent with Pest 4 guidance when the directory-based binding does not apply.
- **Files modified:** tests/Feature/Mqtt/TopicRouterTest.php
- **Commit:** 9fff584

**2. [Rule 2 - Correctness] `preg_quote` on config prefix (T-19-02 mitigation)**

- **Found during:** Task 1 implementation (code review of the regex pattern construction)
- **Issue:** The plan's verbatim FRAS port example concatenated `$prefix` directly into the regex. If a future operator sets `FRAS_MQTT_TOPIC_PREFIX` to a value containing regex metacharacters (e.g., `.`, `+`, `*`, `?`, `[`, `#`), the routing regex would silently mismatch or match too broadly. The threat register (T-19-02) mandates mitigating prefix injection — a subtle correctness + security concern.
- **Fix:** Wrapped the config read in `preg_quote(config('fras.mqtt.topic_prefix'), '#')` so arbitrary prefix values are treated literally by the anchored `^...$` regex.
- **Files modified:** app/Mqtt/TopicRouter.php
- **Commit:** efb5e06 (included in initial implementation)

### Auth Gates

None — fully autonomous plan.

### Architectural Changes

None.

## TDD Gate Compliance

Plan 19-02 is a two-task implementation where Task 1 creates the SUT (`TopicRouter` + handler stubs) and Task 2 creates the Pest test file. Commit sequence:

1. `efb5e06` — `feat(19-02): add MqttHandler contract, handler stubs, and TopicRouter` (implementation — GREEN-equivalent, implementation committed first because stubs are needed before the test can reference them)
2. `9fff584` — `test(19-02): per-pattern TopicRouter routing + liveness + unmatched warning` (tests green on first run after the binding fix above)

This inverts the classic RED-then-GREEN order because the test file imports 5 handler class names that must exist before the test file parses. The plan acknowledges this by putting all 6 PHP files in Task 1's `<files>` list and the test file in Task 2's — effectively stubs-first + tests-after. Both tasks are committed individually with conventional-commit prefixes; the RED-signal still manifests (initial `pest` run failed 7/7 before the binding fix, documented as Deviation #1).

## Threat Flags

None new beyond the plan's threat model:

- T-19-02 (tampering via crafted prefix) — **mitigated** (preg_quote + anchored regex — see Deviation #2)
- T-19-04 (log injection via attacker-controlled topic) — **mitigated** (structured `['topic' => $topic]` array context, not string interpolation — verified by `Mockery::on(fn ($ctx) => $ctx['topic'] === ...)` assertion in Test 5)

No new surface introduced. The four handler stubs contain no logic — they are empty `handle()` bodies implementing the `MqttHandler` contract. Real handlers land in Plan 19-03.

## Known Stubs

- `App\Mqtt\Handlers\RecognitionHandler::handle()` — empty body, TODO Plan 19-03
- `App\Mqtt\Handlers\AckHandler::handle()` — empty body, TODO Plan 19-03
- `App\Mqtt\Handlers\HeartbeatHandler::handle()` — empty body, TODO Plan 19-03
- `App\Mqtt\Handlers\OnlineOfflineHandler::handle()` — empty body, TODO Plan 19-03

These stubs are **intentional** per the plan's explicit wave-2 parallelism strategy (TopicRouter only needs class symbols; `app()` resolution is runtime; real handler logic lands in Plan 19-03 / Wave 3). The stubs satisfy the `MqttHandler` interface so the container resolves them; routing tests mock them via `$this->app->instance()` so the stub bodies never execute in tests.

## Deferred Issues

Out-of-scope pre-existing issue surfaced in this worktree but **not modified**:

- `tests/Feature/Mqtt/MqttClientConfigTest.php` (from 19-01) has the same `pest()->group('mqtt')` binding behavior that breaks `$this->mock()` in the current worktree path. In 19-01's SUMMARY it passed 5/5 because it was likely run from a different working path or without a worktree; rerunning via `vendor/bin/pest --group=mqtt` in this worktree now shows 5 failures. Per scope-boundary rule this is logged for the orchestrator / next plan to address, not fixed here. The 19-02 TopicRouterTest.php is the only test required by 19-02's success criteria, and it passes 7/7.

## Self-Check

- [x] FOUND: app/Mqtt/Contracts/MqttHandler.php
- [x] FOUND: app/Mqtt/Handlers/RecognitionHandler.php
- [x] FOUND: app/Mqtt/Handlers/AckHandler.php
- [x] FOUND: app/Mqtt/Handlers/HeartbeatHandler.php
- [x] FOUND: app/Mqtt/Handlers/OnlineOfflineHandler.php
- [x] FOUND: app/Mqtt/TopicRouter.php
- [x] FOUND: tests/Feature/Mqtt/TopicRouterTest.php
- [x] FOUND commit: efb5e06 (Task 1)
- [x] FOUND commit: 9fff584 (Task 2)
- [x] `php artisan test --compact tests/Feature/Mqtt/TopicRouterTest.php` → 7/7 pass
- [x] Container resolves `App\Mqtt\TopicRouter` without explicit binding
- [x] Pint clean on all new files

## Self-Check: PASSED
