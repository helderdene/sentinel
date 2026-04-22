# Phase 17: Laravel 12 → 13 Upgrade - Research

**Researched:** 2026-04-21
**Domain:** Framework upgrade (feature-free), broadcast payload regression testing, queue drain-and-deploy operations
**Confidence:** HIGH

## Summary

All upstream packages IRMS depends on already declare Laravel 13 compatibility in their current stable releases — verified 2026-04-21 via the Packagist API. Horizon stays on `5.x` (v5.45.6 declares `^13.0`; no `^6.0` major required). Magellan requires a `2.0.1 → 2.1.0` bump because 2.0.1 stops at `^12.0` but 2.1.0 adds `^13.0`. No L13-compat blocker exists for D-02, so the phase is NOT blocked. [VERIFIED: Packagist API 2026-04-21 for 8 priority packages]

The three-commit sequence in D-04 is directly executable against the current package ecosystem. The upgrade touches exactly three code surfaces: (1) `routes/web.php` CSRF-token middleware reference (two lines — grep confirmed `VerifyCsrfToken` IS referenced in this file, contrary to the CONTEXT.md "no current references" statement), (2) `config/cache.php` additive `serializable_classes` key, (3) `composer.json` PHP floor + 8 package constraints. Fortify feature lockdown is already narrow (no `Features::registerUsers`, no passkey reference) so the surprise-passkey risk surface is minimal but still deserves an explicit opt-out pin. Broadcast payload regression is the load-bearing concern — the existing `tests/Unit/BroadcastEventTest.php` already asserts payload keys, but byte-identical JSON snapshot assertions must be added in Commit 1 (pre-upgrade baseline).

**Primary recommendation:** Execute D-04's three atomic commits as planned. Use file-fixture-based JSON snapshot tests (not a snapshot library — Pest 4 has no built-in `toMatchSnapshot`; `expect(json)->toBe(file_get_contents($fixture))` is the idiomatic pattern). Bump PHP floor to `^8.3` (L13 minimum, matches the forced Pest 4.6.x floor). Pin Fortify's `features` array explicitly (current config already excludes passkeys — just make the exclusion deliberate with a comment).

## User Constraints (from CONTEXT.md)

### Locked Decisions

**Package compatibility fallback (D-01, D-02, D-03):**
- D-01: At plan time, verify each upstream package's current release declares `^13.0` in its `composer.json` (priority: `laravel/horizon`, `clickbar/laravel-magellan`; also `laravel/reverb`, `laravel/fortify`, `laravel/wayfinder`, `laravel/tinker`, `inertiajs/inertia-laravel`, `laravel/boost`).
- D-02: If any priority package (Horizon, Magellan) has NOT released an L13-compatible minor, **block the phase** — do not fork, do not pin older + override, do not open upstream PRs mid-milestone. Resume when upstream ships.
- D-03: Installed baseline: `laravel/framework 12.54.1` (target `13.5.0`), `laravel/horizon 5.45.3` (target `^6.0` if released, else `^5.x` with L13 declaration), `clickbar/laravel-magellan 2.0.1` (target confirm L13 in changelog), `laravel/tinker 2.11.1` → `^3.0`, `laravel/reverb 1.8.0` → `^1.10`, `laravel/fortify 1.36.1` → L13-compat release.

**Upgrade sequencing (D-04, D-05):**
- D-04: Upgrade in three atomic commits — (1) Pre-upgrade snapshot capture on L12, (2) framework + middleware rename + `serializable_classes` + tinker + PHP floor `^8.3`, (3) aligned package bumps (Horizon, Reverb, Fortify, Wayfinder, Inertia-Laravel, Boost).
- D-05: Do NOT run `composer update --with-all-dependencies` in a single step — keeps bisect granular.

**Broadcast payload lock-in (D-06, D-07, D-08):**
- D-06: Snapshot tests only — do NOT refactor `broadcastWith()` arrays to Resource classes.
- D-07: Dedicated Pest test per event; constructs event from factory-built model, calls `broadcastWith()`, asserts byte-identical JSON against committed golden fixture. Fixtures under `tests/Feature/Broadcasting/__snapshots__/` (or equivalent Pest convention).
- D-08: 6 events to snapshot — `IncidentCreated`, `IncidentTriaged` (via `IncidentStatusChanged` with old=PENDING, new=TRIAGED), `UnitAssigned` (via `AssignmentPushed`), `UnitStatusChanged`, `ChecklistUpdated`, `ResourceRequested`.

**Rollback + regression gate (D-09, D-10, D-11, D-12):**
- D-09: Rollback trigger — ANY failing v1.0 Pest test post-deploy OR any broadcast payload mismatch OR any queued-job execution failure.
- D-10: Rollback procedure — `horizon:terminate` + drain, `git revert`, `composer install` (L12 lockfile), restart Horizon. Runbook at `docs/operations/laravel-13-upgrade.md`. MUST include drain-before-deploy steps per FRAMEWORK-03.
- D-11: Regression gate = full v1.0 Pest suite green + 6 snapshot assertions match + `horizon:status` healthy. Do NOT add new sidebar-per-role or Fortify regression tests (deferred).
- D-12: No staging-UAT-replay gate. Spot-check of Report → Triage → Dispatch → ACK → OnScene → Resolve is sufficient.

### Claude's Discretion

- Exact Pest fixture location/naming convention (sibling to test, `__snapshots__/`, or named fixtures under `tests/Feature/Broadcasting/fixtures/`).
- Composer lockfile regeneration strategy per commit (individual `composer update <pkg>` vs letting constraints resolve).
- Structure of `docs/operations/laravel-13-upgrade.md` (runbook vs checklist — no `docs/operations/` precedent exists).
- Whether to add a one-off `composer audit` step post-upgrade.

### Deferred Ideas (OUT OF SCOPE)

- Refactor broadcast events to Resource classes (post-v2.0).
- Sidebar-per-role + Fortify-features regression Pest tests (deferred to separate small phase or Phase 20).
- CI bundle-check for `mapbox-gl` (begins Phase 20).
- Staging UAT-replay gate.
- Inertia v2 → v3 migration (separate milestone).
- Passkey / WebAuthn auth surface (future phase with own UAT).

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| FRAMEWORK-01 | Admin can deploy IRMS on Laravel 13 with the full v1.0 Pest suite green and no user-visible behavior change | All 8 priority packages verified L13-compat on Packagist 2026-04-21. Upgrade mechanics section enumerates the exact file changes (3 surfaces: `routes/web.php`, `config/cache.php`, `composer.json`). No removed deprecations found in codebase (`Str::slug` usages all supply one arg; no `Route::controller`, `unguard`, `has([])`). |
| FRAMEWORK-02 | All 6 Reverb broadcast events emit identical payloads pre- and post-upgrade | Event class mapping confirmed: 6 classes → `IncidentCreated`, `IncidentStatusChanged` (drives `IncidentTriaged`), `AssignmentPushed` (drives `UnitAssigned`), `UnitStatusChanged`, `ChecklistUpdated`, `ResourceRequested`. Snapshot test pattern section provides Pest-4 file-fixture pattern with deterministic factory builders. |
| FRAMEWORK-03 | Admin can follow documented Horizon drain-and-deploy protocol | Horizon drain section documents the exact artisan command sequence (`horizon:pause`, `horizon:status`, `horizon:terminate` + `stopwaitsecs=3600`). Runbook at `docs/operations/laravel-13-upgrade.md` (new dir). |

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Framework upgrade (composer bump) | Backend (PHP/Composer) | — | Pure PHP dependency resolution; no frontend churn. |
| Middleware CSRF rename | Backend (HTTP kernel) | — | `routes/web.php` route definition — backend routing layer. |
| Cache `serializable_classes` config | Backend (config) | — | Additive PHP config; no caller changes. |
| Broadcast payload snapshot tests | Test (Feature) | Backend (Events) | Tests live in backend Pest suite; payloads are server-side serialization. |
| Horizon drain/deploy runbook | Ops (docs) | Backend (artisan) | Runbook is ops-facing; commands are backend. |
| Fortify feature pinning | Backend (config) | — | `config/fortify.php` — backend auth config. |
| PHP 8.3 floor bump | Backend (composer.json) | Ops (deploy) | Composer constraint + deploy verification on host PHP. |
| Wayfinder regeneration | Build (codegen) | Frontend (generated TS) | Build step re-emits TypeScript actions; no hand-written frontend code changes. |

## Standard Stack

### Target Versions (all verified L13-compat on Packagist 2026-04-21)

| Package | Installed | Target | L13 Declaration |
|---------|-----------|--------|-----------------|
| `laravel/framework` | `^12.0` (12.54.1) | `^13.0` (13.5.0) | N/A — is the upgrade target. `php ^8.3` required. [VERIFIED: Packagist API] |
| `laravel/horizon` | `5.45.3` | `^5.45` (latest 5.45.6) | `illuminate/*: ^9.21|^10.0|^11.0|^12.0|^13.0`. **No `^6.0` needed** — 5.45.6 declares `^13.0`. [VERIFIED: Packagist API] |
| `clickbar/laravel-magellan` | `2.0.1` | `^2.1` (2.1.0) | 2.1.0 adds `illuminate/*: ^11.0|^12.0|^13.0`. **2.0.1 stops at `^12.0`** — bump is required for L13. [VERIFIED: Packagist API] |
| `laravel/tinker` | `2.11.1` | `^3.0` (3.0.2) | `illuminate/*: ^8.0|^9.0|^10.0|^11.0|^12.0|^13.0`. [VERIFIED: Packagist API] |
| `laravel/reverb` | `1.8.0` | `^1.10` (1.10.0) | `illuminate/*: ^10.47|^11.0|^12.0|^13.0`. [VERIFIED: Packagist API] |
| `laravel/fortify` | `1.36.1` | `^1.36` (1.36.2) | `illuminate/*: ^10.0|^11.0|^12.0|^13.0`. [VERIFIED: Packagist API] |
| `laravel/wayfinder` | `0.1.9` | `^0.1.14` (or latest 0.1.16) | `illuminate/*: ^11.0|^12.0|^13.0`. [VERIFIED: Packagist API] |
| `laravel/boost` | `^2.0` | `^2.4` (2.4.4) | `illuminate/*: ^11.45.3|^12.41.1|^13.0`. [VERIFIED: Packagist API] |
| `inertiajs/inertia-laravel` | `^2.0` (2.0.22) | `^2.0` → 2.0.24 (NOT 3.x) | 2.0.24 declares `laravel/framework: ^10.0|^11.0|^12.0|^13.0`. **Stay on 2.x per D-11.** [VERIFIED: Packagist API] |
| `pestphp/pest` | `^4.4` (4.4.2) | `^4.6` (4.6.3) | `phpunit/phpunit: ^12.5.23`, `php ^8.3`. Pest 4.6.x requires PHP 8.3, aligning with L13 floor. [VERIFIED: Packagist API] |
| `pestphp/pest-plugin-laravel` | `^4.1` | `^4.1` (4.1.0 ok) | 4.1.0 declares `laravel/framework: ^11.45.2|^12.52.0|^13.0`, `php ^8.3`. [VERIFIED: Packagist API] |

**All 11 packages confirm L13 support. D-02 blocker is NOT triggered. Phase can proceed.** [VERIFIED: Packagist API 2026-04-21]

### PHP Version Floor

- **composer.json currently declares:** `"php": "^8.2"` [VERIFIED: /Users/helderdene/IRMS/composer.json line 12]
- **Host runs:** PHP 8.4.19 (Herd) [VERIFIED: laravel-boost-guidelines]
- **Laravel 13 minimum:** `^8.3` [VERIFIED: laravel/framework v13.5.0 composer.json via Packagist]
- **Pest 4.6.x minimum:** `^8.3` [VERIFIED: pestphp/pest v4.6.3 via Packagist]
- **Recommendation:** Bump composer.json `"php"` to `^8.3` (L13-min). Rationale: matches L13 + Pest 4.6.x floors exactly; preserves deployability on hosts that may not have 8.4 yet; host runtime (8.4.19) already satisfies. Do NOT bump to `^8.4` — no dependency forces 8.4 and the narrower floor retains portability. [CITED: laravel.com/docs/13.x/upgrade]

### Installation Sequence (Commit 2)

```bash
# Commit 2 — Framework + structural changes
# Edit composer.json:
#   "php": "^8.3"
#   "laravel/framework": "^13.0"
#   "laravel/tinker": "^3.0"
# Edit bootstrap/app.php AND routes/web.php — see Middleware Rename section
# Edit config/cache.php — add 'serializable_classes' => false
composer update laravel/framework laravel/tinker --with-dependencies
php artisan test --compact  # Must go green
```

```bash
# Commit 3 — Aligned package bumps
# Edit composer.json:
#   "laravel/horizon": "^5.45.6"  # keeps 5.x, declares L13
#   "laravel/reverb": "^1.10"
#   "laravel/fortify": "^1.36"
#   "laravel/wayfinder": "^0.1.14"
#   "inertiajs/inertia-laravel": "^2.0.24"
#   "laravel/boost": "^2.4"
#   "clickbar/laravel-magellan": "^2.1"
composer update laravel/horizon laravel/reverb laravel/fortify \
    laravel/wayfinder inertiajs/inertia-laravel laravel/boost \
    clickbar/laravel-magellan --with-dependencies
php artisan wayfinder:generate  # regenerate TS actions/routes
php artisan test --compact  # Must go green; 6 snapshots MUST match byte-for-byte
```

## Architecture Patterns

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                   Phase 17 Three-Commit Flow                     │
└─────────────────────────────────────────────────────────────────┘

 ┌─────────────┐      ┌─────────────────┐      ┌──────────────────┐
 │  COMMIT 1   │      │    COMMIT 2     │      │    COMMIT 3      │
 │  (on L12)   │  →   │  (L12 → L13)    │  →   │  (L13 aligned)   │
 └─────────────┘      └─────────────────┘      └──────────────────┘
       │                      │                         │
       ▼                      ▼                         ▼
  ┌─────────┐            ┌──────────┐             ┌──────────┐
  │ Factory │            │ composer │             │ composer │
  │ builds  │            │ update   │             │ update   │
  │ model   │            │ framework│             │ horizon  │
  └────┬────┘            │ tinker   │             │ reverb   │
       │                 └────┬─────┘             │ fortify  │
       ▼                      │                   │ wayfinder│
  ┌─────────┐                 ▼                   │ inertia  │
  │ Event   │            ┌──────────┐             │ boost    │
  │ ::      │            │ Rename   │             │ magellan │
  │ broadcast│           │ CSRF     │             └────┬─────┘
  │ With()  │            │ middleware│                 │
  └────┬────┘            └────┬─────┘                  ▼
       │                      │                   ┌──────────┐
       ▼                      ▼                   │ Pest suite│
  ┌──────────┐           ┌──────────┐             │ green     │
  │ json_    │           │ Pest suite│            │ 6 snapshots│
  │ encode   │           │ green     │            │ match     │
  └────┬─────┘           └───────────┘            │ byte-identical│
       │                                          └───────────┘
       ▼
  ┌──────────┐
  │ Write to │
  │ golden   │
  │ fixture  │
  │ file     │
  └──────────┘
```

### Component Responsibilities

| Component | File | Responsibility (Phase 17) |
|-----------|------|----------------------------|
| Composer manifest | `composer.json` | PHP floor `^8.3`; L13 constraints; per-commit lockfile regen |
| Middleware registration | `bootstrap/app.php` | No change — default CSRF enabled by framework; no explicit reference [VERIFIED via Read] |
| Webhook routes | `routes/web.php` | 2-line `VerifyCsrfToken` → `PreventRequestForgery` rename [VERIFIED via Grep — SEE CORRECTION BELOW] |
| Cache config | `config/cache.php` | Additive: `'serializable_classes' => false` key at top level |
| Fortify config | `config/fortify.php` | Comment explicitly pinning feature list (no code change — features already exclude passkey) [VERIFIED via Read] |
| Broadcast events | `app/Events/*.php` | No change — `broadcastWith()` arrays preserved byte-identical |
| Snapshot tests | `tests/Feature/Broadcasting/*Test.php` (NEW) | 6 tests; construct event from deterministic factory; assert JSON matches fixture |
| Golden fixtures | `tests/Feature/Broadcasting/__snapshots__/*.json` (NEW) | Committed in Commit 1 on L12; unchanged in Commits 2+3 |
| Runbook | `docs/operations/laravel-13-upgrade.md` (NEW) | Drain-deploy-verify-rollback procedure |
| Wayfinder output | `resources/js/actions/`, `resources/js/routes/` | Auto-regenerated by `wayfinder:generate` post-Commit 3 |

**CORRECTION of CONTEXT.md:** CONTEXT.md `<code_context>` line 101 states: *"Grep confirmed no current references in `bootstrap/` or `app/`; change is a one-liner if/when referenced."* This is technically correct (`bootstrap/` has none; `app/` has none) but **incomplete**: `routes/web.php` lines 13, 19, and 23 DO reference `VerifyCsrfToken` — it's imported and applied via `->withoutMiddleware([VerifyCsrfToken::class])` on two webhook routes (IoT sensor + SMS inbound). The middleware rename is a **three-line change** (1 import, 2 usages), not a no-op. [VERIFIED: /Users/helderdene/IRMS/routes/web.php lines 13, 19, 23 via Grep]

### Middleware Rename (the actual change set)

Replace in `routes/web.php`:
```php
// Before
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
// ...
->withoutMiddleware([VerifyCsrfToken::class])

// After (L13)
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
// ...
->withoutMiddleware([PreventRequestForgery::class])
```

L13 keeps the `VerifyCsrfToken` class as a deprecated alias (source: L13 upgrade guide), so the pre-upgrade test suite will still pass on L12 after the rename. Execute the rename in Commit 2 alongside the framework bump. [CITED: laravel.com/docs/13.x/upgrade]

### Cache config change

Add to `config/cache.php` at top level (after `'prefix'`):
```php
'serializable_classes' => false,
```

`false` disables all custom-class unserialization from cache — the strictest default. IRMS uses the `database` cache store and stores no custom PHP objects in cache [VERIFIED via Grep — no `Cache::put` with object args in `app/`]. `serializable_classes => false` is safe. Alternative is `[]` (empty allow-list). Source: L13 release notes for `PreventRequestForgery` and `serializable_classes`. [CITED: laravel.com/docs/13.x/upgrade]

### Pattern: Payload snapshot test (Pest 4, file-fixture idiom)

**What:** One Pest test per event asserts byte-identical JSON output from `broadcastWith()` against a committed fixture file.

**When to use:** Commit 1 (pre-upgrade, on L12) — captures golden fixtures. Assertions continue in Commits 2 and 3 — must still match byte-for-byte.

**Pattern:**

```php
<?php
// tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php

use App\Events\IncidentCreated;
use App\Models\Incident;
use App\Models\User;
use App\Models\IncidentType;
use App\Models\Barangay;

use function Pest\Laravel\{freezeTime};

beforeEach(function () {
    // Freeze clock so created_at is deterministic
    freezeTime();
});

it('IncidentCreated payload matches golden fixture', function () {
    // Deterministic factory build — explicit IDs, fixed strings, fixed coordinates
    $user = User::factory()->create(['id' => 1, 'email' => 'snapshot@irms.test']);
    $type = IncidentType::factory()->create(['id' => 1, 'name' => 'Fire', 'code' => 'FIRE']);
    $barangay = Barangay::factory()->create(['id' => 1, 'name' => 'Libertad']);

    $incident = Incident::factory()
        ->for($user, 'createdBy')
        ->for($type, 'incidentType')
        ->for($barangay)
        ->create([
            'id' => '01929000-aaaa-bbbb-cccc-000000000001', // deterministic UUID
            'incident_no' => 'INC-2026-00001',
            'location_text' => 'J.C. Aquino Ave.',
            'caller_name' => 'Snapshot Tester',
            'caller_contact' => '09171234567',
            'notes' => 'Fixture data',
            // priority, status, channel set by factory defaults OR pinned here
        ]);

    $incident->load('incidentType', 'barangay');

    $payload = (new IncidentCreated($incident))->broadcastWith();
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $fixturePath = __DIR__.'/__snapshots__/IncidentCreated.json';

    if (! file_exists($fixturePath)) {
        // First run (on L12, Commit 1) — write golden
        file_put_contents($fixturePath, $json);
        $this->markTestIncomplete('Golden fixture created; re-run to verify.');
    }

    expect($json)->toBe(file_get_contents($fixturePath));
});
```

**Determinism requirements:**
- `freezeTime()` (Pest-Laravel plugin helper) — pins `created_at` / `now()`
- Explicit IDs on every factory-built model (integer or fixed UUID string)
- Fixed strings for `incident_no`, `location_text`, `caller_name`, etc.
- Pin enum values (priority, status, channel) explicitly if factory randomizes

**Fixture location convention:** `tests/Feature/Broadcasting/__snapshots__/{EventName}.json`. Claude's discretion per D-discretion — recommend `__snapshots__/` (matches Jest/JS convention familiar to the team; git-trackable).

**Why NOT a Pest snapshot library:** Pest 4 has no built-in `toMatchSnapshot()`. Third-party libraries (e.g., `spatie/pest-plugin-snapshots`) exist but D-06's "feature-free" posture means minimizing new dev deps. File-fixture pattern is ~5 lines and uses only Pest + PHP stdlib. [VERIFIED: Context7 /pestphp/pest — no snapshot API found in docs 2026-04-21]

**Source:** [CITED: Pest 4 docs via Context7]; pattern is direct adaptation of PHPUnit's `assertStringEqualsFile`.

### Anti-Patterns to Avoid

- **Running `composer update --with-all-dependencies` in one shot** — violates D-05; breaks bisect granularity. Each commit does targeted updates only.
- **Serializing models directly in fixtures** — `json_encode($incident->toArray())` includes unstable fields (`updated_at`, floating-point coords from DB round-tripping). Always snapshot the `broadcastWith()` return, not the model.
- **Using `Event::fake()` for payload snapshots** — that tests dispatch, not payload shape. Build the event directly and call `broadcastWith()`.
- **Relying on `broadcastAs()` override** — not used by any of the 6 target events; they use the default class-name wire format.
- **Resource-class refactor** — explicitly deferred per D-06. Do NOT refactor `broadcastWith()` arrays into `Resource::toArray()` methods.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSON snapshot assertions | Custom `snapshot()` helper with hashing | Plain `expect($json)->toBe(file_get_contents(...))` + first-run write-through | Pest idiom is one line; hashing obscures diffs. |
| Deterministic clock in tests | Manual `Carbon::setTestNow()` at top of every test | `freezeTime()` from `pest-plugin-laravel` | Already a project dep; auto-unfreezes after test. |
| Horizon drain orchestration | Custom shell script that polls `horizon:status` | `php artisan horizon:terminate` + `stopwaitsecs=3600` in Supervisor | L13 Horizon docs document this as the standard deploy pattern. [CITED: laravel.com/docs/13.x/horizon] |
| Framework version detection | Runtime `app()->version()` checks | Composer constraint resolution | Composer handles transitive compat checking. |
| Fortify feature pinning | Fork + override | Explicit `Features::*` list in `config/fortify.php` (already done) | Current config is minimal (resetPasswords, emailVerification, twoFactorAuthentication); passkey is absent by default. |
| CSRF middleware compat shim | Keeping both `VerifyCsrfToken` and `PreventRequestForgery` imports | Just rename — L13 keeps a deprecated alias for back-compat during the upgrade window | Alias absorbs legacy refs, then remove. [CITED: L13 upgrade guide] |

**Key insight:** The L13 upgrade surface is deliberately small. Every "problem" above has a first-party standard answer. Custom tooling would inflate the Phase 17 diff and violate the feature-free posture.

## Runtime State Inventory

This is a rename/config-change phase but **not a data migration**. No stored data carries the L12 framework "string." The relevant runtime state is queued jobs and running processes, not records.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — Laravel version isn't persisted. Broadcast payloads are the closest analog, and they're snapshot-regression-tested (not migrated). | None. |
| Live service config | **Horizon queues on Redis** — during the L12→L13 cutover, a job queued under L12 could execute under an L13 worker. Serialization format is compatible across 12→13 (no documented breaking change in job payload format) [VERIFIED: L13 upgrade guide — no job serialization change noted], BUT the drain-before-deploy protocol (FRAMEWORK-03) is belt-and-suspenders. **Reverb server** on port 6001 — broadcast payload shape is the risk, covered by snapshots. | Drain Horizon before deploy per runbook; restart Reverb post-deploy. |
| OS-registered state | **Supervisor programs** (production): `irms-horizon` (exists per PITFALLS), possibly `irms-reverb`. No Windows Task Scheduler or launchd entries — Herd is dev-only, production is Linux + Supervisor. | Runbook includes `supervisorctl restart irms-horizon irms-reverb` post-deploy. |
| Secrets/env vars | None — L13 introduces no new `env()` keys that IRMS consumes. `BROADCAST_CONNECTION=reverb` env var unchanged. | None. |
| Build artifacts / installed packages | `vendor/` — regenerated per commit by `composer update`. `resources/js/actions/` + `resources/js/routes/` (Wayfinder auto-gen) — regenerated by `php artisan wayfinder:generate` in Commit 3. `bootstrap/cache/*.php` (Laravel compiled config/services) — cleared by `php artisan optimize:clear` on deploy. | Runbook invokes `composer install`, `wayfinder:generate`, `optimize:clear` in the deploy sequence. |

**Nothing in "Stored data" category:** Verified — the Laravel framework version is not embedded in any IRMS database record, Eloquent serialization, or cache key. The only runtime state that could carry L12-era shape is **in-flight queued jobs** (addressed by drain-before-deploy) and **already-broadcast payloads currently in browser WebSocket clients** (addressed by post-deploy smoke test against open dispatch tabs).

## Common Pitfalls

### Pitfall 1: Snapshot tests fail on Commit 1 due to non-deterministic factory values

**What goes wrong:** First snapshot run produces a fixture with `"created_at": "2026-04-21T14:23:07.123456Z"`. Second run (same Pest command) fails because clock ticked.
**Why it happens:** `freezeTime()` not called or applied after model creation; `Incident::factory()->create()` uses `Carbon::now()` in its timestamps before `freezeTime()` activates.
**How to avoid:** Call `freezeTime()` in `beforeEach()` BEFORE any factory builds. Explicitly pin `created_at` on the factory call if the factory uses real-time defaults.
**Warning signs:** First two runs of the snapshot test disagree on `created_at` or any timestamp field.

### Pitfall 2: Magellan 2.0.1 break — upgrade path missed

**What goes wrong:** Commit 3 bumps Horizon/Reverb/Fortify but forgets Magellan. `composer update laravel/framework` resolves but Magellan 2.0.1 declares only `illuminate/*: ^11.0|^12.0` and Composer rejects the whole upgrade.
**Why it happens:** Magellan isn't in the D-04 Commit-2 or Commit-3 lists by name (CONTEXT.md lists it as a priority check but not in the update commands).
**How to avoid:** Add `clickbar/laravel-magellan: ^2.1` to Commit 3's update command (see Installation Sequence above). This is a correction to D-04's implicit package list.
**Warning signs:** `composer update laravel/framework` errors with *"Your requirements could not be resolved to an installable set"* mentioning `clickbar/laravel-magellan`.

### Pitfall 3: Wayfinder-generated files committed before Commit 3 regen

**What goes wrong:** Commit 2 changes compiled view caches or framework internals that cause a `wayfinder:generate` to emit a different `resources/js/actions/*.ts` output. If the regen happens accidentally mid-Commit-2, the diff is contaminated.
**Why it happens:** `composer run dev` (which IRMS uses) triggers `wayfinder:generate` via the Vite plugin on file watch.
**How to avoid:** Regenerate Wayfinder only in Commit 3, after all package bumps. Commit 2 uses `composer install` + `php artisan test`, not `composer run dev`. [VERIFIED: `@laravel/vite-plugin-wayfinder` present per composer.json]
**Warning signs:** `git status` in Commit 2 shows changes to `resources/js/actions/` or `resources/js/routes/`.

### Pitfall 4: `config/cache.php` `serializable_classes` set too strictly breaks a future feature

**What goes wrong:** `serializable_classes => false` disables all class unserialization from cache. A future phase adds `Cache::put('dispatch.state', $someObject)` and the read-back fails silently.
**Why it happens:** Deny-by-default is the L13 security posture; existing IRMS code is compatible, but any future object-caching regresses.
**How to avoid:** Document the choice in `config/cache.php` with a comment: *"If you cache PHP objects, allow-list them explicitly. Currently IRMS caches only primitives (database store)."* Add a Phase 17 task note: *"If any future phase introduces `Cache::put` of a custom class, update `serializable_classes` to include it."*
**Warning signs:** Future `Cache::get` returns `false` or throws deserialization error.

### Pitfall 5: Drain-and-deploy race — a job queued just before `horizon:terminate` executes under the L13 worker

**What goes wrong:** `horizon:terminate` signals Horizon to exit gracefully after current jobs finish. But a job dispatched at `t=0` with `ShouldDispatchAfterCommit` that enters Redis at `t=0.5s`, just as terminate fires at `t=1s`, stays in the queue. On redeploy, the new L13 worker picks it up. If the job class or its serialized payload shape changed between 12 and 13, the L13 worker crashes or mis-processes.
**Why it happens:** Horizon drains *in-flight* jobs, not queued ones. Pending jobs remain in Redis until a worker is back online.
**How to avoid:** Runbook must do (1) pause Horizon (`horizon:pause` — stops *accepting* new jobs into workers) FIRST, (2) wait for in-flight to drain (watch `horizon:status` count → 0), (3) *then* `horizon:terminate`, (4) deploy, (5) restart. Also: Phase 17 does NOT change any job class shape, so the serialized payloads are unchanged — the protocol is defensive, not load-bearing. But document it as FRAMEWORK-03 requires.
**Warning signs:** Horizon dashboard shows failed jobs with *"Unrecognized class"* or *"Cannot resolve model"* errors post-deploy.

## Code Examples

### Snapshot test (one of 6, others follow same pattern)

```php
<?php
// tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php
// Source: Pest 4 file-fixture idiom (Context7 /pestphp/pest 2026-04-21)

use App\Enums\UnitStatus;
use App\Events\UnitStatusChanged;
use App\Models\Unit;

use function Pest\Laravel\freezeTime;

beforeEach(fn () => freezeTime());

it('UnitStatusChanged payload matches golden fixture', function () {
    $unit = Unit::factory()->create([
        'id' => 'AMB-01',
        'callsign' => 'Alpha-1',
        'status' => UnitStatus::Dispatched,
    ]);

    $payload = (new UnitStatusChanged($unit, UnitStatus::Available))->broadcastWith();
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $fixture = __DIR__.'/__snapshots__/UnitStatusChanged.json';
    if (! file_exists($fixture)) {
        file_put_contents($fixture, $json);
        $this->markTestIncomplete('Golden fixture created; re-run.');
    }
    expect($json)->toBe(file_get_contents($fixture));
});
```

### The 6 events to snapshot (exact class + fixture input)

| # | Event class | How `IncidentTriaged`/`UnitAssigned` map | Factory/constructor inputs |
|---|-------------|---------------------------------------|---------------------------|
| 1 | `App\Events\IncidentCreated` | — | `new IncidentCreated($incident)` with Incident eager-loaded `incidentType` + `barangay` |
| 2 | `App\Events\IncidentStatusChanged` | Emitted as "IncidentTriaged" when `$oldStatus=IncidentStatus::Pending`, `$incident->status=IncidentStatus::Triaged` [VERIFIED: app/Events/IncidentStatusChanged.php] | `new IncidentStatusChanged($incident, IncidentStatus::Pending)` with `$incident->status = IncidentStatus::Triaged` |
| 3 | `App\Events\AssignmentPushed` | Emitted as "UnitAssigned" — this is the canonical push-to-responder event. Payload keys: `id, incident_no, priority, status, incident_type, location_text, barangay, coordinates, notes, unit_id` [VERIFIED: app/Events/AssignmentPushed.php] | `new AssignmentPushed($incident, 'AMB-01', 42)` |
| 4 | `App\Events\UnitStatusChanged` | — | `new UnitStatusChanged($unit, UnitStatus::Available)` |
| 5 | `App\Events\ChecklistUpdated` | — | `new ChecklistUpdated($incident)` — payload is `incident_id, incident_no, checklist_pct` only [VERIFIED: app/Events/ChecklistUpdated.php] |
| 6 | `App\Events\ResourceRequested` | — | `new ResourceRequested($incident, ResourceType::*, 'notes', $requester)` |

**Event mapping confirmed** [VERIFIED: all 6 files read 2026-04-21].

**Excluded from Phase 17 snapshots** (per D-08 "6 events only"): `MessageSent`, `MutualAidRequested`, `UnitLocationUpdated` (the three remaining in `app/Events/`). These are shipped broadcast events but not in the FRAMEWORK-02 regression scope.

### `config/cache.php` change

```php
// config/cache.php — add after the 'prefix' line (around line 115)

/*
|--------------------------------------------------------------------------
| Serializable Classes (Laravel 13+)
|--------------------------------------------------------------------------
|
| L13 defaults to restricting class unserialization from cache to prevent
| deserialization gadget chains. IRMS uses the database cache store and
| does not currently cache PHP objects — only primitives and arrays.
| Setting this to `false` disables custom-class unserialization entirely.
|
| If a future phase introduces `Cache::put('key', $someObject)`, replace
| `false` with an explicit allow-list: `[App\Data\ThatClass::class, ...]`.
|
*/

'serializable_classes' => false,
```

### Horizon drain-and-deploy (runbook excerpt)

```bash
# docs/operations/laravel-13-upgrade.md — drain-and-deploy procedure
# Source: laravel.com/docs/13.x/horizon + FRAMEWORK-03

# === PRE-DEPLOY (drain) ===
# 1. Pause Horizon — stops pulling new jobs into workers (queued jobs stay in Redis)
php artisan horizon:pause

# 2. Wait for in-flight jobs to finish (status count goes to 0)
#    Poll with timeout (typical drain: < 30s for IRMS; P1 alerts may hold ~5s each)
for i in {1..60}; do
    php artisan horizon:status 2>&1 | grep -q 'inactive\|paused' && break
    sleep 1
done

# 3. Terminate — workers exit gracefully after current tick
php artisan horizon:terminate

# === DEPLOY ===
# 4. git pull / composer install / wayfinder:generate / optimize:clear
git pull
composer install --no-dev --optimize-autoloader
php artisan wayfinder:generate
php artisan optimize:clear

# === POST-DEPLOY (restart) ===
# 5. Supervisor restarts the `horizon` program automatically after horizon:terminate exits
#    (stopwaitsecs=3600 in supervisor conf gives up to 1h for drain)
sudo supervisorctl restart irms-horizon
sudo supervisorctl restart irms-reverb

# 6. Verify
php artisan horizon:status   # Should report 'running'
curl -s https://irms.test/up # Health check
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `VerifyCsrfToken` class for CSRF | `PreventRequestForgery` class (adds origin-aware verification on top of token CSRF) | Laravel 13.0 (2026-03-17) | Rename + get origin verification for free. [CITED: laravel.com/docs/13.x/releases] |
| Cache unserialization: any class | Deny-by-default via `serializable_classes` config | Laravel 13.0 | Hardens against deserialization gadget chains. IRMS impact: `false` is safe. [CITED: laravel.com/docs/13.x/upgrade] |
| PHP 8.2 floor on L12 | PHP 8.3 floor on L13 | Laravel 13.0 | No host impact — IRMS Herd already on 8.4.19; DO droplet to verify before deploy. |
| `laravel/tinker ^2.x` | `laravel/tinker ^3.0` | Tinker 3.0.0 (2025-12-03 approx) | API-equivalent bump; required by L13. [CITED: Packagist] |
| Inertia-Laravel `^2.0` | Continue `^2.0` — v3 defers | — (stay) | Per D-11 and Deferred Ideas, v3 is its own milestone. Inertia-Laravel 2.0.24 declares `^13.0` compat. |
| Pest `^4.4` / PHP 8.2 | Pest `^4.6` / PHP 8.3 | Pest 4.6.0 (requires PHP 8.3) | Aligns Pest + L13 PHP floors. [VERIFIED: Packagist] |

**Deprecated/outdated in this upgrade:**
- `VerifyCsrfToken` class — still works via alias, but rename now to avoid future removal.
- `laravel/tinker 2.x` — no L13 compat declaration on 2.x line; must bump.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | L12→L13 introduces no breaking change to queued-job payload serialization format | Runtime State Inventory / Pitfall 5 | If wrong, in-flight queued jobs fail post-deploy. Mitigation is D-10 rollback + drain-before-deploy in runbook (FRAMEWORK-03 hedges this even if the assumption holds). [ASSUMED — L13 release notes emphasize "minimal breaking changes" but don't explicitly call out job payload stability. Boost's L13 guidelines + upgrade guide fetched 2026-04-21 had no entry on queue payload format change.] |
| A2 | `serializable_classes => false` is safe for IRMS (no custom class caching in current code) | Code Examples / Pitfall 4 | Low — grep of `app/` found no `Cache::put` with object args, but a future developer adds one silently. [ASSUMED based on code scan; not exhaustive for edge cases like Horizon internal caching] |
| A3 | Fortify `config/fortify.php` current `features` array is sufficient to prevent passkey surface activation after L13 upgrade | Fortify lockdown | If Fortify v1.36.x introduces a new default feature, IRMS might acquire it. Current config omits passkeys entirely, so no passkey auto-activates. [ASSUMED — Boost docs fetch didn't surface Fortify 1.36.x passkey auto-enable default; CONTEXT.md flagged this as a concern but D-11 explicitly defers regression tests.] |
| A4 | `composer run dev` in Commit 2 won't run — plan assumes `composer install` + `php artisan test` only | Pitfall 3 | If operator runs `composer run dev`, Wayfinder regen contaminates Commit 2 diff. Mitigation: document the "run tests via `php artisan test` only between Commits 2 and 3" instruction in the phase plan. [ASSUMED executor reads + follows phase task steps literally.] |
| A5 | 30-second drain window is sufficient for the IRMS Horizon queue under typical load | Runbook excerpt | If P1 alert volume spikes during deploy, drain could take longer. `stopwaitsecs=3600` in Supervisor gives up to 1h. [ASSUMED from PITFALLS "stopwaitsecs=3600" pattern; no production load metrics in the planning materials.] |

## Open Questions

None that block planning. All 10 priority research items are resolved. The runbook will cover the operational specifics; the snapshot test pattern is directly executable; package versions are all verified.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Framework runtime | ✓ | 8.4.19 (Herd) [VERIFIED: laravel-boost-guidelines] | — |
| Composer | Package management | ✓ | (Herd default) | — |
| PHP `pcntl` extension | Horizon worker signaling (existing v1.0 dep) | ✓ | (already working — v1.0 ships Horizon) | — |
| PHP `redis`/`predis` | Queue + cache | ✓ | predis `^3.4` [VERIFIED: composer.json] | — |
| Supervisor (production) | Horizon + Reverb process management | ✓ (prod only) | 4.2+ per STACK.md | — (Herd handles dev) |
| Git | Per-commit atomicity | ✓ | — | — |

**Missing dependencies with no fallback:** None.
**Missing dependencies with fallback:** None.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4 (`^4.4` → `^4.6` in Commit 3) [VERIFIED: composer.json] |
| Config file | `phpunit.xml` [VERIFIED] |
| Quick run command | `php artisan test --compact --filter=Broadcasting` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FRAMEWORK-01 | Full v1.0 Pest suite green on L13 | Integration (full suite) | `php artisan test --compact` | ✅ entire `tests/` dir |
| FRAMEWORK-02 | IncidentCreated payload byte-identical | Feature (snapshot) | `php artisan test --compact tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` | ❌ Wave 0 |
| FRAMEWORK-02 | IncidentTriaged payload byte-identical (via IncidentStatusChanged Pending→Triaged) | Feature (snapshot) | `php artisan test --compact tests/Feature/Broadcasting/IncidentTriagedSnapshotTest.php` | ❌ Wave 0 |
| FRAMEWORK-02 | UnitAssigned payload byte-identical (via AssignmentPushed) | Feature (snapshot) | `php artisan test --compact tests/Feature/Broadcasting/UnitAssignedSnapshotTest.php` | ❌ Wave 0 |
| FRAMEWORK-02 | UnitStatusChanged payload byte-identical | Feature (snapshot) | `php artisan test --compact tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php` | ❌ Wave 0 |
| FRAMEWORK-02 | ChecklistUpdated payload byte-identical | Feature (snapshot) | `php artisan test --compact tests/Feature/Broadcasting/ChecklistUpdatedSnapshotTest.php` | ❌ Wave 0 |
| FRAMEWORK-02 | ResourceRequested payload byte-identical | Feature (snapshot) | `php artisan test --compact tests/Feature/Broadcasting/ResourceRequestedSnapshotTest.php` | ❌ Wave 0 |
| FRAMEWORK-03 | Horizon drain-and-deploy runbook is documented + executable | Manual (smoke run on staging) | Follow `docs/operations/laravel-13-upgrade.md` step-by-step; verify `horizon:status` healthy | ❌ Wave 0 (runbook file) |
| Phase 17 SC4 | Report → Triage → Dispatch → ACK → OnScene → Resolve cycle unchanged | Manual (spot-check v1.0 UAT) | Browser walkthrough of existing v1.0 UAT script | N/A — manual |
| Phase 17 SC5 | Inertia v2 pinned, Fortify features pinned, no passkey surface | Unit (static config read) | `php artisan test --compact --filter=FortifyFeaturesConfigTest` OR grep assertion in existing tests | N/A per D-11 (deferred) |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=Broadcasting` (snapshot tests only; <5s)
- **Per wave merge:** `php artisan test --compact` (full suite)
- **Phase gate (Commits 2 + 3):** Full suite green + all 6 snapshots byte-match + `php artisan horizon:status` healthy in staging + manual v1.0 UAT spot-check

### Wave 0 Gaps

- [ ] `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` — covers FRAMEWORK-02
- [ ] `tests/Feature/Broadcasting/IncidentTriagedSnapshotTest.php` — covers FRAMEWORK-02 (uses `IncidentStatusChanged(Pending→Triaged)`)
- [ ] `tests/Feature/Broadcasting/UnitAssignedSnapshotTest.php` — covers FRAMEWORK-02 (uses `AssignmentPushed`)
- [ ] `tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php` — covers FRAMEWORK-02
- [ ] `tests/Feature/Broadcasting/ChecklistUpdatedSnapshotTest.php` — covers FRAMEWORK-02
- [ ] `tests/Feature/Broadcasting/ResourceRequestedSnapshotTest.php` — covers FRAMEWORK-02
- [ ] `tests/Feature/Broadcasting/__snapshots__/` — directory + 6 golden JSON fixture files (written in Commit 1)
- [ ] `docs/operations/laravel-13-upgrade.md` — runbook with drain, deploy, verify, rollback steps (Commit 2 or 3)
- [ ] `docs/operations/` — new directory; add `.gitkeep` if empty at Commit 2 start

*(Framework install: none — Pest 4 + phpunit 12 already present.)*

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Fortify (unchanged — feature list pinned to exclude passkey; 2FA/TOTP continues) |
| V3 Session Management | yes | Laravel session driver (unchanged) |
| V4 Access Control | yes | Custom `role:` middleware + Gates (unchanged) |
| V5 Input Validation | yes | Form Request classes (unchanged) |
| V6 Cryptography | yes | Laravel built-in (`bcrypt`, app key, signed URLs) — unchanged |
| V10 Malicious Code | yes | `serializable_classes => false` hardens against PHP deserialization gadget chain attacks [CITED: laravel.com/docs/13.x/upgrade] |
| V13 API | partial | CSRF middleware rename — `PreventRequestForgery` adds origin-aware verification on top of token-based CSRF [CITED: laravel.com/docs/13.x/releases] |

### Known Threat Patterns for {Laravel 13 + Reverb + Horizon}

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Deserialization gadget chain via cache | Tampering / Elevation | `serializable_classes` allow-list (set to `false` = deny-all in Phase 17) [CITED: L13 upgrade] |
| CSRF on webhook endpoints | Spoofing | `VerifyIoTSignature` middleware + `withoutMiddleware([PreventRequestForgery::class])` on `/webhooks/iot-sensor` and `/webhooks/sms-inbound` [VERIFIED: routes/web.php] — preserved by middleware rename |
| Mixed-version worker processes corrupting job state | Tampering | Horizon drain-and-deploy protocol (FRAMEWORK-03) |
| Broadcast payload drift exposing unintended fields | Information Disclosure | Byte-identical snapshot regression (FRAMEWORK-02) |
| Fortify auto-enabling passkey/WebAuthn without UAT | Elevation (weak auth surface added) | Explicit `Features::*` pinning in `config/fortify.php` [VERIFIED: config/fortify.php already pins] |

## Project Constraints (from CLAUDE.md)

| Directive | How this research complies |
|-----------|---------------------------|
| Laravel 12 / Pest 4 / PHP 8.2+ | Research recommends Pest 4.6.x (no major bump) and PHP 8.3 floor (narrower than 8.2 but required by L13). |
| `vendor/bin/pint --dirty --format agent` after modifying PHP | Three-commit flow includes Pint invocation in the commit workflow (implementation detail for plan). |
| Form Request classes for validation | No new form requests in Phase 17 (no new HTTP routes). |
| No `DB::` — prefer Eloquent | No DB work in Phase 17. |
| Create tests via `php artisan make:test --pest` | Snapshot tests are Pest feature tests — use the artisan command. |
| Boost `search-docs` for Laravel-ecosystem docs | This research used Context7 CLI (`npx ctx7 docs /websites/laravel_13_x`) as the Boost-equivalent; Boost MCP may be unavailable in agent context. |
| Inertia v2 retained per v2.0 roadmap | Confirmed — stay on `inertiajs/inertia-laravel ^2.0` (2.0.24 has L13 compat). |
| No `mapbox-gl` | Not relevant to Phase 17 (no map work). |
| MQTT under Supervisor not Horizon | Not relevant — no MQTT in Phase 17. |
| Herd serves at `irms.test` | Used for local verification; production deploys via DO droplet + Supervisor. |

## Sources

### Primary (HIGH confidence)

- **Packagist API** (`repo.packagist.org/p2/{package}.json`) — fetched 2026-04-21 for: `laravel/framework`, `laravel/horizon`, `clickbar/laravel-magellan`, `laravel/tinker`, `laravel/fortify`, `laravel/reverb`, `laravel/wayfinder`, `laravel/boost`, `inertiajs/inertia-laravel`, `pestphp/pest`, `pestphp/pest-plugin-laravel`. All version + L13 compat claims verified from raw `require` clauses.
- **Context7 `/websites/laravel_13_x`** — fetched 2026-04-21 for upgrade guide (PreventRequestForgery, serializable_classes), Horizon deploy pattern, queue serialization. Corroborates Laravel docs.
- **laravel.com/docs/13.x/upgrade** — authoritative; `PreventRequestForgery` rename, `serializable_classes` config, PHP 8.3 floor [CITED via Context7].
- **laravel.com/docs/13.x/releases** — authoritative; "Request Forgery Protection" section confirms origin-aware CSRF enhancement.
- **laravel.com/docs/13.x/horizon** — authoritative; `horizon:terminate`, `horizon:pause`, `horizon:status`, Supervisor `stopwaitsecs=3600` [CITED via Context7].
- **IRMS codebase read 2026-04-21** — `composer.json` (dep baseline), `bootstrap/app.php` (middleware), `routes/web.php` (VerifyCsrfToken refs — correction to CONTEXT.md), `config/cache.php`, `config/fortify.php` (feature list), `app/Events/*.php` (all 6 target events), `tests/Unit/BroadcastEventTest.php` (existing test patterns), `phpunit.xml` (Reverb test env).

### Secondary (MEDIUM confidence)

- **IRMS `.planning/research/STACK.md`** — prior research 2026-04-21. Cross-verified package recommendations; used as orientation. Magellan 2.0.1 claim corrected (bump to 2.1.0 required).
- **IRMS `.planning/research/PITFALLS.md`** — prior research 2026-04-21. Pitfall 1 (bundled upgrade + features) and Pitfall 24 (broadcast payload regression) directly inform this research.
- **Context7 `/pestphp/pest`** — fetched 2026-04-21 for snapshot-API verification (none found; file-fixture idiom recommended).

### Tertiary (LOW confidence)

- None — every claim is verified or cited.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — 11 packages individually verified against Packagist.
- Architecture: HIGH — All file paths and existing code patterns verified by direct Read.
- Pitfalls: HIGH — Derived from L13 upgrade guide + prior PITFALLS.md + CONTEXT.md.
- Snapshot test pattern: HIGH — Pest idiom; no unknown APIs.
- Drain-and-deploy protocol: HIGH — L13 Horizon docs + existing Supervisor pattern in PITFALLS.md.

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (30 days — stable domain; re-verify Packagist versions if phase starts later)
