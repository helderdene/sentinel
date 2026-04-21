---
phase: 17
slug: laravel-12-13-upgrade
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 17 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=Broadcasting` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~60 seconds (full v1.0 suite, SQLite in-memory) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<affected scope>`
- **After every plan wave:** Run `php artisan test --compact` (full v1.0 suite)
- **Before `/gsd-verify-work`:** Full suite must be green + 6 broadcast snapshots must match byte-for-byte
- **Max feedback latency:** ~60s

---

## Per-Task Verification Map

*This map is populated by the planner with exact task IDs. Each task below is keyed by requirement + verification type.*

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 17-01-XX | 01 | 1 | FRAMEWORK-02 | — | Broadcast payload snapshots captured on L12 (byte-identical golden fixtures) | unit | `php artisan test --compact tests/Feature/Broadcasting/` | ❌ W0 | ⬜ pending |
| 17-02-XX | 02 | 2 | FRAMEWORK-01 | T-17-01 | `composer update` succeeds; L13 framework installs; full Pest suite green | feature | `composer install && php artisan test --compact` | ✅ | ⬜ pending |
| 17-02-XX | 02 | 2 | FRAMEWORK-01 | T-17-02 | CSRF middleware rename (`VerifyCsrfToken` → `PreventRequestForgery`) applied in `routes/web.php`; webhook routes still skip CSRF correctly | feature | `php artisan test --compact --filter=Webhook` | ✅ | ⬜ pending |
| 17-02-XX | 02 | 2 | FRAMEWORK-01 | — | `config/cache.php` includes `'serializable_classes' => false`; cache operations unaffected | unit | `php artisan config:show cache.serializable_classes` + assertion test | ✅ | ⬜ pending |
| 17-03-XX | 03 | 3 | FRAMEWORK-01 | — | Aligned package bumps (Horizon 5.45.6, Magellan 2.1.0, Reverb 1.10, Fortify, Wayfinder, Inertia-Laravel, Tinker 3, Pest 4.6) installed; full suite still green | feature | `composer outdated --direct` shows no L13-blocking upgrades + `php artisan test --compact` | ✅ | ⬜ pending |
| 17-03-XX | 03 | 3 | FRAMEWORK-02 | — | 6 broadcast snapshot tests from Wave 1 assert byte-identical JSON on L13 | unit | `php artisan test --compact tests/Feature/Broadcasting/` | ✅ (post-W1) | ⬜ pending |
| 17-03-XX | 03 | 3 | FRAMEWORK-03 | — | `docs/operations/laravel-13-upgrade.md` runbook exists, covers drain sequence (`horizon:pause` → poll → `horizon:terminate` → Supervisor `stopwaitsecs`) | doc | `grep -q 'horizon:terminate' docs/operations/laravel-13-upgrade.md` | ❌ W0 | ⬜ pending |

*Planner refines exact task IDs + waves during step 8. Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Broadcasting/` — NEW directory. Six snapshot tests (one per event): `IncidentCreatedSnapshotTest.php`, `IncidentTriagedSnapshotTest.php` (from `IncidentStatusChanged` PENDING→TRIAGED), `UnitAssignedSnapshotTest.php` (from `AssignmentPushed`), `UnitStatusChangedSnapshotTest.php`, `ChecklistUpdatedSnapshotTest.php`, `ResourceRequestedSnapshotTest.php`.
- [ ] `tests/Feature/Broadcasting/__snapshots__/` — NEW directory. Six golden JSON fixtures captured on L12 (pre-upgrade) via `freezeTime()` + fixed factory seeds.
- [ ] `docs/operations/` — NEW directory. `laravel-13-upgrade.md` runbook with drain-and-deploy protocol (FRAMEWORK-03 acceptance artifact).

*Pest 4 framework already installed. No framework install needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Dispatcher completes full Report → Triage → Dispatch → ACK → OnScene → Resolve cycle on L13 build with no behavioral diff vs v1.0 | Phase 17 Success Criterion 4 | Real-browser validation across the dispatch console + responder mobile flow; v1.0 UAT scripts live in prior phases' human-UAT files | (1) Deploy L13 build to dev/staging `irms.test`. (2) Operator posts a P2 incident via `/intake`. (3) Dispatcher triages + assigns unit via `/dispatch`. (4) Responder (second browser) receives push, ACKs within 90s, reaches OnScene, Resolves with outcome. (5) Screenshot PDF report generated. (6) Tick checklist on `.planning/phases/17-laravel-12-13-upgrade/17-HUMAN-UAT.md`. |
| Horizon drain-and-deploy runbook is reproducible in production | FRAMEWORK-03 / SC 3 | Runbook must be executable by admin without AI assistance; reproducibility can only be proven by a human walking through it | Admin follows `docs/operations/laravel-13-upgrade.md` on a staging deploy: (1) pause Horizon, (2) wait for queue drain, (3) terminate Horizon, (4) deploy L13 build, (5) restart Horizon. No queued job should execute under a mixed-version worker. Log duration + any deviations on 17-HUMAN-UAT.md. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references (tests/Feature/Broadcasting/, __snapshots__/, docs/operations/)
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
