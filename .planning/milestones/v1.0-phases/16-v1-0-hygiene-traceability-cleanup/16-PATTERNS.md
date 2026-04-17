# Phase 16: v1.0 Hygiene & Traceability Cleanup - Pattern Map

**Mapped:** 2026-04-17
**Files analyzed:** 6 (2 source edits, 1 new test, 3 docs)
**Analogs found:** 6/6 (100% exact or role-match)

## File Classification

| File | Role | Data Flow | Closest Analog | Match Quality |
|------|------|-----------|----------------|---------------|
| `resources/js/components/intake/QueueRow.vue` (EDIT) | Vue component | request-response (Inertia router.post) | `resources/js/pages/admin/Units.vue` | exact — same `import { named } from '@/actions/...Controller'` + `router.post(named(id).url, ...)` idiom |
| `resources/js/composables/usePushSubscription.ts` (EDIT) | TS composable | request-response (manual fetch + XSRF) | `resources/js/composables/useGpsTracking.ts` | exact — same file type, same Wayfinder-backed `fetch(named.url(), { ..., 'X-XSRF-TOKEN': getXsrfToken() })` pattern |
| `tests/Unit/Conventions/WayfinderConventionTest.php` (NEW) | Pest unit test | file-I/O (disk scan) | `tests/Unit/Enums/UserRoleTest.php` (style only) | partial — no existing Pest convention/lint test in repo; new pattern needed, but Pest idiom `it('...', function () { expect(...)->...; })` is established |
| `.planning/REQUIREMENTS.md` (EDIT) | Documentation | transform | own existing `### Bi-directional Communication` + `### PWA & Push Notifications` sections | exact — same file, adding new sections that mirror its own prior sections |
| `.planning/phases/14-.../14-VALIDATION.md` (EDIT) | YAML frontmatter doc | transform | `.planning/phases/13-pwa-setup/13-VALIDATION.md` | exact — Phase 13 is the explicit precedent named in D-13 |
| `.planning/phases/10-.../10-VERIFICATION.md` (EDIT) | YAML frontmatter + checklist doc | transform | `.planning/phases/10-.../10-VERIFICATION.md` (itself, shape-wise) + `.planning/phases/08-.../08-VERIFICATION.md` (for `status: passed` target state) | exact — flipping same-file frontmatter, modelled on Phase 8's `status: passed` target |

---

## Pattern Assignments

### 1. `resources/js/components/intake/QueueRow.vue` (EDIT)

**Analog:** `resources/js/pages/admin/Units.vue` (`router.delete`/`router.post` with Wayfinder actions; same component-level idiom)

**Supporting analog:** `resources/js/components/intake/TriageForm.vue` (only intake-namespace Wayfinder usage — uses `.url()` helper function variant, useful as a second-reference for the `import { named } from '@/actions/App/Http/Controllers/IntakeStationController'` spelling)

#### Current code to change (`QueueRow.vue:53-79`):

```typescript
function handleOverride(priority: IncidentPriority): void {
    showPriorityPicker.value = false;

    router.post(
        `/intake/${props.incident.id}/override-priority`,
        { priority },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('overridden', props.incident.id, priority);
            },
        },
    );
}

function handleRecall(): void {
    router.post(
        `/intake/${props.incident.id}/recall`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('recalled', props.incident.id);
            },
        },
    );
}
```

#### Imports pattern (copy from `Units.vue:1-8`):

```typescript
import { Head, Link, router } from '@inertiajs/vue3';
import {
    destroy,
    edit,
    recommission,
} from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
```

**Apply to QueueRow.vue** — add a named-import block immediately after the `@inertiajs/vue3` import and before the existing `@/components/intake/...` imports (preserves ESLint `import/order` group ordering: external → internal):

```typescript
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import {
    overridePriority,
    recall,
} from '@/actions/App/Http/Controllers/IntakeStationController';
import IntakeIconOverride from '@/components/intake/icons/IntakeIconOverride.vue';
// ...rest unchanged
```

#### Core invocation pattern (copy from `Units.vue:74-82`):

```typescript
function decommissionUnit(unit: AdminUnit): void {
    router.delete(destroy(unit.id).url, {
        preserveScroll: true,
    });
}

function recommissionUnit(unit: AdminUnit): void {
    router.post(recommission(unit.id).url, {}, { preserveScroll: true });
}
```

**Key idiom:** `named(arg).url` — the invoked action returns `{ url, method }`; access `.url` as a property (no second parens). This matches how the IntakeStationController generates `overridePriority(args): RouteDefinition<'post'> => ({ url: overridePriority.url(args, options), method: 'post' })` (see `resources/js/actions/App/Http/Controllers/IntakeStationController.ts:224-227`).

**Apply to QueueRow.vue** — `handleOverride` and `handleRecall` swaps:

```typescript
function handleOverride(priority: IncidentPriority): void {
    showPriorityPicker.value = false;

    router.post(
        overridePriority(props.incident.id).url,
        { priority },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('overridden', props.incident.id, priority);
            },
        },
    );
}

function handleRecall(): void {
    router.post(
        recall(props.incident.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('recalled', props.incident.id);
            },
        },
    );
}
```

**Why `named(id).url` and NOT `named.url(id)`:** Both work — the analog `Units.vue` uses `.url` property access (matches Inertia idiom of wrapping the action call). `TriageForm.vue:244-247` uses `.url(id)` function variant, but only because it needs to assign the URL to a variable before passing to `form.post()`. For direct `router.post(url, payload, options)` calls, `named(id).url` is the more idiomatic Units.vue pattern. Planner should prefer the Units.vue form for consistency with the closest structural analog.

#### Why this is a safe swap
- `IntakeStationController::overridePriority` and `IntakeStationController::recall` already exported by Wayfinder generator (see `resources/js/actions/App/Http/Controllers/IntakeStationController.ts:224, :304`)
- Route names `intake.override-priority` and `intake.recall` are stable (`routes/web.php:93-94`)
- No signature change for `router.post` — third arg (options) unchanged
- Preserves `preserveScroll` + `onSuccess` emit pattern verbatim

#### Divergences planner should call out
- QueueRow.vue's existing imports are all internal (all `@/`), unlike Units.vue which has external `lucide-vue-next` and `@inertiajs/vue3` imports — inserting the `@/actions/...` import should keep it alphabetized within the internal `@/` group (ESLint `import/order` enforces `alphabetize: asc, caseInsensitive`). `@/actions/...` sorts before `@/components/intake/icons/...` because `actions` < `components`.
- Unlike Units.vue which uses a mix of named imports + default-import fallback (`import AdminUnitController from ...` on line 8), QueueRow.vue only needs 2 actions so **named-only import** is sufficient and preferred (tree-shaking; skill guidance at `.claude/skills/wayfinder-development/SKILL.md:38-44`).

---

### 2. `resources/js/composables/usePushSubscription.ts` (EDIT)

**Analog:** `resources/js/composables/useGpsTracking.ts` — **exact structural and behavioural match**. Same file type (TS composable), same manual `fetch()` pattern, same `getXsrfToken()` helper (literally a duplicated helper between the two files), same headers block, same `method: 'POST'` + JSON body.

#### Imports pattern (copy from `useGpsTracking.ts:1-4`):

```typescript
import type { Ref } from 'vue';
import { onUnmounted, ref, watch } from 'vue';
import { updateLocation } from '@/actions/App/Http/Controllers/ResponderController';
import type { IncidentStatus } from '@/types/responder';
```

**Apply to usePushSubscription.ts** — add named-import immediately after `vue` import (line 1):

```typescript
import { onMounted, ref } from 'vue';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/PushSubscriptionController';
```

Name shadowing note: the existing composable already has no local `store`/`destroy` variables, so the bare named imports are safe.

#### Core fetch pattern (copy from `useGpsTracking.ts:43-68`):

```typescript
function broadcastPosition(lat: number, lng: number): void {
    const now = Date.now();
    const interval = getBroadcastInterval(status.value);

    if (now - lastBroadcastTime < interval) {
        return;
    }

    lastBroadcastTime = now;

    fetch(updateLocation.url(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({
            latitude: lat,
            longitude: lng,
        }),
    }).catch(() => {
        // Silent fail -- GPS broadcast is best-effort
    });
}
```

**Key idiom:** `fetch(named.url(), { ... })` — `named.url()` is a no-arg helper that returns the route URL as a string (see `PushSubscriptionController.ts:22-24` for store, `PushSubscriptionController.ts:78-80` for destroy; both have zero route params so `.url()` takes no args).

#### Apply to usePushSubscription.ts

Current subscribe() block at lines 64-82:

```typescript
await fetch('/push-subscriptions', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getXsrfToken(),
    },
    body: JSON.stringify({ ... }),
});
```

Becomes:

```typescript
await fetch(store.url(), {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getXsrfToken(),
    },
    body: JSON.stringify({ ... }),
});
```

Current unsubscribe() block at lines 102-113:

```typescript
await fetch('/push-subscriptions', {
    method: 'DELETE',
    headers: { ... },
    body: JSON.stringify({ endpoint: subscription.endpoint }),
});
```

Becomes:

```typescript
await fetch(destroy.url(), {
    method: 'DELETE',
    headers: { ... },
    body: JSON.stringify({ endpoint: subscription.endpoint }),
});
```

#### Why `named.url()` and NOT `named().url`:

Both work, but `useGpsTracking.ts:53` uses the `.url()` helper (no parens on `updateLocation`) — this is **the composable-layer idiom** in this codebase. `named().url` is the Vue-component-layer idiom (Units.vue). Match the file type of the analog: composable → `.url()` function; component → `(id).url` property.

#### Divergences planner should call out
- `usePushSubscription.ts` already has its own `getXsrfToken()` helper at lines 5-11 — **do not remove it**. It stays identical. This mirrors `useGpsTracking.ts:23-30` where the helper is duplicated per-composable (no shared utility extracted).
- The `method: 'POST'` and `method: 'DELETE'` explicit-string values in `fetch()` are **not replaced by the Wayfinder method field**. Only the URL changes. The HTTP method stays as a hand-written string because `fetch()` requires it there and the Wayfinder-returned `method` property is for Inertia's benefit, not `fetch()`'s.
- Do not migrate to `router.post(store.url(), ...)` — D-08 locks the manual `fetch()` pattern in place due to the raw JSON body + XSRF pattern being deliberate for background push subscription management.

---

### 3. `tests/Unit/Conventions/WayfinderConventionTest.php` (NEW)

**Analog:** None exact — **no existing Pest test in this repo scans files on disk**. Closest structural Pest style analog is `tests/Unit/Enums/UserRoleTest.php` (simple `it(...)` block with `expect()`).

#### Pest test style (copy shape from `UserRoleTest.php:1-19`):

```php
<?php

use App\Enums\UserRole;

it('has an Operator case with value operator', function () {
    $operator = UserRole::Operator;

    expect($operator->value)->toBe('operator');
});

it('has exactly 5 cases', function () {
    expect(UserRole::cases())->toHaveCount(5);
});
```

**Notes from this analog:**
- Uses `it('description', function () { ... })` blocks (Pest idiom, not `test(...)`)
- PHP 7.4+ `<?php` tag, no closing tag
- Bare `use` statement at top for the class under test
- `expect(...)->toBe(...)` / `->toHaveCount(...)` / `->toBeEmpty()` assertions
- No class wrapper — Pest's `pest()->extend(TestCase::class)->in('Unit')` in `tests/Pest.php:21-22` already binds all Unit-folder files to the base TestCase

#### Pest test directory convention

Unit tests in subdirectories exist already: `tests/Unit/Enums/UserRoleTest.php`, `tests/Unit/Enums/IncidentStatusTest.php`. Creating `tests/Unit/Conventions/WayfinderConventionTest.php` is consistent with this pattern. `tests/Pest.php:21-22` binds all `tests/Unit/**` files automatically.

#### File-traversal pattern (no in-repo precedent — planner must introduce)

Since no existing Pest test scans files, suggest the planner use Laravel's `Illuminate\Support\Facades\File` facade (already available — used in `FortifyServiceProvider` and other areas) or Symfony's `Symfony\Component\Finder\Finder` (ships with Laravel).

**Recommended approach for planner** (draft pattern — not from existing code, derived from Laravel-boost conventions):

```php
<?php

use Symfony\Component\Finder\Finder;

it('forbids literal intake URLs in Vue/TS sources', function () {
    $finder = (new Finder())
        ->in(base_path('resources/js'))
        ->exclude(['actions', 'routes', 'wayfinder'])
        ->name(['*.ts', '*.vue'])
        ->files();

    $bannedPatterns = [
        '/intake/{incident}/override-priority',  // literal (templated form shouldn't appear in source)
        '/override-priority',                    // naked path
        '/recall',                               // naked path
    ];

    $violations = [];

    foreach ($finder as $file) {
        $contents = $file->getContents();

        foreach ($bannedPatterns as $pattern) {
            if (str_contains($contents, $pattern)) {
                // Additional check: regex for `/intake/.../override-priority` or `/intake/.../recall`
                if (preg_match('#/intake/[^\'"`]+/(override-priority|recall)#', $contents)) {
                    $violations[] = $file->getRelativePathname();
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Banned literal intake URLs found in:\n  - " . implode("\n  - ", $violations)
    );
});

it('forbids literal push-subscriptions URL in Vue/TS sources', function () {
    $finder = (new Finder())
        ->in(base_path('resources/js'))
        ->exclude(['actions', 'routes', 'wayfinder'])
        ->name(['*.ts', '*.vue'])
        ->files();

    $violations = [];

    foreach ($finder as $file) {
        $contents = $file->getContents();

        if (preg_match("#['\"`]/push-subscriptions['\"`]#", $contents)) {
            $violations[] = $file->getRelativePathname();
        }
    }

    expect($violations)->toBeEmpty(
        "Banned literal '/push-subscriptions' found in:\n  - " . implode("\n  - ", $violations)
    );
});
```

#### Why the `exclude()` list

The three Wayfinder-generated directories (`actions/`, `routes/`, `wayfinder/`) legitimately contain the literal URLs as values inside `.definition = { url: '/intake/{incident}/override-priority' }` — excluding them matches the ESLint ignore pattern at `eslint.config.js:85-88`:

```javascript
ignores: [
    // ...
    'resources/js/actions/**',
    'resources/js/components/ui/*',
    'resources/js/routes/**',
    'resources/js/wayfinder/**',
    // ...
]
```

#### Error message format

Use Pest's custom-message argument to `->toBeEmpty()` — the message lists all offending `file:line` matches so a contributor reintroducing a literal URL sees the exact locations. Pattern mirrors Pest 4 assertion docs.

#### Divergences planner should call out
- No existing `Conventions/` test directory — the planner will create it. This is consistent with `tests/Unit/Enums/` precedent.
- Symfony Finder is not currently imported in any Pest test — introducing it is fine (it's bundled with Laravel). Alternatively, Laravel's `File::allFiles(...)` facade works but returns `SplFileInfo[]` rather than `SplFileInfo` with convenience methods; Finder's `->exclude()` API is cleaner for this case.
- D-11 says the test should "fail with a descriptive message listing any offending `file:line` matches" — the draft above lists `file` only (via `$file->getRelativePathname()`). The planner should enhance this to include line numbers by using `preg_match_all` with `PREG_OFFSET_CAPTURE` and computing the line number from the byte offset, **or** simpler: include the full file path so the contributor can open and search. The skill hierarchy is `fail-with-file-path` > `fail-with-file:line`; either satisfies D-11's intent.
- CI integration: `composer ci:check` already runs Pest via `php artisan test --compact`; new test auto-discovers. No CI config change needed (matches D-12).

---

### 4. `.planning/REQUIREMENTS.md` (EDIT)

**Analog:** The file's own existing sections. No external analog needed — it is a self-documenting format.

#### Section header style (copy from REQUIREMENTS.md lines 122, 138):

```markdown
### Bi-directional Communication

- [x] **COMM-01**: MessageSent event broadcasts on incident-level channel (`incident.{id}.messages`) and dispatch channel (`dispatch.incidents`) instead of user-level channel
- [x] **COMM-02**: Incident message channel authorization permits dispatch roles (operator, dispatcher, supervisor, admin) and responders whose unit is assigned to the incident
- [x] **COMM-03**: Dispatch sendMessage endpoint at POST `dispatch/{incident}/message` creates message and dispatches MessageSent event
```

And:

```markdown
### PWA & Push Notifications

- [x] **MOBILE-01**: PWA Service Worker with app shell caching (JS, CSS, HTML, fonts, icons) via vite-plugin-pwa injectManifest strategy; web app manifest with CDRRMO branding; installable from browser; "New version available" update prompt
- [x] **MOBILE-02**: Web Push notifications via VAPID for background alerts: new assignment pushed to responder, P1 incident alert to dispatchers/operators, ack timeout warning to responder; push subscription management endpoints with custom in-app permission prompt
```

**Canonical format:**
- H3 (`### `) for section header, spaced words (no kebab-case)
- Each requirement: `- [x] **ID**: one-line statement.`
- Completed requirements use `[x]`; pending use `[ ]` (line 54, 58 are precedents for the `[ ]` style)
- Use code backticks for technical terms (route names, table names, channel names)

#### Insertion points (per D-04)

- **`### Operator Role`** section — insert between existing `### Intake` (line 22) and `### Dispatch` (line 34). Precedes Dispatch to preserve phase-chronological reading order (Phase 2 Intake → Phase 8 Operator → Phase 4 Dispatch).
- **`### Sentinel Rebrand`** section — insert between existing `### PWA & Push Notifications` (line 138) and `## v2 Requirements` (line 143). Comes last in v1, immediately before v2.

#### OP-01..15 source text (per D-02, from `.planning/phases/08-.../08-VERIFICATION.md:133-147`)

Copy descriptions verbatim from the 08-VERIFICATION.md Requirements Coverage table (second column: "Behavior Verified"). Because D-02 notes ROADMAP.md doesn't have 1:1 descriptions, 08-VERIFICATION.md is the canonical source.

Sample to confirm format — first 3 rows for planner to use as template:

```markdown
- [x] **OP-01**: Operator case exists in UserRole enum with value `'operator'` (5th role)
- [x] **OP-02**: Triaged case exists in IncidentStatus enum with value `'TRIAGED'` (between PENDING and DISPATCHED)
- [x] **OP-03**: 6 intake gates defined (`access-intake-station`, `create-incident-intake`, `triage-incident`, `override-priority`, `recall-incident`, `view-session-log`) with correct role matrix
```

#### REBRAND-01..06 source text (per D-02, from `14-VERIFICATION.md:99-104`)

Sample to confirm format — first 3 rows:

```markdown
- [x] **REBRAND-01**: CSS token migration — Sentinel navy/blue palette values in `--t-*` tokens, both `:root` (light) and `.dark` blocks; Shadcn components inherit via cascade
- [x] **REBRAND-02**: Font migration — DM Mono replaces Space Mono as `--font-mono`; Bebas Neue added as `--font-display` for auth page title
- [x] **REBRAND-03**: SVG shield identity — full animated shield on auth page (radar rings, crosshairs, eye); simplified 26x30 shield in sidebar + favicon
```

#### Traceability row format (copy from REQUIREMENTS.md lines 174-275)

```markdown
| FNDTN-01 | Phase 1 | Complete |
| INTK-01 | Phase 2 | Complete |
| MOBILE-01 | Phase 13 | Complete |
| MOBILE-02 | Phase 13 | Complete |
```

**Canonical row shape:** `| {ID} | Phase {N} | {Complete|Pending} |`

Phase 16's 21 new rows (per D-05, appended AFTER MOBILE-02 at line 275, grouped by prefix):

```markdown
| OP-01 | Phase 8 | Complete |
| OP-02 | Phase 8 | Complete |
...
| OP-15 | Phase 8 | Complete |
| REBRAND-01 | Phase 14 | Complete |
| REBRAND-02 | Phase 14 | Complete |
...
| REBRAND-06 | Phase 14 | Complete |
```

**OP-10 status exception:** Per `08-VERIFICATION.md:142`, OP-10 was originally PARTIAL because topbar stats weren't updated at runtime. Then `08-VERIFICATION.md:8-9` frontmatter records a `resolved` gap with commit 66b8a52. So OP-10's Traceability row should be `Complete`, matching the resolved status.

#### Coverage footer update (per D-03)

**Current (REQUIREMENTS.md:277-280):**

```markdown
**Coverage:**
- v1 requirements: 102 total
- Mapped to phases: 102 (RSPDR-06 and RSPDR-10 reassigned to Phase 15 gap closure)
- Unmapped: 0
```

**Updated:**

```markdown
**Coverage:**
- v1 requirements: 123 total (102 + 15 OP + 6 REBRAND backfilled in Phase 16)
- Mapped to phases: 123 (RSPDR-06 and RSPDR-10 reassigned to Phase 15 gap closure)
- Unmapped: 0
```

#### "Last updated" line (REQUIREMENTS.md:284)

Append a new timestamp line OR update existing:

Current: `*Last updated: 2026-04-17 after v1.0 milestone audit — RSPDR-06 and RSPDR-10 reopened for Phase 15 gap closure*`

Updated: `*Last updated: 2026-04-17 after Phase 16 traceability backfill — OP-01..15 and REBRAND-01..06 added to registry and coverage table*`

---

### 5. `.planning/phases/14-.../14-VALIDATION.md` (EDIT — frontmatter flip)

**Analog:** `.planning/phases/13-pwa-setup/13-VALIDATION.md` (explicitly named in D-13)

#### Phase 13 frontmatter block verbatim (lines 1-9):

```yaml
---
phase: 13
slug: pwa-setup
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-15
audited: 2026-04-17
---
```

#### Phase 14 current frontmatter (lines 1-8):

```yaml
---
phase: 14
slug: update-design-system-to-sentinel-branding-and-rename-app
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-15
---
```

#### Target Phase 14 frontmatter (after edit)

Per D-13: flip `status: draft` → `status: approved` AND append `approved: 2026-04-17`.

**Decision point for planner (re D-13):** Phase 13's precedent is `audited: 2026-04-17` (line 8) — not `approved: YYYY-MM-DD`. D-13 says append "approved: 2026-04-17 to frontmatter to match the Phase 13 precedent pattern". This is a **minor conflict**: the exact key in Phase 13 is `audited`, not `approved`.

**Recommendation:** Match Phase 13's **exact key** for precedent fidelity — use `audited: 2026-04-17`. This interprets D-13's intent ("match Phase 13") as more important than its literal wording ("approved"). Planner's call, per CONTEXT's "Claude's Discretion" bullet 2.

**Resulting block:**

```yaml
---
phase: 14
slug: update-design-system-to-sentinel-branding-and-rename-app
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-15
audited: 2026-04-17
---
```

**Two-line diff:**
- Line 4: `status: draft` → `status: approved`
- Line 7 (append after `created: 2026-03-15`): `audited: 2026-04-17`

All other lines unchanged.

#### Divergences planner should call out
- Key name choice (`audited:` vs `approved:`) needs a single-line decision in the plan. Recommend `audited:` to match Phase 13's precedent verbatim.
- No other file edits needed — the validation artifact body (tables, strategy text) remains as-is. A single-hunk diff.

---

### 6. `.planning/phases/10-.../10-VERIFICATION.md` (EDIT — add checklist + flip frontmatter)

**Analog:** The file itself (for shape). For the **target** `status: passed` state, the closest precedent is `.planning/phases/08-.../08-VERIFICATION.md:4` which has `status: passed`.

#### Current frontmatter (lines 1-26)

```yaml
---
phase: 10-update-all-pages-design-to-match-irms-intake-design-system
verified: 2026-03-14T00:00:00Z
status: human_needed
score: 12/12 must-haves verified
re_verification:
  previous_status: gaps_found
  previous_score: 10/12
  gaps_closed:
    - "All auth pages render with zero hardcoded neutral-* classes (TextLink.vue and TwoFactorChallenge.vue fixed)"
    ...
human_verification:
  - test: "Visual Design System Consistency"
    expected: "Login page shows CDRRMO branding; sidebar shows IRMS; ..."
    why_human: "Rendering fidelity cannot be verified programmatically."
  - test: "Focus Ring Rendering"
    expected: "Tab through form inputs on login and settings pages; focus ring shows as blue border + soft box-shadow (not the default outline-ring/50 style)"
    why_human: "CSS [data-slot]:focus-visible behavior is browser-rendered and cannot be verified via grep."
  - test: "Priority Selector color fidelity (PrioritySelector.vue)"
    expected: "P1=red, P2=orange, P3=amber, P4=green active button colors; inactive buttons show correctly tinted borders and hover states"
    why_human: "Token-to-color rendering requires visual browser verification; color-mix() cannot be inspected without rendering."
---
```

#### Target frontmatter (after human verification, per D-18)

Flip `status: human_needed` → `status: passed`. Keep `human_verification` array — per D-18 "keep the existing truth-row detail" — but the implicit convention is that once `status: passed`, the `human_verification:` entries can be annotated with pass dates or left as historical record.

**Minimal single-line change (most conservative):**
- Line 4: `status: human_needed` → `status: passed`

Optional additional edits (planner discretion):
- Add `human_verified: 2026-04-17` below the `verified:` line to distinguish the machine-verification date (2026-03-14) from the human-verification date (2026-04-17)
- Or add per-item `verified_by_user: true` flags under each `human_verification` entry

**Recommendation:** Keep it minimal — just flip `status` — to match the single-line spirit of D-18.

#### Checklist section to append (per D-16, D-17, D-18)

Append a new section after "Gaps Summary" (line 145-158) and before the trailing `---` (line 160):

```markdown
### Human Verification Checklist (Phase 16 follow-up)

Verified: 2026-04-17 by [user]

- [ ] **Focus ring** — Tab through login form + Settings > Profile inputs
      - Expected: `border-color: #2563eb` + `box-shadow: 0 0 0 3px rgba(37,99,235,0.1)`
      - NOT the Tailwind default `outline-ring/50`
      - Source: `resources/css/app.css:304`
      - Chrome light screenshot: `./10-verification-screenshots/focus-ring-light.png`
      - Chrome dark screenshot: `./10-verification-screenshots/focus-ring-dark.png`

- [ ] **color-mix() opacity — PrioritySelector**
      - Open `incidents/Create`; observe inactive priority buttons
      - Expected: borders at 40% opacity tint, hover at 8% opacity tint, using `--t-p1..--t-p4` tokens (not hardcoded red/orange/amber/green)
      - Source: `resources/js/components/incidents/PrioritySelector.vue`
      - Chrome light screenshot: `./10-verification-screenshots/priority-selector-light.png`
      - Chrome dark screenshot: `./10-verification-screenshots/priority-selector-dark.png`

- [ ] **Dark mode contrast — ReportRow badges**
      - Open Analytics > Reports; view report type + status badges in dark mode
      - Expected: color-mix() TYPE_BADGES (t-accent, t-role-supervisor, t-online, t-p2) + STATUS_BADGES (t-p3, t-online, t-p1) remain legible against dark background
      - Source: `resources/js/components/analytics/ReportRow.vue`
      - Chrome light screenshot: `./10-verification-screenshots/report-row-light.png`
      - Chrome dark screenshot: `./10-verification-screenshots/report-row-dark.png`
```

#### Screenshot directory (per D-15)

Create `.planning/phases/10-update-all-pages-design-to-match-irms-intake-design-system/10-verification-screenshots/` — planner's plan should include this `mkdir -p` step and list the 6 expected filenames.

**Filename scheme:** `{check-slug}-{light|dark}.png` — three checks × 2 modes = 6 files:
- `focus-ring-light.png`, `focus-ring-dark.png`
- `priority-selector-light.png`, `priority-selector-dark.png`
- `report-row-light.png`, `report-row-dark.png`

#### Divergences planner should call out
- The screenshots are user-produced offline. The plan for this file should be a **documentation plan**, not a code plan. Pest tests do not cover this.
- `status: passed` flip happens AFTER the user commits the screenshots. Plan should be explicit that the frontmatter edit is the last step, performed by the user after verifying all 3 checks.

---

## Shared Patterns

### Wayfinder Action Import (applies to all frontend code)

**Source:** `.claude/skills/wayfinder-development/SKILL.md:38-44`, `CLAUDE.md` Frontend Structure section

**Canonical form:**
```typescript
// Preferred (tree-shakes)
import { actionOne, actionTwo } from '@/actions/App/Http/Controllers/SomeController';

// Default import (when calling many actions on the same controller)
import SomeController from '@/actions/App/Http/Controllers/SomeController';
```

**Usage variants (both valid):**
- `actionOne(id).url` — property access on the invocation result (used in Vue components: Units.vue, IncidentTypes.vue)
- `actionOne.url(id)` — direct helper call (used in composables and Form submissions: TriageForm.vue, useGpsTracking.ts)

**Style rule:** Match the closest analog of the file's type — component → `(id).url`; composable → `.url(id)`.

### XSRF Fetch Pattern (applies to any manual `fetch()`)

**Source:** `resources/js/composables/useGpsTracking.ts:53-67` (and `usePushSubscription.ts:64-82` which already uses it)

**Canonical form:**
```typescript
function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

await fetch(someAction.url(), {
    method: 'POST',  // or 'DELETE', 'PUT', etc.
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getXsrfToken(),
    },
    body: JSON.stringify({ ... }),
});
```

**Rule:** `getXsrfToken()` is duplicated per-composable (no shared utility). Keep it that way.

### Pest Test Structure (applies to new convention test)

**Source:** `tests/Pest.php:17-22` (base binding) + `tests/Unit/Enums/UserRoleTest.php` (style exemplar)

**Canonical form:**
```php
<?php

use Some\Namespace\Thing;

it('describes the expectation', function () {
    expect($actual)->toBe($expected);
});
```

Unit tests auto-extend `TestCase` via `tests/Pest.php:21-22`. No class wrapper needed.

### REQUIREMENTS.md Formatting (applies to Plan 16-02)

**Section format:** `### {Title Case Section Name}` (H3, spaced words)
**Requirement line:** `- [x] **{ID}**: {one-line statement with `code` backticks as needed}`
**Traceability row:** `| {ID} | Phase {N} | {Complete\|Pending} |`

Completed requirements use `[x]`; pending use `[ ]`.

### Phase VALIDATION.md Approval Pattern (applies to Plan 16-03 for Phase 14)

**Source:** `.planning/phases/13-pwa-setup/13-VALIDATION.md:1-9`

**Frontmatter keys to set for an approved phase:**
- `status: approved`
- `audited: YYYY-MM-DD` (append after `created:`)
- Preserve `nyquist_compliant`, `wave_0_complete`, `phase`, `slug`, `created` as-is

---

## No Analog Found

All 6 files mapped. No files are left without an analog — even WayfinderConventionTest.php has a structural Pest style analog (UserRoleTest.php) even though no file-scanning precedent exists.

| File | Status |
|------|--------|
| (none) | — |

**Note on the convention test:** While no in-repo Pest test scans files on disk, the Pest test-shape (`it('...', fn () => {})`, `expect()->toBeEmpty()`) has clear precedent. The new pattern the planner introduces is the Symfony Finder + regex scan — this is a defensible extension, not a deviation from project style.

---

## Metadata

**Analog search scope:**
- `resources/js/` (Wayfinder + XSRF patterns)
- `resources/js/components/intake/` (verified no existing Wayfinder usage — QueueRow.vue establishes the precedent in this folder)
- `resources/js/pages/admin/` (primary analog source for Units.vue router.post idiom)
- `resources/js/pages/intake/` (verified no existing Wayfinder usage)
- `resources/js/composables/` (primary analog source for useGpsTracking.ts fetch idiom)
- `tests/Unit/` (Pest structural analog — UserRoleTest.php)
- `tests/Pest.php` (configuration)
- `eslint.config.js` (import ordering + ignore list for the convention test)
- `.planning/REQUIREMENTS.md` (self-analog for section + traceability formatting)
- `.planning/phases/13-pwa-setup/13-VALIDATION.md` (Phase 14 approval precedent)
- `.planning/phases/10-.../10-VERIFICATION.md` (self-analog; `status: passed` precedent borrowed from 08-VERIFICATION.md)

**Files scanned:** ~35 source files, 6 tests, 6 planning docs

**Pattern extraction date:** 2026-04-17
