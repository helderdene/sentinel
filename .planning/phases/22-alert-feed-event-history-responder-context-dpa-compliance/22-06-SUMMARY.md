---
phase: 22
plan: 06
subsystem: fras
tags: [inertia, vue, echo, wave-3, ring-buffer, audio]
requires:
  - 22-02 (FrasAlertAcknowledged event class + auth.can.view_fras_alerts gate + fras_audio_muted Inertia share)
  - 22-05 (parallel — provides FrasAlertFeedController routes + FrasAudioMuteController route; merged post-wave)
provides:
  - /fras/alerts Inertia page (AppLayout, ring-buffer scroll container, empty state)
  - useFrasFeed composable (100-entry ring buffer + cross-operator clear + critical audio gate)
  - AlertCard Vue component (face thumb + severity + category + ACK/Dismiss buttons)
  - DismissReasonModal (4-reason radio + conditional 500-char note)
  - AudioMuteToggle (Bell/BellOff header button posting to /fras/settings/audio-mute)
  - FrasAlertItem + FrasAckPayload TypeScript types extending resources/js/types/fras.ts
  - AppSidebar.vue nav entries "FRAS Alerts" + "FRAS Events" for Operator/Supervisor/Admin roles
affects:
  - resources/js/types/fras.ts (extended with 2 new exports, Phase 21 types preserved)
  - resources/js/components/AppSidebar.vue (6 new NavItem entries across 3 role branches)
tech-stack:
  added: []
  patterns:
    - Ring-buffer reactive Ref<FrasAlertItem[]> with unshift + .length truncation at MAX_ALERTS=100 (copied from useIntakeFeed)
    - Dual useEcho subscription on single private channel (RecognitionAlertReceived unshift + FrasAlertAcknowledged filter)
    - Defense-in-depth audio gate — severity=critical AND document.visibilityState='visible' AND !auth.user.fras_audio_muted
    - Sibling-composable pattern — useFrasFeed is NEW, useFrasAlerts (Phase 21 map-pulse) preserved byte-unchanged
    - Payload → display-item mapping function (mapPayloadToAlert) isolates broadcast shape coupling
    - Stub URL path strings for Plan 22-05 routes until Wayfinder regenerates post-merge
key-files:
  created:
    - resources/js/composables/useFrasFeed.ts
    - resources/js/components/fras/AudioMuteToggle.vue
    - resources/js/components/fras/AlertCard.vue
    - resources/js/components/fras/DismissReasonModal.vue
    - resources/js/pages/fras/Alerts.vue
  modified:
    - resources/js/types/fras.ts
    - resources/js/components/AppSidebar.vue
decisions:
  - "22-06-D1: Stubbed URL path strings (/fras/alerts/{id}/ack, /fras/alerts/{id}/dismiss, /fras/settings/audio-mute) instead of Wayfinder-generated action imports — Plan 22-05 controllers have not yet landed in this parallel worktree, so the action files don't exist. Wayfinder auto-regenerates on Vite dev start once the orchestrator merges both worktrees; URLs are string-stable so no downstream refactor is required."
  - "22-06-D2: mapPayloadToAlert helper isolates the RecognitionAlertPayload → FrasAlertItem shape transform — broadcast payload is flat (camera_id, personnel_name) while the display type is nested (camera: {...}, personnel: {...}). Keeping the mapping function at module scope makes the shape divergence visible to reviewers and prevents ad-hoc inline coercions if this pattern expands."
  - "22-06-D3: AppSidebar nav entries added as plain NavItems without a `can` predicate — the existing NavItem type (resources/js/types/navigation.ts) has no `can` field, and NavMain does not consult one. Visibility is gated exclusively by the role-branch scoping (entries only live in Operator/Supervisor/Admin branches). Dispatcher and Responder role branches are untouched, so their sidebars never render the FRAS entries. Defense-in-depth D-27 is upheld by this scoping + the backend gate on the routes themselves."
  - "22-06-D4: Task 3 human-verify checkpoint auto-approved under auto-mode policy — two-browser cross-operator ACK verification requires Plan 22-05 backend routes + Reverb to be running, which the parallel worktree cannot satisfy in isolation. Manual verification is deferred to the orchestrator's post-merge sanity pass."
metrics:
  duration_min: 22
  tasks: 3
  files_created: 5
  files_modified: 2
  completed_date: "2026-04-22"
---

# Phase 22 Plan 06: FRAS Live Alerts Vue Surface — Summary

**One-liner:** Wave 3 Plan 2 of 3 ships the operator-facing `/fras/alerts` live feed — a 100-entry ring-buffer Inertia page driven by `useFrasFeed` (dual-subscription on `fras.alerts` private channel), with AlertCard rendering face thumb + severity + category + ACK/Dismiss buttons, DismissReasonModal capturing the 4-reason enum with conditional 500-char note, AudioMuteToggle persisting the per-user mute preference, and AppSidebar nav entries gated to Operator/Supervisor/Admin only. Critical alerts play the reused Phase 21 `playPriorityTone('P1')` only when the tab is visible and the user has not muted.

## Tasks Completed

| Task | Name                                                                 | Commit  | Files                                                                                                                          |
| ---- | -------------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------ |
| 1    | TS types + useFrasFeed composable + AudioMuteToggle                  | 0302176 | resources/js/types/fras.ts, resources/js/composables/useFrasFeed.ts, resources/js/components/fras/AudioMuteToggle.vue          |
| 2    | AlertCard + DismissReasonModal + /fras/alerts Inertia page + sidebar | 41c9e34 | resources/js/components/fras/AlertCard.vue, resources/js/components/fras/DismissReasonModal.vue, resources/js/pages/fras/Alerts.vue, resources/js/components/AppSidebar.vue |
| 3    | Checkpoint: human-verify live feed with 2-browser ACK handoff        | —       | Auto-approved per auto-mode policy; manual verification deferred post-merge                                                    |

## Verification Results

| Check                                        | Result                                                                                   |
| -------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `npm run types:check` new-file errors        | 0 — zero TS errors in any of the 5 new files or the 2 modified files                     |
| `npm run types:check` baseline               | 15 total errors — all pre-existing `.form` accessor and UnitForm AcceptableValue issues (unrelated to this plan) |
| `npm run build`                              | Built in 11.77s; service worker 16.78 kB; 123 precache entries                            |
| `grep bg-t-online` in AlertCard.vue          | 1 — ACK button color contract per UI-SPEC                                                 |
| `grep variant="destructive"` in DismissReasonModal | 1 — Dismiss submit button color contract                                            |
| `grep False match` in DismissReasonModal     | 1 — reason enum label per UI-SPEC copy                                                    |
| `grep Siren` in AppSidebar.vue               | 4 — import + 3 usages (admin/operator/supervisor branches)                                |
| `grep History,` in AppSidebar.vue            | 4 — import + 3 usages                                                                     |
| `grep -E "fras/alerts|FRAS Alerts"` in AppSidebar | 6 — 3 hrefs + 3 titles                                                               |
| `grep FRAS Events` in AppSidebar.vue         | 3 — one per role branch                                                                   |
| Phase 21 useFrasAlerts.ts diff               | 0 lines changed (HEAD~2 → HEAD) — confirmed byte-unchanged                               |

**Grep contracts (Task 1 + Task 2 acceptance):**

- `useEcho<RecognitionAlertPayload>` + `'RecognitionAlertReceived'` both present in `useFrasFeed.ts` (Prettier wrapped the single-line pattern across 5 lines — contract semantically preserved)
- `useEcho<FrasAckPayload>` + `'FrasAlertAcknowledged'` both present
- `playPriorityTone('P1')` × 1 in `useFrasFeed.ts`
- `document.visibilityState === 'visible'` × 1 in `useFrasFeed.ts`
- `fras_audio_muted` × 3 references in `useFrasFeed.ts` (TypeScript narrowing requires multiple touches)
- `export interface FrasAlertItem` × 1 in `types/fras.ts`
- `export interface FrasAckPayload` × 1 in `types/fras.ts`

## Implementation Notes

### Dual-Subscription Ring Buffer

`useFrasFeed` registers two `useEcho` handlers on the single `fras.alerts` private channel:

1. **`RecognitionAlertReceived`** — new detections. Severity-guarded (only critical/warning enter the buffer; Info is filtered at the factory per Phase 21 D-16 but the client also guards defensively). New items `unshift` onto `alerts.value` and `alerts.value.length` is truncated to `MAX_ALERTS` (100) in-place. This is the pattern used in `useIntakeFeed` (where `.pop()` is used after length-check); Phase 22 uses the `.length = N` truncation form per PATTERNS.md §Wave 3 §useFrasFeed.ts.

2. **`FrasAlertAcknowledged`** — cross-operator clears. When *any* operator (including the current one) ACKs or Dismisses an event, the `fras_access_log`-era event class (Plan 22-02) broadcasts `event_id` + `action` + `actor_*`. This handler replaces `alerts.value` with a filtered array that drops the matching `event_id`. Reactive replacement is preferred over in-place splicing because the filter is O(n) and the buffer is capped at 100 — no meaningful perf delta, and the replacement form is more declarative.

### Defense-in-Depth Audio Gate

Three conditions must all be true for `playPriorityTone('P1')` to fire:

1. `payload.severity === 'critical'` — warning alerts are visible-only, no audio
2. `document.visibilityState === 'visible'` — minimized/backgrounded tabs never emit sound (T-22-06-03 mitigation: prevents alert leakage to nearby people when operator walks away from the workstation)
3. `!auth.user.fras_audio_muted` — per-user persisted preference, Inertia-shared via Plan 22-02

The triple-gate is intentional: each condition protects a different failure mode (severity mismatch / accidental reveal / user-consent).

### Payload → Display Mapping

The broadcast payload (`RecognitionAlertPayload`) is flat — `camera_id`, `personnel_name`, `personnel_category`. The display type (`FrasAlertItem`) is nested — `camera: { id, camera_id_display, name }`, `personnel: { id, name, category }`. The `mapPayloadToAlert` helper at module scope does the transform on each `RecognitionAlertReceived` event. Nullable personnel fields fall back to safe defaults (`id: ''`, `name: 'Unknown'`, `category: 'block'`) because the TS shape forbids null on the display type; in practice the backend only broadcasts events with a matched personnel row (Phase 21 RecognitionAlertReceived::broadcastWith omits events without personnel), so the fallbacks are defensive rather than expected.

### Stubbed URL Paths for Plan 22-05 Routes

Plan 22-05 (backend controllers + routes) runs in parallel and has not yet landed in this worktree. Three forms of backend interaction are stubbed:

- AudioMuteToggle → `router.post('/fras/settings/audio-mute', ...)` (not `audioMuteUpdate().url` — the Wayfinder action file doesn't exist yet)
- AlertCard ACK → `ackForm.post(\`/fras/alerts/\${event_id}/ack\`, ...)` (not `acknowledge(event_id).url`)
- DismissReasonModal → `form.post(\`/fras/alerts/\${eventId}/dismiss\`, ...)` (not `dismiss(eventId).url`)

When the orchestrator merges 22-05 back, Wayfinder's Vite plugin auto-regenerates `resources/js/actions/App/Http/Controllers/FrasAlertFeedController.ts` and `FrasAudioMuteController.ts` on next dev boot. The stubbed URLs match the planned route paths from 22-UI-SPEC §Surface contracts §Routes, so no behavior change is required post-merge — only an optional refactor pass to switch from path strings to typed actions.

### Sidebar Role-Branch Scoping

`AppSidebar.vue` contains a `Record<UserRole, NavItem[]>` literal that switches the visible sidebar entries per user role. Phase 22 appends 2 NavItems (FRAS Alerts + FRAS Events) to the Operator, Supervisor, and Admin branches only. Dispatcher and Responder branches are untouched — their users never see the FRAS nav entries in the first place, which is the primary visibility gate (T-22-06-06 mitigation layer 1). Backend gates on the actual routes (`can:view-fras-alerts` from Plan 22-02) provide defense-in-depth layer 2, so even if a Responder or Dispatcher types `/fras/alerts` into the URL bar directly, they get a 403.

The `NavItem` type in `resources/js/types/navigation.ts` has no `can` field (only `title`, `href`, `icon`, `isActive`, `children`), so adding `can: 'view_fras_alerts'` would be a type error. Role-branch scoping is sufficient; the type doesn't need an extension for this plan.

### AlertCard Event-Dismiss Handling

The Dismiss button inside `AlertCard.vue` does NOT open its own modal — it emits `dismiss` to the parent page (`Alerts.vue`) with the `event_id`. The parent owns the `DismissReasonModal` state (open boolean + target event id), which means:

1. Only one modal exists on the page at a time (even with 100 cards)
2. The modal state survives card re-orders (unshift new alerts) without stale references
3. The card's form state (`ackForm`) stays local — ACK and Dismiss are fully decoupled concerns

### Worktree Environment Setup

This plan executed in a parallel worktree. Initial clone had no `vendor/`, `node_modules/`, or `public/build/`. Per Wave 1/2 precedent (see 22-02-SUMMARY.md and 22-03-SUMMARY.md §Worktree Environment Setup), `vendor/` was copied (full copy for autoload path resolution), `node_modules/` and `public/build/` were symlinked from the main repo. None of these are tracked.

## Deviations from Plan

### Plan Deviations

**1. [Rule 3 - blocking] Stubbed URL paths for Plan 22-05 routes**

- **Found during:** Task 1 AudioMuteToggle authoring
- **Issue:** Plan §action referenced `import { update as audioMuteUpdate } from '@/actions/App/Http/Controllers/FrasAudioMuteController'` and similar Wayfinder action imports for AlertCard ACK and DismissReasonModal submit. The backend controllers (and thus the generated action files) don't exist in this parallel worktree.
- **Fix:** Used path-string URLs matching the planned route paths (`/fras/settings/audio-mute`, `/fras/alerts/{id}/ack`, `/fras/alerts/{id}/dismiss`). When 22-05 merges and Wayfinder regenerates, the URLs resolve identically — no downstream refactor required, optional cleanup pass can switch to typed actions.
- **Files:** resources/js/components/fras/AudioMuteToggle.vue, resources/js/components/fras/AlertCard.vue, resources/js/components/fras/DismissReasonModal.vue
- **Commits:** 0302176, 41c9e34

**2. [Rule 2 - missing functionality] NavItem `can` predicate absent from type**

- **Found during:** Task 2 AppSidebar edit
- **Issue:** Plan §action suggested `can: 'view_fras_alerts'` on the new NavItem entries as "defense-in-depth belt-and-braces". Inspected `resources/js/types/navigation.ts` — `NavItem` has no `can` field (`{ title, href, icon?, isActive?, children? }`). Adding the field would require a type change and NavMain.vue edits to consult it, which is out of scope for this plan.
- **Fix:** Rely on role-branch scoping exclusively (entries only live in Operator/Supervisor/Admin branches; Dispatcher/Responder branches unchanged). Backend gate `can:view-fras-alerts` on the actual routes provides defense-in-depth layer 2. Plan §acceptance allowed this fallback: "relying on role-branch scoping is sufficient since the role switch already gates visibility."
- **Files:** resources/js/components/AppSidebar.vue
- **Commit:** 41c9e34

### Out-of-scope Observations

**Lint auto-fix touched unrelated files**

`npm run lint --fix` modified `resources/js/components/responder/NavTab.vue` and `resources/js/pages/admin/Cameras.vue` as pre-existing lint drift unrelated to this plan. These files were NOT staged or committed — the per-task commits explicitly added only the plan's intended files. The unrelated modifications remain in the worktree working tree and will be handled by the orchestrator's main repo (or ignored, since they're pre-existing drift, not changes this plan introduced).

## Auth Gates

None encountered — all execution is pure frontend Vue/TypeScript with no external-service interaction.

## Known Stubs

None functionally — all code paths are fully wired. The three stub concerns (listed as Deviation 1) are URL-path string forms of endpoints that are backend-owned by Plan 22-05 and merge-reconciled automatically; the frontend surface itself is complete.

## Threat Flags

None. The plan's `<threat_model>` covered all introduced surface:

- T-22-06-01 (channel auth on fras.alerts): already mitigated by Phase 21 channel guard (`routes/channels.php` unchanged)
- T-22-06-02 (forged broadcast removing cards): accepted per plan (server is source of truth; page reload re-hydrates)
- T-22-06-03 (audio leakage on minimized tab): mitigated — `document.visibilityState === 'visible'` gate in `useFrasFeed.ts`
- T-22-06-04 (ring buffer memory growth): mitigated — `alerts.value.length = MAX_ALERTS` truncation
- T-22-06-05 (signed face URL leaked via history/referer): mitigated — face URLs render via `<img src>` which doesn't send a Referer to top-level navigation
- T-22-06-06 (FRAS nav visible to dispatcher/responder): mitigated — role-branch scoping excludes those branches

No new network endpoints beyond the planned 3 (ack/dismiss/mute), no new auth paths, no new file access patterns, no schema changes.

## TDD Gate Compliance

Plan frontmatter does not declare `type: tdd` and both tasks had `tdd="false"` — no RED/GREEN/REFACTOR gate sequence required. Tests for these surfaces land in Wave 4's Pest Browser suite per PATTERNS.md §Wave 3 note ("The page will be navigable but has no browser-level test yet (Wave 4 Pest Browser may add).").

## Self-Check: PASSED

**Files created (verified present):**

- `resources/js/composables/useFrasFeed.ts` — FOUND
- `resources/js/components/fras/AudioMuteToggle.vue` — FOUND
- `resources/js/components/fras/AlertCard.vue` — FOUND
- `resources/js/components/fras/DismissReasonModal.vue` — FOUND
- `resources/js/pages/fras/Alerts.vue` — FOUND

**Files modified (verified diff non-empty):**

- `resources/js/types/fras.ts` — 2 new exports appended (FrasAlertItem, FrasAckPayload); Phase 21 exports preserved
- `resources/js/components/AppSidebar.vue` — 2 new lucide imports (Siren, History) + 6 new NavItem entries (3 branches × 2 items)

**Commits (verified in `git log`):**

- `0302176` feat(22-06): add FRAS alert types + useFrasFeed composable + AudioMuteToggle — FOUND
- `41c9e34` feat(22-06): add /fras/alerts Inertia page + AlertCard + DismissReasonModal + sidebar nav — FOUND
