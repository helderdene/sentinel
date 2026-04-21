# Phase 17: Laravel 12 → 13 Upgrade - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Feature-free framework upgrade. IRMS v1.0 must run unchanged on Laravel 13 — no user-visible behavior change, no broadcast payload drift, no queued-job corruption under mixed-version workers. Every downstream FRAS phase (18-22) absorbs framework churn independently from feature churn. No FRAS feature code lands in this phase.

Scope: `laravel/framework` 12 → 13, aligned PHP-side package bumps (Horizon, Reverb, Fortify, Wayfinder, Tinker, Inertia-Laravel), middleware rename, cache serialization config, Fortify feature lockdown, payload regression tests, documented rollback protocol.

</domain>

<decisions>
## Implementation Decisions

### Package compatibility fallback
- **D-01:** At plan time, verify each upstream package's current release declares `^13.0` in its `composer.json` (priority: `laravel/horizon` and `clickbar/laravel-magellan`; also `laravel/reverb`, `laravel/fortify`, `laravel/wayfinder`, `laravel/tinker`, `inertiajs/inertia-laravel`, `laravel/boost`).
- **D-02:** If any priority package (Horizon, Magellan) has NOT released an L13-compatible minor, **block the phase** — do not fork, do not pin older + override, do not open upstream PRs mid-milestone. Resume when upstream ships.
- **D-03:** Installed baseline at discuss time: `laravel/framework 12.54.1` (target `13.5.0`), `laravel/horizon 5.45.3` (target `^6.0` if released, else `^5.x` with L13 declaration), `clickbar/laravel-magellan 2.0.1` (target confirm L13 in changelog), `laravel/tinker 2.11.1` → `^3.0`, `laravel/reverb 1.8.0` → `^1.10`, `laravel/fortify 1.36.1` → L13-compat release.

### Upgrade sequencing
- **D-04:** Upgrade in three atomic commits so `git bisect` stays useful:
  1. **Commit 1 — Pre-upgrade snapshot capture:** Add Pest tests on L12 that snapshot the JSON output of all 6 broadcast events (see D-08 for event list). These become the golden regression fixtures. Commit with tests green on L12.
  2. **Commit 2 — Framework + structural changes:** Bump `laravel/framework` to `^13.0`, rename `VerifyCsrfToken` → `PreventRequestForgery` in `bootstrap/app.php` + any tests, add `'serializable_classes' => false` to `config/cache.php`, bump `laravel/tinker` → `^3.0`, bump PHP floor in `composer.json` from `^8.2` → `^8.3`. Run full Pest suite — must go green.
  3. **Commit 3 — Aligned package bumps:** Bump `laravel/horizon`, `laravel/reverb`, `laravel/fortify`, `laravel/wayfinder`, `inertiajs/inertia-laravel`, `laravel/boost` to their L13-compatible releases. Run full Pest suite — must go green; all 6 broadcast snapshots must still match byte-for-byte.
- **D-05:** Do NOT run `composer update --with-all-dependencies` in a single step — keeps bisect granular if something breaks.

### Broadcast payload lock-in
- **D-06:** **Snapshot tests only** — do NOT refactor broadcastWith() arrays to Resource classes in this phase (Resource refactor is deferred; tracked under Deferred Ideas). Refactoring would violate the feature-free-upgrade guardrail and inflate the diff.
- **D-07:** Snapshot strategy: dedicated Pest test per event that constructs the event from a factory-built model, calls `broadcastWith()`, and asserts byte-identical JSON against a committed golden fixture. Fixtures live alongside the tests in `tests/Feature/Broadcasting/__snapshots__/` (or equivalent Pest convention).
- **D-08:** Events to snapshot (6, matching FRAMEWORK-02 + Phase 17 Success Criterion 2): `IncidentCreated`, `IncidentTriaged` (emitted via `IncidentStatusChanged` when transitioning to TRIAGED), `UnitAssigned` (emitted via `AssignmentPushed`), `UnitStatusChanged`, `ChecklistUpdated`, `ResourceRequested`. Researcher must confirm event-class mapping against `app/Events/` and the v1.0 shipped events list.

### Rollback + regression gate
- **D-09:** Rollback trigger: ANY failing v1.0 Pest test post-deploy OR any broadcast payload mismatch OR any queued-job execution failure under the new worker.
- **D-10:** Rollback procedure (documented in `docs/operations/laravel-13-upgrade.md` as part of this phase): (1) `horizon:terminate` + drain queue, (2) `git revert` the Phase 17 commit range, (3) `composer install` to restore L12 lockfile, (4) restart Horizon. Documentation MUST include the drain-before-deploy steps from FRAMEWORK-03.
- **D-11:** Regression gate scope = full v1.0 Pest suite green + 6 broadcast snapshot assertions match + `php artisan horizon:status` healthy. Do NOT add new sidebar-per-role or Fortify regression tests in this phase (PITFALLS recommends them but they expand scope — deferred to a separate small phase or folded into Phase 20 when Fortify surface actually changes).
- **D-12:** No staging-UAT-replay gate. A spot-check of the v1.0 dispatch flow (Report → Triage → Dispatch → ACK → OnScene → Resolve) is sufficient for Success Criterion 4.

### Claude's Discretion
- Exact Pest fixture location/naming convention (sibling to test, under `__snapshots__/`, or inline via `spectator`-style) — planner picks what matches existing IRMS Pest conventions.
- Composer lockfile regeneration strategy per commit (whether to `composer update <package>` individually vs let Composer resolve the constraint bump).
- How `docs/operations/laravel-13-upgrade.md` is structured (runbook style vs checklist style) — follow conventions if any `docs/operations/` files exist; otherwise planner chooses.
- Whether to add a one-off `composer audit` step post-upgrade (not required by success criteria).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 17 goal, requirements, success criteria
- `.planning/ROADMAP.md` §Phase 17 — goal, depends-on, 5 success criteria, requirements list
- `.planning/REQUIREMENTS.md` FRAMEWORK-01, FRAMEWORK-02, FRAMEWORK-03 (lines 27-29) — acceptance criteria

### Stack decisions (packages + versions)
- `.planning/research/STACK.md` §Framework Upgrade (Laravel 12 → 13) — version matrix, middleware rename, cache config, Tinker bump
- `.planning/research/STACK.md` §Packages and Their Compatibility (Laravel 12 and Laravel 13) — compat flags for Horizon, Magellan
- `.planning/research/STACK.md` §Laravel 13 references — laravel.com/docs/13.x/releases, /upgrade, /filesystem, /migrations, /horizon

### Risks + pre/post regression strategy
- `.planning/research/PITFALLS.md` §Pitfall 1 — bundled upgrade + feature work (enforces feature-free Phase 17)
- `.planning/research/PITFALLS.md` §Inertia v2/v3 divergence — stay on v2, pin explicitly
- `.planning/research/PITFALLS.md` §Fortify upgrade shared-prop regression (#19) — sidebar snapshot test rationale (deferred per D-11, but context for planner)
- `.planning/research/PITFALLS.md` §v1.0 broadcast payload regression (#24) — snapshot-per-event strategy
- `.planning/research/PITFALLS.md` §Deploy rollback plan (Phase 17) — rollback must include Horizon payload migration back to L12 format

### Synthesis
- `.planning/research/SUMMARY.md` §Phase 17 — cross-researcher alignment on feature-free upgrade, drain-before-deploy, Fortify feature pinning

### Code under change (for researcher/planner orientation)
- `composer.json` — current deps at `laravel/framework ^12.0`, `php ^8.2`
- `bootstrap/app.php` — middleware registration (CSRF middleware rename lands here)
- `config/cache.php` — add `serializable_classes` key
- `app/Events/` — 9 broadcast event classes (6 are v1.0-shipped and subject to snapshot testing)
- `phpunit.xml` — existing `BROADCAST_CONNECTION=reverb` test env pattern (extend for snapshot tests)

### Carried milestone-level decisions (from PROJECT.md STATE.md accumulated context)
- `.planning/STATE.md` §Accumulated Context §Decisions "v2.0 roadmap-level decisions (2026-04-21)" — MQTT under dedicated Supervisor (not Horizon), mapbox-gl rejected, Inertia v2 retained, UUID PKs on FRAS tables

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Existing broadcast event test pattern:** `phpunit.xml` already declares `BROADCAST_CONNECTION=reverb` with test credentials; Phase 3 established the pattern for exercising events in tests. Extend this for snapshot tests — same env config, new assertion style.
- **`config/integrations.php` + service-layer pattern** (Phase 6, Phase 2): used for stubbed connectors with config-driven behavior. Pattern reusable if any L13 adapter shim is needed, though none is anticipated.
- **PHP 8.4.19 already installed** on the host (Herd) — only composer.json floor bump is needed (`^8.2` → `^8.3`); runtime already satisfies.

### Established Patterns
- **`ShouldBroadcast, ShouldDispatchAfterCommit` dual-interface** on every event class (confirmed across all 9 events in `app/Events/`). L13 upgrade must preserve — verify no deprecation.
- **`broadcastWith(): array` return type** used consistently in all 6 snapshot-target events. Snapshot tests hook here, not at `broadcastAs()` or channel auth.
- **`HandleInertiaRequests` shared sidebar props** (Phase 1) — PITFALLS flags this as Fortify-upgrade risk surface; regression tests NOT added in this phase (D-11) but planner should be aware the surface exists if Fortify bump unexpectedly changes User serialization.
- **Atomic commit + Pest-green-per-commit discipline** — shipped across all 16 v1.0 phases; matches D-04 three-commit sequencing.
- **No `.env` secrets in tests** — use `phpunit.xml` env overrides (Reverb pattern from Phase 3). Snapshot tests follow the same convention.

### Integration Points
- `bootstrap/app.php` — middleware rename (`VerifyCsrfToken` → `PreventRequestForgery`). Grep confirmed no current references in `bootstrap/` or `app/`; change is a one-liner if/when referenced.
- `config/cache.php` — additive: `'serializable_classes' => false`. Low blast radius (IRMS uses `database` cache store).
- `composer.json` — PHP floor + framework + tinker + priority packages. Lockfile regenerated per commit.
- `docs/operations/` — NEW directory (does not exist yet). Rollback runbook lives here; planner creates the directory.
- `tests/Feature/Broadcasting/` — NEW test directory (pattern follows existing Feature tests). Snapshot fixtures live under `__snapshots__/` sibling.

### Known open-at-upgrade-time flags
- `laravel/horizon ^6.0` not yet verified as released with L13 declaration (STATE.md blocker).
- `clickbar/laravel-magellan ^2.0` L13 declaration to confirm in changelog (STATE.md blocker).
- Resolution per D-02: if neither declares `^13.0`, block the phase until they do.

</code_context>

<specifics>
## Specific Ideas

- **"Framework first, packages second, snapshots first of all"** — three-commit order mirrors the research's "minimize blast radius" posture and lets bisect point at the exact package whose bump broke something.
- **"Byte-identical JSON"** is the payload regression bar (not just "shape-identical"). Snapshot tests compare serialized JSON strings, not PHP arrays, because broadcast Reverb wire format is what citizens' dispatcher/responder browsers actually receive.
- **Drain Horizon before deploy** — non-negotiable per FRAMEWORK-03. Mixed-version workers can corrupt queued job payloads. Documented in runbook.

</specifics>

<deferred>
## Deferred Ideas

- **Refactor broadcast events to Resource classes** (typed payload shape via dedicated Resource class per event) — PITFALLS recommends this for long-term payload drift prevention. Deferred to a post-v2.0 phase; would inflate Phase 17 diff and violate feature-free posture.
- **Sidebar-per-role + Fortify-features regression Pest tests** — PITFALLS #19 recommends; adds safety net against Fortify shared-prop drift. Deferred to a separate small phase OR folded into Phase 20 (`Camera + Personnel Admin`) if Fortify surface actually changes.
- **CI bundle-check for `mapbox-gl`** — enforcement begins Phase 20 per v2.0 roadmap decisions. Not part of Phase 17 (no frontend bundling changes in this phase).
- **Staging-deployment UAT-replay gate** — higher-confidence rollout path (staging → v1.0 UAT script replay → prod promote). Not needed for Phase 17 per D-12; reconsider if a later phase touches dispatch-critical surface.
- **Inertia v3 migration** — explicitly deferred to a separate milestone per v2.0 roadmap decisions. Phase 17 keeps `inertiajs/inertia-laravel` on `^2.x`.
- **Passkey / WebAuthn auth surface** — PITFALLS warns that Fortify bumps can auto-surface passkey UI. Phase 17 pins Fortify features to exclude passkey explicitly; actual passkey rollout is a future phase with its own UAT.

</deferred>

---

*Phase: 17-laravel-12-13-upgrade*
*Context gathered: 2026-04-21*
