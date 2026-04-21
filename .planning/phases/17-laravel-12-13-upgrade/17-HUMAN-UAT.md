---
status: partial
phase: 17-laravel-12-13-upgrade
source: [17-VERIFICATION.md]
started: 2026-04-21T05:00:00Z
updated: 2026-04-21T05:00:00Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. Dispatcher full-cycle behavioral parity on Laravel 13 (Phase 17 SC4)
expected: A dispatcher completing a full Report → Triage → Dispatch → ACK → OnScene → Resolve cycle on the upgraded L13 build sees no behavioral difference from the v1.0 L12 build (spot-verified against v1.0 UAT scripts).
result: [pending]

Steps:
1. Serve the app on Herd at `irms.test` (L13 state is current working tree).
2. Operator logs in, files a P2 incident via `/intake` (Walk-in channel works; SMS/IoT webhooks optional).
3. Dispatcher (second browser) receives the incident on `/dispatch`, triages to TRIAGED, assigns nearest unit.
4. Responder (third browser on mobile viewport) receives the assignment push, ACKs within the 90s countdown, transitions Standby → ACK → EnRoute → OnScene → Resolving → Resolved.
5. Responder fills `OutcomeSheet`, picks a hospital, submits `ClosureSummary`.
6. Verify: incident report PDF downloads correctly. Timeline entries show correct actors + timestamps. Dispatch console live-updates at each state change (Reverb WebSocket). No JS console errors. No 500s in `storage/logs/laravel.log`.
7. Compare against a v1.0 UAT script artifact (if available) — note ANY behavioral diff, however small.

### 2. Horizon drain-and-deploy runbook reproducibility (FRAMEWORK-03 / Phase 17 SC3)
expected: An admin following `docs/operations/laravel-13-upgrade.md` on a real staging environment can deploy Laravel 13 without any queued job executing under a mixed-version worker. Drain → deploy → restart is reproducible by a human without AI assistance.
result: [pending]

Steps:
1. Spin up a staging environment with Redis + Horizon running (local Herd does not run Redis — this must be a real staging env).
2. Check out the pre-Phase-17 commit (parent of `ca937b4`) to establish the L12 starting state. Deploy it. Start Horizon. Queue some background jobs (e.g., `IncidentReportJob`, `CheckAckTimeout`).
3. Follow `docs/operations/laravel-13-upgrade.md` step-by-step:
   - §3 Pre-deploy checklist — verify compat, backup DB, announce maintenance window
   - §4 Drain sequence — `horizon:pause` → poll `horizon:status` until paused → `horizon:terminate` → verify queue depth zero
   - §5 Deploy — `git pull` to the Phase 17 tip (`021c5ee`) + `composer install --no-dev` + config/route cache + migrate
   - §6 Restart — `supervisorctl restart irms-horizon`, confirm `horizon:status` healthy
   - §7 Smoke test — enqueue a test job, verify it completes on the L13 worker
4. Verify NO queued-job execution failure during the entire drain+deploy+restart window. No `Job payload unserializable` errors in the logs.
5. If any step in the runbook is ambiguous, unclear, or fails — record the specific line/section that needs revision. This feeds back into the runbook.

## Summary

total: 2
passed: 0
issues: 0
pending: 2
skipped: 0
blocked: 0

## Gaps

[populated when human testing finds issues]
