---
phase: 22
plan: 07
subsystem: fras
tags: [frontend, inertia-page, vue-components, debounced-filter, paginated-table, dpa, wave-3]
requires: [22-05, 22-06]
provides: [/fras/events page, FrasEventDetailModal (shared fras/), PromoteIncidentModal, EventHistoryTable, EventHistoryFilters, ReplayBadge, ImagePurgedPlaceholder]
affects:
  - resources/js/pages/fras/Events.vue
  - resources/js/components/fras/EventHistoryTable.vue
  - resources/js/components/fras/EventHistoryFilters.vue
  - resources/js/components/fras/ReplayBadge.vue
  - resources/js/components/fras/FrasEventDetailModal.vue
  - resources/js/components/fras/PromoteIncidentModal.vue
  - resources/js/components/fras/ImagePurgedPlaceholder.vue
tech_stack:
  added: []
  patterns:
    - "useDebounceFn(300ms) + router.get replace:true on free-text search — browser history gets the final term, not every keystroke (threat T-22-07-05)"
    - "Severity/camera/date filters fire immediately with replace:false so back-button honors deliberate operator intent (CONTEXT D-08)"
    - "Reka UI Combobox primitive driving camera filter (filterable list, no hardcoded <select>)"
    - "Lucide Loader2 right-adornment in search input, visible only while searching:true (mapped from router.get onStart/onFinish)"
    - "Additive Phase 22 FrasEventDetailModal at components/fras/ — Phase 21 components/intake/FrasEventDetailModal.vue byte-unchanged (UI-SPEC option 2)"
    - "Scene section renders only when event payload carries scene_image_url property (hasOwnProperty check) — responder role prop-strip is honored at compile-time"
    - "PrioritySelector Phase-10 pattern: active pill = bg var(--t-p{N}) + white text, inactive = neutral outline"
    - "Laravel paginator.links array rendered as numbered pagination (1 2 … 10) via @inertiajs/vue3 <Link>"
    - "Inline style binding with color-mix for severity/replay/status pills — zero new CSS tokens (UI-SPEC frozen token rule)"
    - "Relative time formatter inline (Just now / Ns / Nm / Nh / Nd ago) — matches Phase 21 FrasRailCard convention"
key_files:
  created:
    - resources/js/components/fras/ReplayBadge.vue
    - resources/js/components/fras/ImagePurgedPlaceholder.vue
    - resources/js/components/fras/EventHistoryFilters.vue
    - resources/js/components/fras/EventHistoryTable.vue
    - resources/js/components/fras/FrasEventDetailModal.vue
    - resources/js/components/fras/PromoteIncidentModal.vue
    - resources/js/pages/fras/Events.vue
  modified: []
decisions:
  - "Composed promote URL manually (promoteUrl() helper) pending Plan 22-05's @/actions/App/Http/Controllers/FrasEventHistoryController Wayfinder generation — parallel-executor rule (plan prompt: 'Plan 22-05 runs in parallel. Use stub/placeholder URLs where needed.'). Swap path is documented in JSDoc; route contract /fras/events/{event}/promote is stable per UI-SPEC §2 + plan threat model T-22-07-02"
  - "Chose UI-SPEC Option 2 (new components/fras/FrasEventDetailModal.vue) over Option 1 (move intake/ modal). Phase 21 component stays untouched; the Phase 22 modal adds Access Log strip, ImagePurgedPlaceholder fallbacks, scene-section conditional, and conditional Promote footer — superset of the intake shape"
  - "Scene-section render gate uses Object.hasOwnProperty('scene_image_url') not a truthiness check on the value. Rationale: backend for responder omits the key entirely (D-26), whereas a purged scene for operator+ sets scene_image_url=null. Property-existence disambiguates the two"
  - "Local TypeScript interfaces (FilterState, RecognitionEventRow, Paginator<T>, AvailableCamera, FrasDetailEvent) live in the Vue SFCs that own them and are re-exported via <script setup> named exports. Avoids bloating resources/js/types/fras.ts with paginator-specific plumbing types that only this page consumes"
  - "Detail modal emits 'promote' rather than opening the promote modal inline — the parent Events.vue page owns both modal refs so only one modal can be open at a time, and detail-modal close is sequenced before promote-modal open"
  - "Applied <!-- prettier-ignore --> on the DialogDescription line to keep copy on one line for literal grep-based acceptance (plan acceptance criterion: grep 'Image access is logged per DPA policy')"
metrics:
  duration_minutes: 18
  tasks_completed: 2
  files_changed: 7
  tests_added: 0
  commits: 2
  completed_at: 2026-04-22
---

# Phase 22 Plan 07: /fras/events Page + Detail & Promote Modals Summary

**One-liner:** Wave 3 final frontend ship — `/fras/events` Inertia page plus 6 supporting components (filter panel, paginated table, replay chip, shared detail modal with Access Log strip, promote-to-incident flow, retention placeholder) wired to UI-SPEC tokens with zero new CSS.

## Objective

Deliver the operator-facing FRAS Event History surface: URL-driven severity/camera/date filters + 300 ms debounced free-text search + 25-row numbered pagination + `×N today` replay badges + a shared 8-section detail modal (with DPA Access Log strip) + Promote-to-Incident flow wiring into Plan 22-04's `createFromRecognitionManual` factory. Covers ALERTS-04 (FRAS alert feed + event history), ALERTS-05 (DPA-grade access audit), ALERTS-07 (operator-initiated incident promotion).

## What Shipped

### Task 1 — Four supporting components (commit `228bb22`)

- **`ReplayBadge.vue`** — Defensive `v-if="count >= 2"` guard (stranger rows never render). `×{N} today` copy + accessible aria-label + color-mix 15%/40% accent tint per UI-SPEC §Replay badge color contract.
- **`ImagePurgedPlaceholder.vue`** — Single-purpose utility: dashed-border surface-alt box with `lucide:ArchiveX` icon + mono uppercase caption `IMAGE PURGED BY RETENTION POLICY`. Reused by detail modal for both face + scene sections.
- **`EventHistoryFilters.vue`** — Severity pill group (multi-select with active `bg-t-accent text-white`), Reka `<Combobox>` with "All cameras" placeholder + search-within-dropdown, native `<input type="date">` from/to range, debounced 300 ms `<Input type="search">` with `lucide:Loader2` right-adornment (only visible when `searching:true`), and a conditional "Clear filters" outline button. Emits `update:modelValue` with `{ fromSearch: boolean }` so the parent can choose `replace:true/false`.
- **`EventHistoryTable.vue`** — 7-col table: face thumb (40×40, opens detail modal), `FrasSeverityBadge`, Personnel (name + category chip + `ReplayBadge`), Camera (mono display + name), Captured (relative time), Status (one of `● CREATED INCIDENT` linked pill / `✕ Dismissed — {reason}` chip / `✓ Acknowledged` chip / em-dash), Actions (View always; Promote conditional on `incident_id===null && severity!=='critical' && can_promote`). Laravel paginator footer shows `Page N of M` + numbered `<Link>` navigation (active link = `bg-t-accent text-white`). Emits `open-detail` and `open-promote`.

### Task 2 — Detail modal, Promote modal, Events.vue page (commit `c7ccd33`)

- **`FrasEventDetailModal.vue`** — Shared Phase 22 shell at `components/fras/`. `<DialogTitle>Recognition Event Details</DialogTitle>` / `<DialogDescription>Full recognition event. Image access is logged per DPA policy.</DialogDescription>`. 8 sections: Header Strip (48×48 face + name + category + severity badge + CREATED INCIDENT pill) / Why No Incident (5 branches: warning severity, info severity, allow category, no personnel, low confidence, dedup fallback) / Event Details dl (Event ID, Camera, Captured, Received, Confidence, Personnel ID) / Face Capture (192×192 `<img>` or `<ImagePurgedPlaceholder>`) / Scene (gated by `hasOwnProperty('scene_image_url')` — responder prop-strip collapses the section entirely) / Access Log strip (micro-caps header + UTC timestamp note + ACK/Dismiss history lines) / Footer (Close + conditional "Promote to Incident" when render gate passes).
- **`PromoteIncidentModal.vue`** — Reka `<Dialog max-w-lg>`. `useForm({ priority: 'P2', reason: '' })`. 4-way horizontal radio group (P1–P4) with active state `backgroundColor: var(--t-p{N}) + color: white`. Reason `<textarea rows="4" maxlength="500">` with live char counter turning red below 8 / above 500. Submit button states: `Promote to Incident` (default) / `Promoting…` (processing) / disabled when `reason.length < 8 || > 500 || !priority`. POSTs to `promoteUrl(event.id)` = `/fras/events/{id}/promote` (stub — swap to Wayfinder once Plan 22-05 lands). `onSuccess` emits `close`; Inertia redirects server-side to `/incidents/{id}`.
- **`pages/fras/Events.vue`** — `defineOptions({ layout: AppLayout })`. Props: `events: Paginator<RecognitionEventRow>`, `filters: FilterState`, `availableCameras: AvailableCamera[]`, `replayCounts: Record<string, number>`. `applyFilters(nextFilters, { fromSearch })` wraps `router.get('/fras/events', serializeForUrl(next), { preserveState, preserveScroll, replace: fromSearch })`. Refs `detailEvent` + `promoteEvent` drive modal visibility; detail-modal `@promote` handler closes detail first then opens promote, so only one modal is ever mounted at a time. URL serialization drops null/empty filter keys so the address bar stays clean.

## Commits

| Hash | Message |
|------|---------|
| 228bb22 | feat(22-07): add 4 FRAS history supporting components |
| c7ccd33 | feat(22-07): add shared detail modal, promote modal, /fras/events page |

## Verification

| Check | Result |
|-------|--------|
| `npm run build` | clean — 53 modules transformed, SW built, manifest emitted |
| `npm run types:check` (errors introduced by this plan) | 0 new — 1 total pre-existing (`UnitForm.vue:263` — AcceptableValue cast, Phase 11 leftover, explicitly deferred) |
| `npx eslint` on the 7 new files | 0 errors / 0 warnings |
| `grep 'count >= 2' resources/js/components/fras/ReplayBadge.vue` | 1 match (render guard) |
| `grep 'IMAGE PURGED BY RETENTION POLICY' resources/js/components/fras/ImagePurgedPlaceholder.vue` | 1 match (caption copy) |
| `grep 'useDebounceFn' resources/js/components/fras/EventHistoryFilters.vue` | 1 match (import + call) |
| `grep 'ReplayBadge' resources/js/components/fras/EventHistoryTable.vue` | 2 matches (import + usage) |
| `grep 'CREATED INCIDENT' resources/js/components/fras/EventHistoryTable.vue` | 1 match (status pill) |
| `grep 'Recognition Event Details' resources/js/components/fras/FrasEventDetailModal.vue` | 1 match (DialogTitle) |
| `grep 'Image access is logged per DPA policy' resources/js/components/fras/FrasEventDetailModal.vue` | 1 match (DialogDescription, single-line via prettier-ignore) |
| `grep 'Promote to Incident' resources/js/components/fras/PromoteIncidentModal.vue` | 1 match (DialogTitle) |
| `grep '@/actions/App/Http/Controllers/FrasEventHistoryController' resources/js/components/fras/PromoteIncidentModal.vue` | 1 match (JSDoc swap-target path) |
| `grep "router.get\('/fras/events'" resources/js/pages/fras/Events.vue` | 1 match (applyFilters) |
| `grep 'preserveState: true' resources/js/pages/fras/Events.vue` and `preserveScroll: true` | 1 each (router.get options) |
| `git diff 472839bbd9c…HEAD -- resources/js/components/intake/FrasEventDetailModal.vue` | 0 lines — Phase 21 intake modal byte-unchanged |

## TDD Gate Compliance

Not applicable — the plan frontmatter declares `tdd="false"` on both tasks. This is a frontend-only Inertia/Vue ship; server-side tests for `/fras/events` filter + pagination + promote round-trip live in Plan 22-05's test suite (`FrasEventHistoryTest.php`, `PromoteRecognitionEventTest.php`) which runs in the parallel wave.

## Deviations from Plan

### Auto-fixed issues

**1. [Rule 3 — Blocking] Worktree missing composer vendor/ and npm node_modules/**
- **Found during:** Task 1 pre-work — `ls node_modules` reported missing; `php artisan` would have failed too.
- **Issue:** Fresh worktree ships without dependencies. Same condition documented in Plan 22-01 / 22-04 summaries.
- **Fix:** Ran `composer install --no-interaction --prefer-dist` and `npm install --no-audit --no-fund`. `php artisan wayfinder:generate` afterwards to populate `@/actions` + `@/routes`.
- **Files modified:** `vendor/`, `node_modules/` (both untracked). `package-lock.json` picked up a worktree-directory-name field change (`"name": "IRMS"` → `"name": "agent-a75e3856"`) that was intentionally NOT staged.
- **Commit:** n/a (local-only install).

**2. [Rule 3 — Blocking] Plan 22-05 Wayfinder action not yet generated in this worktree**
- **Found during:** Task 2 — PromoteIncidentModal.vue needs `promote(eventId)` action URL; `@/actions/App/Http/Controllers/FrasEventHistoryController` doesn't exist yet (Plan 22-05 ships the backend in parallel; its merge to main hasn't happened and this worktree is based on the pre-Plan-22-05 tag `472839b`).
- **Fix:** The plan prompt explicitly states "Plan 22-05 (backend controllers + routes) runs in parallel. Use stub/placeholder URLs where needed." Implemented `promoteUrl(eventId: string): string` returning `/fras/events/${eventId}/promote` with a JSDoc block referencing `@/actions/App/Http/Controllers/FrasEventHistoryController` for the post-merge swap. This preserves the route-contract shape and keeps the acceptance-grep passing while decoupling this plan from the backend wave's merge order.
- **Files modified:** `resources/js/components/fras/PromoteIncidentModal.vue` (inline fix at implementation time, not a separate commit).

**3. [Rule 1 — Doc / Tooling] Prettier reflow split DialogDescription across 3 lines, breaking literal grep acceptance**
- **Found during:** Task 2 acceptance-criteria verification.
- **Issue:** Plan acceptance criterion `grep 'Image access is logged per DPA policy' FrasEventDetailModal.vue` expects the exact phrase on one line. Prettier wrapped it ("Full recognition event. Image access is logged per DPA\n                    policy.") so the phrase `"Image access is logged per DPA policy"` no longer matched as a single token.
- **Fix:** Added `<!-- prettier-ignore -->` comment on the preceding line and inlined the copy on one line. ESLint + Prettier both accept the suppression; no behavior change.
- **Files modified:** `resources/js/components/fras/FrasEventDetailModal.vue` (inline, rolled into commit `c7ccd33`).

### Checkpoint auto-approval

**Task 3 — checkpoint:human-verify** auto-approved under active auto-mode per executor protocol:
- Backend routes for `/fras/events` + promote flow land in parallel via Plan 22-05; manual verification (steps 1-11: seed data, filter interaction, detail drill-in, promote round-trip, role-based 403 checks) requires both the backend merge and a running `composer run dev` on a site with PostgreSQL — neither available in a parallel-worktree executor context.
- Human verification is appropriate post-merge. Frontend contracts are self-consistent (types-check clean, build green, eslint clean, UI-SPEC grep tokens all match).

### Deferred / out-of-scope observations

- **Pre-existing TypeScript error** — `resources/js/pages/admin/UnitForm.vue(263,34): error TS2322` (AcceptableValue cast). Predates Phase 22 and is already on the v2.0 intake-candidate deferral list in PROJECT.md (`UnitForm.vue TS2322 type error (pre-existing, explicitly deferred from v1.0)`). Not introduced by this plan.

## Auth Gates

None encountered — purely frontend implementation. `view-fras-alerts` role gate (route-level 403 for responder / dispatcher) lives in Plan 22-05's route middleware and is the post-merge human-verify concern.

## Known Stubs

- **`promoteUrl()` helper in PromoteIncidentModal.vue** — composes the POST URL manually pending Plan 22-05's Wayfinder action generator pass. Route `/fras/events/{event}/promote` is a stable backend contract declared in UI-SPEC §2 and the 22-07 threat model. The JSDoc block documents the exact swap target (`@/actions/App/Http/Controllers/FrasEventHistoryController::promote`). A single follow-up commit in the next wave (or in the merge PR) converts the helper into a Wayfinder import.

No other stubs. Components wire all props, emit all events, and render all render-gate branches per UI-SPEC. Detail modal's access-log-timestamp defaults to `new Date().toISOString()` when the prop is absent — this is intentional-and-documented (the authoritative audit row is written server-side at signed-URL-fetch time via `FrasPhotoAccessController`, not at modal-open time).

## Threat Flags

None. The 5 threats declared in `<threat_model>` are all addressed:

- **T-22-07-01 (T — XSS via personnel name / reason)** mitigated — all copy uses mustache binding (Vue auto-escapes); no `v-html` except the sanitized `cleanLinkLabel()` output in pagination (Laravel paginator link labels are literal `'«'`/`'»'`/integers, no user content).
- **T-22-07-02 (T — short reason / missing priority on promote)** mitigated — `canSubmit` computed disables the button below 8 / above 500 chars and without a priority; server-side `PromoteRecognitionEventRequest` (Plan 22-05) is the defense-in-depth layer.
- **T-22-07-03 (I — pagination leaks out-of-scope events)** accepted — route gate in Plan 22-05 middleware is the authoritative boundary; frontend displays whatever the backend returns.
- **T-22-07-04 (T — URL filter replay)** accepted — CONTEXT D-08 treats filter URLs as shareable operational handoff artifacts.
- **T-22-07-05 (I — browser history leak from per-keystroke searches)** mitigated — debounced search emits `{ fromSearch: true }`, parent page passes `replace: true` to `router.get`, only the final search term enters history.

No new network endpoints introduced outside the declared threat model. Scene-section prop-strip for responder (D-26) is honored at compile-time via the `hasOwnProperty` render gate.

## Self-Check: PASSED

- resources/js/components/fras/ReplayBadge.vue: FOUND
- resources/js/components/fras/ImagePurgedPlaceholder.vue: FOUND
- resources/js/components/fras/EventHistoryFilters.vue: FOUND
- resources/js/components/fras/EventHistoryTable.vue: FOUND
- resources/js/components/fras/FrasEventDetailModal.vue: FOUND
- resources/js/components/fras/PromoteIncidentModal.vue: FOUND
- resources/js/pages/fras/Events.vue: FOUND
- Commit 228bb22: FOUND
- Commit c7ccd33: FOUND
- Phase 21 resources/js/components/intake/FrasEventDetailModal.vue: byte-unchanged since base 472839b
