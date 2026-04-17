---
phase: 16-v1-0-hygiene-traceability-cleanup
reviewed: 2026-04-17T00:00:00Z
depth: standard
files_reviewed: 3
files_reviewed_list:
  - resources/js/components/intake/QueueRow.vue
  - resources/js/composables/usePushSubscription.ts
  - tests/Unit/Conventions/WayfinderConventionTest.php
findings:
  critical: 0
  warning: 0
  info: 2
  total: 2
status: clean
---

# Phase 16: Code Review Report

**Reviewed:** 2026-04-17
**Depth:** standard
**Files Reviewed:** 3
**Status:** clean (Info-only items — no bugs, no security issues)

## Summary

Phase 16 is a narrow, low-risk hygiene refactor. Three changed files were reviewed at standard depth:

1. `resources/js/components/intake/QueueRow.vue` — Swaps two hardcoded intake URL template literals for named Wayfinder action calls (`overridePriority(props.incident.id).url` / `recall(props.incident.id).url`). Function signatures, `preserveScroll`, and `onSuccess` emit semantics preserved verbatim.
2. `resources/js/composables/usePushSubscription.ts` — Swaps two hardcoded `/push-subscriptions` string literals for Wayfinder `.url()` calls (`store.url()` / `destroy.url()`). Named-import form chosen over CONTEXT.md D-07's literal default-import suggestion — the rationale (tree-shaking per skill convention + 1:1 match with `useGpsTracking.ts` analog) is sound and documented in 16-01-SUMMARY.md. XSRF + manual fetch pattern retained as specified by D-08.
3. `tests/Unit/Conventions/WayfinderConventionTest.php` — New Pest unit test using Symfony Finder to scan `resources/js/**/*.{ts,vue}` (excluding Wayfinder-generated dirs + `sw.ts`) for banned literal URL patterns. Two `it(...)` blocks execute and both pass against the clean post-swap codebase (verified: `Tests: 2 passed (2 assertions)` in 0.33s).

**Per-file verification:**
- QueueRow.vue: Wayfinder signatures (`overridePriority(string).url` / `recall(string).url`) match the generated controller (`IntakeStationController.ts:224,304`). String `props.incident.id` is accepted by the `string | { id: string } | ...` union.
- usePushSubscription.ts: `store.url()` and `destroy.url()` both resolve to `/push-subscriptions` via Wayfinder's generated definitions (`PushSubscriptionController.ts:14,70`). `method: 'POST'` / `method: 'DELETE'` strings are correctly kept on the `fetch()` call (Wayfinder's `method` field is for Inertia, not raw fetch) — this is the documented analog behavior per `useGpsTracking.ts:53`.
- WayfinderConventionTest.php: Regex patterns were verified against a set of positive and negative test inputs (see Info items below for minor coverage observations). `tests/Pest.php:21-22` auto-binds `tests/Unit/**` files to `TestCase` with no `RefreshDatabase` trait — correct for a disk-scan convention test that doesn't touch the DB.

**Scope discipline:** Per phase-context instruction, pre-existing permission-handling logic in `usePushSubscription.ts` (`isSupported`, `onMounted`, `checkExistingSubscription`) was NOT reviewed — only the import block and the `.url()` call sites in `subscribe()` (line 69) and `unsubscribe()` (line 107) are in Phase 16 scope. Those are correct.

**No critical issues. No warnings.** Two Info items below are coverage observations about the Pest guard, not defects.

## Info

### IN-01: Convention guard does not catch all future URL variants of `/push-subscriptions`

**File:** `tests/Unit/Conventions/WayfinderConventionTest.php:52`
**Issue:** The regex `#['\"`]/push-subscriptions['\"`]#` only matches when `/push-subscriptions` is immediately followed by a closing quote. If a future controller adds a subpath route like `/push-subscriptions/{id}` and a contributor writes `fetch('/push-subscriptions/123', ...)`, the guard will not fire because the character after `push-subscriptions` is `/`, not `'`/`"`/`` ` ``. Verified with a test input `'/push-subscriptions/123'` — no match.

This is not a defect (no such subpath route exists today and the Phase 16 scope is strictly the exact literal currently in the codebase), and the plan's Pest guard honours D-11's stated intent verbatim. It is noted as a known future-edge-case for when the `PushSubscriptionController` grows.

**Fix (optional, for future robustness):** Relax the trailing-character class to allow the end of the URL path as well:
```php
$pattern = "#['\"`]/push-subscriptions(?:/|['\"`])#";
```
This catches both `'/push-subscriptions'` and `'/push-subscriptions/<anything>'` while still rejecting `push-subscriptions.store` (no leading quote).

### IN-02: Intake regex matches unquoted occurrences inside comments and docstrings

**File:** `tests/Unit/Conventions/WayfinderConventionTest.php:29`
**Issue:** The regex `#/intake/[^\'"`\s]+/(override-priority|recall)#` matches any occurrence of `/intake/<non-quote-or-space>/override-priority` or `/recall`, including inside a JSDoc block comment or inline `//` comment that documents a route for reference purposes. Verified: `/intake/{incident}/override-priority` (raw, no quotes) matches.

This is a conservative, belt-and-suspenders choice and is actually desirable — it prevents a contributor from "commenting the banned URL back in" as documentation. However, if a future contributor adds a comment like `// TODO: handle /intake/{id}/recall edge case` purely for documentation, the guard will flag it.

**Fix (optional):** Either leave as-is (preferred — conservative guards catch more regressions), or narrow to quoted-only matching to mirror the push-subscriptions guard's stricter pattern:
```php
$pattern = '#[\'"`]/intake/[^\'"`\s]+/(override-priority|recall)[\'"`]#';
```
Do NOT change without team agreement — the current looser pattern is stronger regression protection and the contributor burden is minimal (documentation can just reference the route name like `intake.recall` instead of the literal URL).

---

**Suggested disposition:** Accept both Info items as documented observations, not blockers. The Pest guard as-written is fit for purpose and its test-passing state (2 assertions, 0.33s) confirms it works against the Phase 16 clean codebase. Close this review.

---

_Reviewed: 2026-04-17_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
