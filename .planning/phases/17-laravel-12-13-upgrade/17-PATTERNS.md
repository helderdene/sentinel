# Phase 17: Laravel 12 → 13 Upgrade — Pattern Map

**Mapped:** 2026-04-21
**Files analyzed:** 10 new/modified scope files
**Analogs found:** 7 with close match / 10 total (3 have no direct analog — composer bumps, runbook doc)

---

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|----------------|---------------|
| `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` | test (Pest feature) | event-construction → JSON assertion | `tests/Unit/BroadcastEventTest.php` (broadcast payload assertions) + `tests/Feature/Intake/IoTWebhookTest.php` (Pest helper + `beforeEach`) | role-match (payload shape tests exist; byte-JSON fixture pattern is new) |
| `tests/Feature/Broadcasting/IncidentTriagedSnapshotTest.php` | test (Pest feature) | event-construction → JSON assertion | Same as above (uses `IncidentStatusChanged` PENDING→TRIAGED) | role-match |
| `tests/Feature/Broadcasting/UnitAssignedSnapshotTest.php` | test (Pest feature) | event-construction → JSON assertion | Same as above (uses `AssignmentPushed`) | role-match |
| `tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php` | test (Pest feature) | event-construction → JSON assertion | Same as above | role-match |
| `tests/Feature/Broadcasting/ChecklistUpdatedSnapshotTest.php` | test (Pest feature) | event-construction → JSON assertion | Same as above | role-match |
| `tests/Feature/Broadcasting/ResourceRequestedSnapshotTest.php` | test (Pest feature) | event-construction → JSON assertion | Same as above | role-match |
| `tests/Feature/Broadcasting/__snapshots__/*.json` | fixture (golden data) | static file read | None — new convention for IRMS | no analog |
| `routes/web.php` (MODIFY) | config (routing) | middleware import rename | Self — existing `VerifyCsrfToken` usage lines 13, 19, 23 | exact (rename-in-place) |
| `config/cache.php` (MODIFY) | config (framework) | additive top-level key | Self — existing top-level keys (`default`, `stores`, `prefix`) | exact (additive) |
| `config/fortify.php` (MODIFY — comment only) | config (auth) | PHPDoc comment addition | Self — existing `/* ... */` block comments above each config section (lines 107-145) | exact |
| `bootstrap/app.php` (NO CHANGE expected) | config (framework) | — | Self — no CSRF references present [VERIFIED: lines 1-56] | N/A (no-op) |
| `composer.json` (MODIFY — constraint bumps) | config (package manifest) | JSON key edits | Self — existing constraint strings lines 12, 18, 19, 20, 21, 22 | exact (string replace) |
| `docs/operations/laravel-13-upgrade.md` (NEW) | documentation (runbook) | static file read | None — `docs/operations/` directory does not exist. Closest prose analog is `docs/IRMS-Specification.md` (heavy TOC-style spec, not runbook fit). Use RESEARCH.md §Horizon drain-and-deploy excerpt verbatim. | no analog |

---

## Pattern Assignments

### Snapshot tests (all 6) — classified as `test / event-construction → JSON assertion`

**Primary analog:** `tests/Unit/BroadcastEventTest.php`
**Secondary analog (for Pest `beforeEach` + helper fn convention):** `tests/Feature/Intake/IoTWebhookTest.php`

#### Imports pattern — copy from `tests/Unit/BroadcastEventTest.php` lines 1-16

```php
<?php

use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Events\AssignmentPushed;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Events\MessageSent;
use App\Events\UnitLocationUpdated;
use App\Events\UnitStatusChanged;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
```

**Note:** Phase 17 snapshot tests add `use function Pest\Laravel\freezeTime;` — not present in existing tests (see Pitfall 1 in RESEARCH.md). Also add `use App\Models\IncidentType;` and `use App\Models\Barangay;` for deterministic factory builds (per RESEARCH.md example).

#### Core payload-construction pattern — copy from `tests/Unit/BroadcastEventTest.php` lines 30-44

```php
it('IncidentCreated broadcastWith returns correct payload keys', function () {
    $incident = Incident::factory()->for(User::factory(), 'createdBy')->create();
    $incident->load('incidentType', 'barangay');

    $event = new IncidentCreated($incident);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys([
        'id', 'incident_no', 'priority', 'status',
        'incident_type', 'location_text', 'barangay', 'channel', 'created_at',
    ]);
    expect($payload['id'])->toBe($incident->id);
    expect($payload['priority'])->toBe($incident->priority->value);
    expect($payload['status'])->toBe($incident->status->value);
});
```

**What to copy verbatim:** Factory-build-then-load pattern (`->for(User::factory(), 'createdBy')` + `->load('incidentType', 'barangay')`), direct event construction (`new IncidentCreated($incident)`), and calling `broadcastWith()` directly on the instance. **Do NOT use `Event::fake()`** (anti-pattern per RESEARCH.md — tests dispatch, not payload shape).

**What to replace (Phase 17 diff):** Swap `->toHaveKeys([...])` assertion for the byte-JSON fixture assertion (see Phase 17 new pattern below).

#### Factory determinism pattern — copy from `tests/Feature/Intake/IoTWebhookTest.php` lines 10-12 + 42-43

```php
beforeEach(function () {
    Event::fake([IncidentCreated::class]);
});

it('creates PENDING incident from valid flood_gauge payload with P2 priority', function () {
    $floodType = IncidentType::factory()->create(['code' => 'NAT-002', 'default_priority' => 'P2']);
```

**What to copy:** (a) `beforeEach(function () { ... });` structure. (b) Pinning factory attributes via `->create(['key' => 'value'])` arrays — this is the IRMS-standard way to make factory output deterministic. Phase 17 snapshot tests replace the `Event::fake` line inside `beforeEach` with `freezeTime()` (see RESEARCH.md line 383: `beforeEach(fn () => freezeTime());`).

#### New Phase 17 pattern — byte-JSON fixture assertion (not in existing code)

```php
// From RESEARCH.md "Pattern: Payload snapshot test (Pest 4, file-fixture idiom)" — lines 230-281
$payload = (new IncidentCreated($incident))->broadcastWith();
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$fixturePath = __DIR__.'/__snapshots__/IncidentCreated.json';

if (! file_exists($fixturePath)) {
    file_put_contents($fixturePath, $json);
    $this->markTestIncomplete('Golden fixture created; re-run to verify.');
}

expect($json)->toBe(file_get_contents($fixturePath));
```

**Source:** RESEARCH.md lines 269-281 (primary) and lines 385-401 (per-event variant for UnitStatusChanged). Re-use verbatim; adapt only the event class + constructor args per event.

#### Factory input mapping per event — from RESEARCH.md table "The 6 events to snapshot" (lines 406-414)

| Test file | Event constructor | Model eager-loads / extras |
|-----------|-------------------|----------------------------|
| `IncidentCreatedSnapshotTest.php` | `new IncidentCreated($incident)` | `$incident->load('incidentType', 'barangay')` |
| `IncidentTriagedSnapshotTest.php` | `new IncidentStatusChanged($incident, IncidentStatus::Pending)` with `$incident->status = IncidentStatus::Triaged` | `->create(['status' => IncidentStatus::Triaged])` |
| `UnitAssignedSnapshotTest.php` | `new AssignmentPushed($incident, 'AMB-01', 42)` | `$incident->load('incidentType', 'barangay')` |
| `UnitStatusChangedSnapshotTest.php` | `new UnitStatusChanged($unit, UnitStatus::Available)` | `Unit::factory()->create(['id' => 'AMB-01', 'callsign' => 'Alpha-1', 'status' => UnitStatus::Dispatched])` |
| `ChecklistUpdatedSnapshotTest.php` | `new ChecklistUpdated($incident)` | payload is `incident_id, incident_no, checklist_pct` — pin `checklist_pct` on factory |
| `ResourceRequestedSnapshotTest.php` | `new ResourceRequested($incident, ResourceType::*, 'notes', $requester)` | pin `now()` via `freezeTime()` — payload includes `'timestamp' => now()->toISOString()` |

#### Factory determinism specifics (from Pitfall 1, RESEARCH.md)

Because existing `IncidentFactory::definition()` uses `fake()->randomElement(IncidentPriority::cases())`, `fake()->address()`, `Point::makeGeodetic(... + fake()->randomFloat(...))` etc. (see `/Users/helderdene/IRMS/database/factories/IncidentFactory.php` lines 27-43), snapshot tests MUST pin these explicitly via the `->create([...])` overrides. Example pinning list per RESEARCH.md line 258-265:

```php
$incident = Incident::factory()
    ->for($user, 'createdBy')
    ->for($type, 'incidentType')
    ->for($barangay)
    ->create([
        'id' => '01929000-aaaa-bbbb-cccc-000000000001',
        'incident_no' => 'INC-2026-00001',
        'location_text' => 'J.C. Aquino Ave.',
        'caller_name' => 'Snapshot Tester',
        'caller_contact' => '09171234567',
        'notes' => 'Fixture data',
        'priority' => IncidentPriority::P2,
        'channel' => IncidentChannel::Phone,
    ]);
```

**Why this is non-negotiable:** `IncidentFactory::definition()` line 33-36 wraps `Point::makeGeodetic(... + fake()->randomFloat(4, -0.05, 0.05), ...)` — without pinning, every run produces a different `coordinates.lat/lng` and the snapshot bytes drift. Pin `'coordinates' => Point::makeGeodetic(8.9475, 125.5406)` explicitly.

---

### `routes/web.php` (MODIFY — CSRF middleware rename)

**Analog:** Self (rename-in-place)

**Current state** — `routes/web.php` lines 13, 19, 23:

```php
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
// ...
Route::post('iot-sensor', IoTWebhookController::class)
    ->middleware('verify-iot-signature')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.iot-sensor');

Route::post('sms-inbound', SmsWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.sms-inbound');
```

**Target state** (Commit 2) — three-line change:

```php
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
// ...
    ->withoutMiddleware([PreventRequestForgery::class])  // line 19
// ...
    ->withoutMiddleware([PreventRequestForgery::class])  // line 23
```

**Scope discipline:** No other `routes/web.php` edits in this phase. Surface is exactly 3 lines.

---

### `config/cache.php` (MODIFY — additive `serializable_classes` key)

**Analog:** Self — existing top-level keys at lines 18, 35, 115

**Insertion point** — after line 115 (the closing `]` that wraps the `'prefix'` key, still inside the top-level `return [...]`):

```php
// Existing line 115:
'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

// ADD AFTER (Phase 17, Commit 2):

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

**Comment-block pattern source:** Every section in `config/cache.php` uses the `/*\n|---...---\n| Title\n|---...---\n| body\n*/` style (see lines 7-16, 20-33, 104-113). Match this exactly.

---

### `config/fortify.php` (MODIFY — comment-only lockdown per Research item 6)

**Analog:** Self — existing `/* ... */` comment block above each config section (lines 107-145)

**Current state** — lines 146-154 (the `features` array):

```php
'features' => [
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
        // 'window' => 0
    ]),
],
```

**Target state** (Phase 17) — prepend a comment explicitly pinning the opt-out of passkeys / unused Fortify features:

```php
/*
|--------------------------------------------------------------------------
| Feature Lockdown (Phase 17 — Laravel 13 upgrade)
|--------------------------------------------------------------------------
|
| IRMS v1.0 deliberately enables only: resetPasswords, emailVerification,
| twoFactorAuthentication. Fortify's passkey / WebAuthn, registerUsers, and
| updateProfileInformation features are NOT enabled and MUST NOT be auto-
| enabled by future Fortify minor bumps. If a Fortify upgrade introduces a
| new default feature, keep it omitted here until a dedicated phase lands
| UAT for the added auth surface.
|
*/

'features' => [
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
        // 'window' => 0
    ]),
],
```

**No PHP behavior change** — pure documentation. Code remains byte-identical.

---

### `composer.json` (MODIFY — constraint bumps across Commits 2 and 3)

**Analog:** Self — existing `require` block lines 11-26 uses string constraint values

**Current state** — relevant lines 12, 18, 19, 20, 21, 22, 14, 15, 17 and dev deps 35:

```json
"require": {
    "php": "^8.2",
    "barryvdh/laravel-dompdf": "^3.1",
    "clickbar/laravel-magellan": "^2.0",
    "inertiajs/inertia-laravel": "^2.0",
    "laravel-notification-channels/webpush": "^10.5",
    "laravel/fortify": "^1.30",
    "laravel/framework": "^12.0",
    "laravel/horizon": "^5.45",
    "laravel/reverb": "^1.8",
    "laravel/tinker": "^2.10.1",
    "laravel/wayfinder": "^0.1.9",
    ...
},
"require-dev": {
    ...
    "laravel/boost": "^2.0",
    ...
    "pestphp/pest": "^4.4",
    "pestphp/pest-plugin-laravel": "^4.1"
}
```

**Target state (Commit 2)** — framework + tinker + PHP floor only:

```json
"php": "^8.3",
"laravel/framework": "^13.0",
"laravel/tinker": "^3.0",
```

**Target state (Commit 3)** — aligned bumps per RESEARCH.md lines 82-92:

```json
"clickbar/laravel-magellan": "^2.1",
"inertiajs/inertia-laravel": "^2.0.24",
"laravel/fortify": "^1.36",
"laravel/horizon": "^5.45.6",
"laravel/reverb": "^1.10",
"laravel/wayfinder": "^0.1.14",
"laravel/boost": "^2.4",
"pestphp/pest": "^4.6",
```

**Style:** Preserve `sort-packages: true` (already set in `config` block line 114) — composer will re-sort on `composer update`.

---

### `docs/operations/laravel-13-upgrade.md` (NEW — no in-repo analog)

**Analog status:** **No existing analog.** `docs/operations/` directory does not exist. `docs/` contains only `IRMS-Specification.md` (TOC-heavy spec prose) and `IRMS-Intake-Design-System.md` (design system) — neither is a runbook.

**Recommended structure** (planner chooses per Discretion in CONTEXT.md line 44):

1. **Purpose** — why this runbook exists (FRAMEWORK-03)
2. **Preconditions** — who runs it, when, what staging gate
3. **Pre-deploy: drain Horizon** — from RESEARCH.md lines 447-461 verbatim
4. **Deploy: composer + wayfinder + optimize** — from RESEARCH.md lines 463-468 verbatim
5. **Post-deploy: restart + verify** — from RESEARCH.md lines 470-478 verbatim
6. **Rollback** — D-10 sequence: `horizon:terminate` → `git revert` → `composer install` (L12 lockfile) → `supervisorctl restart`
7. **Smoke test checklist** — Phase 17 Success Criterion 4 spot-check (Report → Triage → Dispatch → ACK → OnScene → Resolve)

**Source excerpt to copy verbatim** — RESEARCH.md lines 443-478 (the `# docs/operations/laravel-13-upgrade.md — drain-and-deploy procedure` code block). That block is Markdown-ready.

---

## Shared Patterns

### Pest test file structure

**Source:** All files in `tests/Feature/` and `tests/Unit/` (e.g., `tests/Unit/BroadcastEventTest.php`, `tests/Feature/Intake/IoTWebhookTest.php`)
**Apply to:** All 6 new snapshot tests

```php
<?php

use App\Events\...;       // event class under test
use App\Models\...;       // factory-buildable models
// (no namespace; no class declaration — Pest global-function style)

beforeEach(function () {
    // per-test setup
});

it('description of assertion', function () {
    // arrange + act + assert
});
```

IRMS convention is Pest's global-function style (no `class FooTest extends TestCase`). All feature tests use `RefreshDatabase` implicitly via `tests/Pest.php` (not re-read; standard IRMS convention per CLAUDE.md).

### Event factory build pattern

**Source:** `tests/Unit/BroadcastEventTest.php` lines 19-20, 31, 47-49, 58-60, 90, 100
**Apply to:** All 6 snapshot tests

```php
$incident = Incident::factory()->for(User::factory(), 'createdBy')->create();
$incident->load('incidentType', 'barangay');
```

Always eager-load `incidentType` + `barangay` on Incident before constructing any Incident-based broadcast event (the event's `broadcastWith()` reads `$incident->incidentType?->name` and `$incident->barangay?->name` — absent loads emit `null`, which would still serialize but changes fixture bytes).

### Direct event construction (NOT `Event::fake`)

**Source:** `tests/Unit/BroadcastEventTest.php` lines 22-23, 33-34, 51, 61-62, 72, 82, 92, 102, 109
**Apply to:** All 6 snapshot tests

```php
$event = new IncidentCreated($incident);
$payload = $event->broadcastWith();
```

**Never** use `Event::fake([...])` for snapshot tests (anti-pattern per RESEARCH.md line 300). Use `Event::fake` only in tests that verify *dispatch*, not payload shape (see `tests/Feature/Intake/IoTWebhookTest.php` for the dispatch-verification pattern — outside Phase 17 scope).

### Laravel config file `/*--|--*/` comment blocks

**Source:** `config/cache.php` lines 7-16, 20-33, 104-113; `config/fortify.php` lines 7-16, 20-33, 33-46, 52-62, 65-74, 78-87, 93-102, 106-115, 122-131, 135-144
**Apply to:** `config/cache.php` (new `serializable_classes` key), `config/fortify.php` (comment above `features`)

```php
/*
|--------------------------------------------------------------------------
| Title
|--------------------------------------------------------------------------
|
| Prose body.
|
*/
```

Always precede top-level config keys with this block when adding new ones (matches existing IRMS/Laravel convention).

---

## No Analog Found

Planner should use RESEARCH.md excerpts directly for these (no in-repo pattern to imitate):

| File | Role | Data Flow | Reason / Where to get pattern |
|------|------|-----------|-------------------------------|
| `tests/Feature/Broadcasting/__snapshots__/*.json` | fixture | static | No existing `__snapshots__/` convention in IRMS. Generated by test first-run (RESEARCH.md lines 274-278). |
| `docs/operations/laravel-13-upgrade.md` | runbook | static | No `docs/operations/` precedent. Copy runbook shell directly from RESEARCH.md lines 443-478 (already Markdown-ready). |
| `composer.json` constraint bumps | manifest | JSON edit | First framework-major upgrade in IRMS history. Values come directly from RESEARCH.md §Target Versions table (lines 82-92). |

---

## Key Insights for the Planner

1. **Snapshot tests have TWO analog sources, not one:** payload assertion style comes from `tests/Unit/BroadcastEventTest.php` (direct event construction, `broadcastWith()` call, factory+load pattern); determinism-harness pattern (`beforeEach`, factory attribute pinning) comes from `tests/Feature/Intake/IoTWebhookTest.php`. The byte-JSON fixture idiom itself is new to IRMS — cite RESEARCH.md lines 230-281 verbatim in the plan's `<read_first>` block.

2. **All 6 snapshot tests share the SAME pattern** — differ only in event constructor args and factory pin list. Planner can template one (`IncidentCreated`) and clone 5× with per-event swap.

3. **The middleware rename is a 3-line change, not a no-op** — CONTEXT.md said `bootstrap/app.php` has no refs (true) but missed `routes/web.php` which has 3 references. RESEARCH.md correction at lines 193-194 is authoritative. Planner's task MUST cite `routes/web.php` lines 13, 19, 23 specifically.

4. **`bootstrap/app.php` is a no-op in Phase 17** — verified at lines 1-56: no `VerifyCsrfToken` / `PreventRequestForgery` imports or usages. Planner should include a "verify no change needed" step, not an edit task.

5. **Factory determinism is the load-bearing concern** — Pitfall 1 (RESEARCH.md lines 335-339) is the most likely cause of snapshot-test flakiness. Every task that creates a fixture MUST pin `coordinates` explicitly (not left to `IncidentFactory::definition()` line 33-36 random floats) AND call `freezeTime()` BEFORE any factory build.

6. **Three-commit discipline maps to waves:** Wave 1 = Commit 1 (snapshot capture on L12). Wave 2 = Commit 2 (framework + middleware + cache). Wave 3 = Commit 3 (aligned package bumps + runbook). Planner aligns wave boundaries to commit boundaries so `git bisect` remains useful per D-04.

---

## Metadata

**Analog search scope:** `tests/Unit/`, `tests/Feature/` (Intake, RealTime, Foundation), `app/Events/`, `database/factories/`, `config/`, `routes/`, `bootstrap/`, `docs/`, `composer.json`, `phpunit.xml`
**Files scanned:** 22 (Read) + 4 directory listings + 1 Grep
**Pattern extraction date:** 2026-04-21
