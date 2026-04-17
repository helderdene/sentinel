---
phase: 16
plan: 01
subsystem: frontend-hygiene
tags: [wayfinder, refactor, convention-test, tech-debt, v1-audit]
dependency_graph:
  requires:
    - "resources/js/actions/App/Http/Controllers/IntakeStationController.ts (Wayfinder-generated; exports overridePriority, recall)"
    - "resources/js/actions/App/Http/Controllers/PushSubscriptionController.ts (Wayfinder-generated; exports store, destroy)"
  provides:
    - "Wayfinder-backed URL invocations in QueueRow.vue (override + recall)"
    - "Wayfinder-backed URL invocations in usePushSubscription.ts (subscribe + unsubscribe)"
    - "Pest convention guard at tests/Unit/Conventions/WayfinderConventionTest.php"
  affects:
    - "resources/js/components/intake/QueueRow.vue (override/recall call sites)"
    - "resources/js/composables/usePushSubscription.ts (subscribe/unsubscribe fetch URLs)"
    - "composer ci:check pipeline (auto-discovers new Pest test)"
tech_stack:
  added: []
  patterns:
    - "Wayfinder named imports + .url() variant (composable-layer idiom)"
    - "Wayfinder named imports + (id).url variant (component-layer idiom)"
    - "Pest convention-guard via Symfony Finder (new pattern for this repo)"
key_files:
  created:
    - "tests/Unit/Conventions/WayfinderConventionTest.php"
  modified:
    - "resources/js/components/intake/QueueRow.vue"
    - "resources/js/composables/usePushSubscription.ts"
decisions:
  - "Named imports chosen over default import (D-07 literal vs skill resolution; rationale below)"
  - "Pest convention guard kept in tests/Unit/Conventions/ (new subdir); auto-bound by tests/Pest.php:21-22"
  - "Pint auto-formatted new Finder() -> new Finder; idempotent after second run"
metrics:
  duration_min: 9
  tasks: 3
  files_touched: 3
  completed_date: "2026-04-17"
---

# Phase 16 Plan 01: Wayfinder URL swaps (QueueRow.vue + usePushSubscription.ts + Pest guard) Summary

Replaced 3 hardcoded URL literals (`/intake/{id}/override-priority`, `/intake/{id}/recall`, `/push-subscriptions`) with their Wayfinder action equivalents and added a Pest convention guard preventing reintroduction — closes v1.0 Milestone Audit Phase 8 (QueueRow.vue:56-78) and Phase 13 (usePushSubscription.ts:64,102) tech-debt items.

## Tasks Completed

### Task 1 — QueueRow.vue Wayfinder swap

**Commit:** `1f6af07` — `refactor(16-01): swap hardcoded intake URLs for Wayfinder actions in QueueRow`

**Changes (resources/js/components/intake/QueueRow.vue):**
- Line 5-8: Added named import block `import { overridePriority, recall } from '@/actions/App/Http/Controllers/IntakeStationController';` (ESLint `import/order` alphabetizes `actions` before `components`)
- Line 61: `handleOverride()` now calls `router.post(overridePriority(props.incident.id).url, ...)` — replaces `` `/intake/${props.incident.id}/override-priority` `` template literal
- Line 74: `handleRecall()` now calls `router.post(recall(props.incident.id).url, ...)` — replaces `` `/intake/${props.incident.id}/recall` `` template literal

**Idiom:** Component-layer uses `named(id).url` per 16-PATTERNS.md §1 (Units.vue analog). `preserveScroll`, `onSuccess` emit, and priority picker toggle side-effect all preserved verbatim.

### Task 2 — usePushSubscription.ts Wayfinder swap

**Commit:** `2fe4443` — `refactor(16-01): swap hardcoded push-subscription URLs for Wayfinder actions`

**Changes (resources/js/composables/usePushSubscription.ts):**
- Line 3-6: Added named import block `import { destroy, store } from '@/actions/App/Http/Controllers/PushSubscriptionController';`
- Line 63: `subscribe()` now calls `await fetch(store.url(), { method: 'POST', ... })` — replaces `'/push-subscriptions'` string literal
- Line 101: `unsubscribe()` now calls `await fetch(destroy.url(), { method: 'DELETE', ... })` — replaces `'/push-subscriptions'` string literal

**Idiom:** Composable-layer uses `named.url()` per 16-PATTERNS.md §2 (useGpsTracking.ts analog). `getXsrfToken()` helper, headers block, JSON body, and `localStorage.setItem/removeItem` flow all preserved verbatim (per D-08).

### Task 3 — Pest convention guard

**Commit:** `fb5b7ac` — `test(16-01): add Pest convention guard preventing literal URL reintroduction`

**File created:** `tests/Unit/Conventions/WayfinderConventionTest.php` (70 lines)

- Two `it(...)` blocks using Symfony Finder to scan `resources/js/**/*.{ts,vue}` (excludes Wayfinder-generated `actions/`, `routes/`, `wayfinder/`; also excludes `sw.ts` service worker)
- Block 1: Fails if any file contains `/intake/{id}/override-priority` or `/intake/{id}/recall` literal (catches string, template literal, and any quoted form)
- Block 2: Fails if any file contains `'/push-subscriptions'` (or `"` or backtick quote form) literal
- Failure messages list offending file paths (relative) plus corrective Wayfinder idiom inline
- **Both blocks PASS against the clean post-Task-1/2 codebase** (2 passed, 2 assertions, 0.26s)
- Auto-discovered by `composer ci:check` — no new tooling per D-12

**Pint run outcome:** Auto-formatted the new test file once (`new Finder()` → `new Finder`; double-quoted `"  router.post(recall(id).url, {}, options);"` → single-quoted). Second Pint run returned `{"result":"pass"}` (idempotent). Post-format test re-run: 2 passed.

## D-07 Literal-vs-Skill Resolution

CONTEXT.md's D-07 literally suggested a **default** import for `PushSubscriptionController`:

```typescript
import PushSubscriptionController from '@/actions/App/Http/Controllers/PushSubscriptionController';
PushSubscriptionController.store();
PushSubscriptionController.destroy();
```

Task 2 uses **named** imports instead:

```typescript
import { destroy, store } from '@/actions/App/Http/Controllers/PushSubscriptionController';
store.url();
destroy.url();
```

**Rationale** (recorded in both the 16-01-PLAN.md `<action>` block and the Task 2 commit body):

1. **Project skill takes precedence.** `.claude/skills/wayfinder-development/SKILL.md:39` states verbatim: *"Named imports for tree-shaking (preferred)"* — this is the project-wide Wayfinder convention.
2. **Structural analog match.** The 1:1 analog `resources/js/composables/useGpsTracking.ts` uses NAMED imports from a Wayfinder action controller (16-PATTERNS.md §2). Matching the analog's import style is what D-06/D-07's underlying intent asks for.
3. **CONTEXT.md grants discretion.** §"Claude's Discretion" bullet 1 explicitly grants the executor latitude on exact patterns when a project skill takes precedent.
4. **Substance preserved.** Both forms produce identical runtime URLs (`/push-subscriptions`). D-07's core intent — "no hardcoded `/push-subscriptions` literals remain in the composable" — is fully satisfied. Only the surface import form diverges from D-07's literal wording.
5. **Tree-shaking.** Named imports allow Vite to ship only `store` + `destroy` in the bundle (the rest of the controller's exports don't land).

## Deviations from Plan

### Environmental (not code deviations)

**1. [Rule 3 - Blocking] Regenerated Wayfinder output during verification**
- **Found during:** Post-Task-3 verification chain
- **Issue:** First `npm run types:check` showed a duplicate-identifier error in `resources/js/routes/admin/units/index.ts` (generated file with duplicated import line). Investigation revealed `resources/js/routes` is in `.gitignore` and was regenerated out-of-band — likely the Vite dev plugin half-ran during my test suite invocation.
- **Fix:** Ran `php artisan wayfinder:generate --with-form` (matches `vite.config.ts:74` `formVariants: true` setting) to restore the `.form` helpers that were temporarily missing from a naive regen.
- **Files modified:** None tracked (all under `.gitignore`d `resources/js/actions/` and `resources/js/routes/`)
- **Commit:** Not applicable — regenerated files are gitignored

## Pre-existing Issues Not Fixed (Scope Boundary)

Per the `<scope_boundary>` rule, these pre-existing failures are NOT caused by my changes and are out of scope:

1. **TypeScript: `resources/js/pages/admin/UnitForm.vue:263` TS2322** — `(value: string) => void` not assignable to `(value: AcceptableValue) => any`. This is explicitly listed in CONTEXT.md line 28 as a v2-deferred item. Confirmed pre-existing via `git log --oneline resources/js/pages/admin/UnitForm.vue` (no Phase 16 touches).
2. **ESLint full-tree: 452 errors in `report-app/dist/**` and `report-app/vite.config.ts`** — the Citizen Reporting App sub-project's build output plus one parse error. The `report-app/public/` directory is listed as untracked in the starting git status and is outside the v1.0 codebase scope.
3. **Pest full suite: 48 "failures" — `SQLSTATE[23505]: Unique violation ... "incident_categories_name_unique"`** — cascading PostgreSQL test-DB constraint violations when the full suite runs in one pass. Proven pre-existing by running each failing test file in isolation (all pass): `tests/Feature/Intake/` (97 passed), `tests/Feature/WebPushNotificationTest.php` (10 passed), `tests/Feature/Dispatch/DispatchConsolePageTest.php` (8 passed), `tests/Feature/AckTimeout` (4 passed). This is a test-ordering/seeding infrastructure issue unrelated to Phase 16 changes.

## Authentication Gates

None encountered.

## Verification Results

All 7 plan-level verification commands evaluated:

| # | Command | Outcome |
|---|---------|---------|
| 1 | `php artisan test --compact tests/Unit/Conventions/WayfinderConventionTest.php` | ✅ **PASS** — 2 passed (2 assertions) in 0.26s |
| 2 | `php artisan test --compact` (full) | ⚠️ 48 pre-existing DB-state failures (all pass in isolation; unrelated to scope) |
| 3 | `npm run types:check` | ⚠️ 1 pre-existing error (`UnitForm.vue:263` — v2-deferred per CONTEXT.md line 28) |
| 4a | `npx eslint resources/js/components/intake/QueueRow.vue` | ✅ **PASS** — 0 errors |
| 4b | `npx eslint resources/js/composables/usePushSubscription.ts` | ✅ **PASS** — 0 errors |
| 5 | `npm run lint` (full) | ⚠️ 452 pre-existing errors (all in `report-app/**` sub-project — out of scope) |
| 6 | `npm run build` | ✅ **PASS** — 53 modules transformed, built in 127ms, PWA service worker generated cleanly |
| 7 | `vendor/bin/pint --dirty --format agent` | ✅ **PASS** — `{"result":"pass"}` (idempotent) |

**In-scope files pass 100%** of applicable checks (TypeScript, ESLint, Vite import resolution, Pint, Pest convention guard). Pre-existing failures are documented and out of scope per the `<scope_boundary>` rule.

## Tech Debt Closed

- **v1.0-MILESTONE-AUDIT.md Tech Debt Phase 8 item** (lines ~211-214): `QueueRow.vue:56-78` hardcoded intake URLs → resolved by Task 1
- **v1.0-MILESTONE-AUDIT.md Tech Debt Phase 13 item** (lines ~215-218): `usePushSubscription.ts:64,102` hardcoded push-subscription URLs → resolved by Task 2
- **New regression guard** (Task 3): `tests/Unit/Conventions/WayfinderConventionTest.php` prevents reintroduction of these and equivalent literal URLs across `resources/js/**` going forward

## Self-Check: PASSED

**Files created:**
- `tests/Unit/Conventions/WayfinderConventionTest.php` — FOUND

**Files modified:**
- `resources/js/components/intake/QueueRow.vue` — FOUND (Wayfinder import + overridePriority(id).url + recall(id).url present; no template-literal URLs remain)
- `resources/js/composables/usePushSubscription.ts` — FOUND (Wayfinder import + store.url() + destroy.url() present; no `/push-subscriptions` literals remain)

**Commits:**
- `1f6af07` — FOUND (Task 1: QueueRow.vue)
- `2fe4443` — FOUND (Task 2: usePushSubscription.ts, with D-07 resolution in body)
- `fb5b7ac` — FOUND (Task 3: WayfinderConventionTest.php)

All acceptance criteria from the plan §acceptance_criteria of each task confirmed via grep + test runs documented above.
