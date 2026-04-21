---
phase: 20-camera-personnel-admin-enrollment
plan: 07
subsystem: fras-admin-ui
tags: [wave-4, admin-ui, cameras, personnel, mapbox, echo, enrollment-panel]
requirements:
  - CAMERA-01
  - CAMERA-02
  - PERSONNEL-01
  - PERSONNEL-05
dependency_graph:
  requires:
    - plan-20-01 (EnrollmentProgressed event + fras.enrollments private channel)
    - plan-20-04 (Wayfinder AdminCameraController actions + admin.cameras routes)
    - plan-20-05 (Wayfinder AdminPersonnelController + EnrollmentController actions + photo_signed_url + enrollment_rows Inertia props)
  provides:
    - resources/js/pages/admin/Cameras.vue (full implementation replacing Plan 04 stub)
    - resources/js/pages/admin/CameraForm.vue (full implementation replacing Plan 04 stub)
    - resources/js/pages/admin/Personnel.vue (full implementation replacing Plan 05 stub)
    - resources/js/pages/admin/PersonnelForm.vue (full implementation replacing Plan 05 stub)
    - resources/js/components/admin/CameraLocationPicker.vue (ported from intake/LocationMapPicker, adds geocoding search)
    - resources/js/components/fras/EnrollmentProgressPanel.vue (live-updating per-camera status with retry + resync-all)
    - resources/js/components/fras/CameraStatusBadge.vue (reusable status pill — online/degraded/offline/decommissioned)
    - resources/js/composables/useEnrollmentProgress.ts (useEcho-backed reactive Map<camera_id, EnrollmentRow>)
  affects:
    - plan-20-08 (dispatch map cameras layer — this plan ships the admin-only surface; Plan 08 ships the operator-facing map layer)
tech-stack:
  added: []
  patterns:
    - useEcho-backed reactive Map idiom (Phase 12 precedent) — new Map(prev).set(id, row) preserves reactivity while avoiding in-place mutation
    - Wayfinder-only action binding — zero hardcoded URLs; every router.post/delete/link href routes through @/actions/* or @/routes/*
    - color-mix() 12% tint over --t-* tokens for all status badges (UI-SPEC §Color)
    - Font-mono 9px tracking-[2px] uppercase for table + section headers (v1.0 Units convention)
    - Aria-live="polite" region on enrollment row container so status transitions announce automatically to screen readers
    - Reverse-geocoded address + barangay auto-fill displayed as read-only derived fields next to the map picker (operator edits via map, not inputs)
    - Optimistic local state mutation before echo settles (retry → pending; resync-all → all pending)
key-files:
  created:
    - resources/js/components/admin/CameraLocationPicker.vue
    - resources/js/components/fras/CameraStatusBadge.vue
    - resources/js/components/fras/EnrollmentProgressPanel.vue
    - resources/js/composables/useEnrollmentProgress.ts
  modified:
    - resources/js/pages/admin/Cameras.vue (stub replaced with real implementation)
    - resources/js/pages/admin/CameraForm.vue (stub replaced with real implementation)
    - resources/js/pages/admin/Personnel.vue (stub replaced with real implementation)
    - resources/js/pages/admin/PersonnelForm.vue (stub replaced with real implementation)
decisions:
  - "20-07-D1: CameraStatusBadge rendered with `variant=\"secondary\"` on the underlying Reka Badge primitive — the secondary variant provides a neutral border-transparent base that our `color-mix()` tint classes layer on top. Without `variant=\"secondary\"`, the primitive's default `bg-primary` wins over our tint via CSS specificity. Matches v1.0 Units idiom (unit type badges all use `variant=\"secondary\"`)."
  - "20-07-D2: The CameraLocationPicker search input's `@blur` handler was extracted from the template to a named `onBlurSearch()` function and uses `window.setTimeout` rather than bare `setTimeout`. vue-tsc rejects bare `setTimeout` in template handler expressions because Vue's resolved component-instance type does not expose global DOM symbols; extracting into the script block with `window.setTimeout` is the clean fix."
  - "20-07-D3: Camera prop shape accepts both `{lat, lng}` and `{latitude, longitude}` via narrowing helpers — the controller's `$camera->location` is a Magellan Point that serializes inconsistently across edit paths (sometimes as GeoJSON, sometimes as the accessor-serialized form). Extractor functions `extractLat/extractLng` unwrap either shape into the form's flat `latitude` + `longitude` fields."
  - "20-07-D4: Checkbox components use v-model on a plain boolean ref (`hideDecommissioned`, `hideExpired`) — Reka's CheckboxRoot accepts boolean via CheckboxRootProps.modelValue; the bound Checkbox wrapper forwards props/emits unchanged, so the simple idiom works without extra transforms."
  - "20-07-D5: Photo dropzone built inline in PersonnelForm.vue (not extracted to a dedicated component) — FRAS's /resources/js/pages/personnel/Create.vue was inspected but is a citizen-app artifact, not admin-surface code. Inline implementation keeps the form self-contained, uses native drag/drop events, and enforces JPEG + 1 MB client-side before form submit (matches UI-SPEC §Error states copy verbatim)."
  - "20-07-D6: Collapsible Details + Contact sections default-open ONLY in edit mode AND when the underlying personnel row already has a value in any included field (`hasDetails` / `hasContact` computed). Create mode and edit-mode-with-blank-fields both default closed — reduces initial form height without hiding pre-existing data from the operator."
  - "20-07-D7: useEnrollmentProgress's reactive Map uses `new Map(prev).set(id, row)` replacement (Phase 12 precedent) rather than in-place `.set()` — Vue's reactivity proxy does not track Map mutations on plain refs, but replacing the ref's value triggers the effect graph cleanly. Verified by grepping `useIntakeFeed.ts` and `useDispatchFeed.ts` for equivalent patterns."
  - "20-07-D8: EnrollmentProgressPanel's `allDone` green banner renders alongside (not instead of) the per-row list — operators still want to see which cameras are done for the audit trail. The plan's UI-SPEC §Empty states lists 'All cameras synced' as a state message but is silent on co-location; chose additive rendering for information density."
metrics:
  duration: 18min
  completed_date: 2026-04-22
  tasks_completed: 2 (plus 1 auto-approved checkpoint)
  files_created: 4
  files_modified: 4
---

# Phase 20 Plan 07: Admin Cameras + Personnel UI — Summary

Closed the user-facing half of CAMERA-01, CAMERA-02, PERSONNEL-01, and PERSONNEL-05. The backend surface from Plans 04–05 is now usable by admins: 4 Inertia pages, 3 new reusable components, 1 Echo-backed composable. Every form submit and every destructive action routes through Wayfinder-generated action functions — zero hardcoded URLs anywhere in the 8 new files.

## Requirements Addressed

- **CAMERA-01** — `/admin/cameras` renders a 7-column table with status/search/hide-decommissioned filters; create/edit form embeds the map picker; decommission/recommission dialogs wired end-to-end via Wayfinder actions.
- **CAMERA-02** — `CameraLocationPicker` (port of `intake/LocationMapPicker` plus geocoding search) emits `update:coordinates` + `update:address` on click/drag/select; Barangay slot displays "(will be detected on save)" and auto-fills from the v1.0 `BarangayLookupService` server-side PostGIS lookup on submit.
- **PERSONNEL-01** — `/admin/personnel` renders a 6-column table with category/search/hide-expired/hide-decommissioned filters; create/edit form with photo dropzone + category select + consent textarea; Remove-from-Watch-list Dialog with operator-specific confirmation copy.
- **PERSONNEL-05** — `EnrollmentProgressPanel` subscribes to the `fras.enrollments` private channel and live-updates per-camera rows. Retry-one-camera button calls `retry({personnel, camera}).url`; Resync-all calls `resyncAll(personnel).url`; both optimistically mutate local state before the Echo payload lands.

## Task 1: Camera surfaces + status badge + map picker (commit 9a36cf7)

### New components

- **`components/fras/CameraStatusBadge.vue`** — Wraps the Reka Badge primitive; maps `online` / `degraded` / `offline` / `decommissioned` to the UI-SPEC §Color token table via `color-mix()` 12% tint classes. Renders a leading `●` (aria-hidden) on `degraded` state to satisfy WCAG AA on the amber tint that fails contrast alone. Used in Cameras.vue table and reserved for Plan 08's dispatch-map popup.

- **`components/admin/CameraLocationPicker.vue`** — Verbatim port of `intake/LocationMapPicker.vue` map lifecycle: `BUTUAN_CENTER = [125.5406, 8.9475]`, `BUTUAN_ZOOM = 13`, `PIN_COLOR = '#E24B4A'`, `MAP_STYLE = 'mapbox://styles/helderdene/cmmq06eqr005j01skbwodfq08'`. Extends LocationMapPicker with a forward-geocoding search input bound to the existing `useGeocodingSearch` composable (300ms debounce). On suggestion click: `map.flyTo(...)` + marker drop + `update:coordinates` + `update:address` emissions. `props.address` is rendered under the map for edit-mode pre-fill.

### New pages

- **`pages/admin/Cameras.vue`** — Verbatim structural mirror of `admin/Units.vue`:
  - Header: page Heading + `Create Camera` primary Link
  - Filter row: Input (name + device_id search), Select (All/Online/Degraded/Offline/Decommissioned), Checkbox (Hide decommissioned, default on)
  - 7-column table: ID / Name / Status / Device ID / Location (truncate max-w-[240px] + title tooltip) / Enrollments / Actions
  - Actions column: `Edit Camera` ghost Link → `Decommission` Dialog (confirm copy from UI-SPEC §Destructive) or `Recommission` ghost Button if decommissioned
  - Empty states: two copies ("No cameras yet" + filter-cleared variant) per UI-SPEC §Empty states

- **`pages/admin/CameraForm.vue`** — Structural mirror of `admin/UnitForm.vue`:
  - 3 sections: Camera Identity (name + device_id) / Placement (CameraLocationPicker + auto address + auto barangay read-only displays) / Notes (edit-mode-only textarea)
  - Edit mode renders the `ID: CAM-NN` mono banner above the form card
  - Submit: primary `Create Camera` / `Update Camera` Button + `Cancel` outline Link → index

## Task 2: Personnel surfaces + live enrollment panel + Echo composable (commit 55ab756)

### New composable + component

- **`composables/useEnrollmentProgress.ts`** — Factory function taking `(personnelId, initialRows)` and returning `{ rows: Ref<Map<camera_id, EnrollmentRow>> }`. Subscribes via `useEcho<EnrollmentProgressedPayload>('fras.enrollments', 'EnrollmentProgressed', cb)`. Inside the callback: filters by `payload.personnel_id === personnelId`, merges with any existing row (preserving `camera_name` + `camera_id_display`), sets `enrolled_at = new Date().toISOString()` when status transitions to `done`, and replaces the ref with `new Map(prev).set(id, row)` (Phase 12 reactive-Map precedent). Scope-tied — Vue cleans up on component unmount.

- **`components/fras/EnrollmentProgressPanel.vue`** — Consumes useEnrollmentProgress; renders:
  - Header strip: `Enrollment Status` mono heading + `Resync all cameras` outline Button
  - `aria-live="polite" aria-atomic="false"` div containing sorted rows (by `camera_id_display`)
  - Per row: camera_id_display (mono 10px) + camera_name + optional `last_error` (t-p1 when failed) + status Badge (color per UI-SPEC §Color) + `Retry this camera` ghost Button only when `status === 'failed'`
  - `allDone` banner ("All cameras synced. Enrollment complete.") surfaces above the rows when every row is `done`
  - Empty state: "No active cameras. Add a camera to begin enrollment."
  - Optimistic updates: retry sets one row to `pending`; resync-all sets all rows to `pending` + disables the resync button until `onFinish`

### New pages

- **`pages/admin/Personnel.vue`** — Same structural shape as Cameras.vue:
  - Filter row: search Input + Category Select + Hide-expired Checkbox + Hide-decommissioned Checkbox
  - 6-column table: Name / Category badge (per UI-SPEC color map) / Expires date + Expired badge when past / Enrollments `{done}/{total}` (color graded by failed/pending/done) / Consent icon + Tooltip (FileCheck2 green when present, FileX2 amber when missing) / Actions
  - Actions column: `Edit Personnel` + `Remove from Watch-list` Dialog (or `Restore to Watch-list` if decommissioned)

- **`pages/admin/PersonnelForm.vue`** — 6-section form:
  1. Photo — custom dropzone (JPEG, ≤1 MB, client-side validated) + current-photo preview (signed-URL from controller prop) + live object-URL preview of newly selected file
  2. Identity — name / category Select / expires_at date
  3. Details (Collapsible, default-open only if edit mode + populated) — gender Select (Unspecified/Male/Female) / birthday / id_card
  4. Contact (Collapsible, same auto-open rule) — phone / address
  5. Consent — consent_basis textarea with RA 10173 helper text
  6. Enrollment Status (edit mode only) — `<EnrollmentProgressPanel :personnel-id="personnel.id" :initial-rows="enrollment_rows ?? []">`

Form transforms `gender: 'unspecified'` → null on submit (backend expects `0 | 1 | null`) and strips empty photo/expires_at/birthday so they round-trip to `null` at the Eloquent layer.

## Output Section Answers (plan required)

1. **Wayfinder action import paths matched PATTERNS exactly.** Both `@/actions/App/Http/Controllers/Admin/AdminCameraController` and `@/actions/App/Http/Controllers/Admin/AdminPersonnelController` + `@/actions/App/Http/Controllers/Admin/EnrollmentController` resolved without surprises. `@/routes/admin/cameras` and `@/routes/admin/personnel` both present. Default import + named imports work (see Cameras.vue line 5–9 for the double-destructure idiom).

2. **Collapsible for Details + Contact:** Used the Reka-wrapped Shadcn `<Collapsible>` + `<CollapsibleTrigger>` + `<CollapsibleContent>` primitive (already shipping under `components/ui/collapsible/`). Trigger is an entire-width button showing the `<h3>` section heading on the left and a `<ChevronDown>` lucide icon on the right that rotates 180° when `v-model:open` flips true. Not native `<details>` — kept parity with the project's other disclosures (e.g. dispatch incident detail panel).

3. **Photo dropzone chosen:** Rebuilt inline in PersonnelForm.vue (D-07). Justification in decision table. Uses native HTML5 drag/drop events (`@dragover` / `@dragleave` / `@drop`), a hidden `<input type="file" accept="image/jpeg">` triggered via `.click()` from a `role="button" tabindex="0"` div, and keyboard fallback (Enter/Space). Client-side `acceptFile()` guards both MIME and size before `useForm` sees the File.

4. **ESLint/TS errors encountered + fix approach:**
   - `setTimeout` in an inline `@blur` template handler → vue-tsc TS2339 (Vue's resolved component-instance type doesn't expose global DOM symbols in templates). **Fix:** Extracted to `onBlurSearch()` in the script block using `window.setTimeout`. D-02.
   - Pre-existing `UnitForm.vue(263,34)` TS2322 from a Reka Select type narrowing issue remains — **not in scope** for Plan 07, logged as pre-existing. Did not touch UnitForm.

5. **Manual UAT results — auto-approved:**
   - Auto-mode is active for this executor run. Plan's Task 3 `checkpoint:human-verify` was auto-approved per the executor's checkpoint protocol (⚡ Auto-approved: admin cameras + personnel UI shipped).
   - Automated verification gates all green: `npm run types:check` (0 errors introduced; pre-existing UnitForm error persists), `npm run build` (3511 modules transformed in 15.9s), `npm run lint` scoped to new files (0 errors, 0 warnings).
   - Two-browser WebSocket UAT + CDRRMO operator walkthrough deferred to the phase's wave-verify gate (orchestrator owns after Plan 08 wraps Wave 4).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] `setTimeout` in template `@blur` expression fails vue-tsc.**

- **Found during:** Task 1 `npm run types:check` after initial CameraLocationPicker authoring.
- **Issue:** `vue-tsc` reports `Property 'setTimeout' does not exist on type 'CreateComponentPublicInstance<…>'` for bare `setTimeout(...)` inside a template `@blur="() => setTimeout(...)"` expression. Vue's resolved instance type does not lift DOM globals into template scope.
- **Fix:** Moved the handler into the script block as a named function `onBlurSearch()` that calls `window.setTimeout(...)`. Template now binds `@blur="onBlurSearch"` directly. Cleaner, testable, type-safe.
- **Files:** `resources/js/components/admin/CameraLocationPicker.vue`
- **Commit:** 9a36cf7

**2. [Rule 2 — Missing critical functionality] Camera prop `location` shape narrowing.**

- **Found during:** CameraForm.vue authoring.
- **Issue:** The edit-mode `camera.location` prop comes from a Magellan Point model attribute that may serialize as either `{lat, lng}` (accessor-wrapped) or `{latitude, longitude}` (GeoJSON). Plan PATTERNS showed only the `{lat, lng}` shape. Without both being handled, the form would fail to pre-fill coordinates on edit for half of the serialization paths.
- **Fix:** Added `extractLat()` / `extractLng()` helpers that narrow both shapes. Type union on the prop captures both; helpers return `null` for the unknown case and the map picker falls back to `BUTUAN_CENTER`.
- **Files:** `resources/js/pages/admin/CameraForm.vue`
- **Commit:** 9a36cf7

### Non-deviations worth noting

**A. Checkpoint auto-approval.** The plan's Task 3 is a `checkpoint:human-verify` gate with copy-heavy UAT steps. Per this executor's auto-mode protocol (`workflow._auto_chain_active === true`), human-verify checkpoints are auto-approved after automated gates pass. The two-browser Echo round-trip + CDRRMO operator walkthrough remain deferred to the phase-level wave-verify stage (orchestrator-owned). Nothing was skipped; automated gates all green and manual verification moves up one layer.

**B. Reka Badge `variant="secondary"`.** Plan PATTERNS did not specify the variant; I defaulted to `variant="secondary"` on every Badge instance because Reka's Badge CSS default (`bg-primary text-primary-foreground`) overrides the phase's `color-mix()` tint via specificity. `secondary` is the neutral no-op variant — same as v1.0 Units does for type/status badges. D-01.

## Verification

- `npm run types:check` — 0 new errors. Pre-existing `UnitForm.vue(263,34)` Select type warning is unchanged (out of Plan 07 scope).
- `npm run build` — `3511 modules transformed; ✓ built in 15.91s`. Vite manifest regenerated cleanly; service-worker bundle-size warning (PWA plugin, pre-existing) is the only `error during build` line and is cosmetic, not blocking.
- `npm run lint` (scoped to new files only via `npx eslint --fix`) — 0 errors, 0 warnings across all 8 files. (Full-tree `npm run lint` fails on `.claude/worktrees/` external artifacts — pre-existing infrastructure issue unrelated to Plan 07.)

### Grep-verifiable acceptance criteria

| Criterion | Expected | Actual |
|---|---|---|
| `BUTUAN_CENTER = [125.5406, 8.9475]` in CameraLocationPicker | 1 | 1 |
| `@/actions/App/Http/Controllers/Admin/AdminCameraController` imported in Cameras + CameraForm | 2 | 2 |
| `CameraStatusBadge` used in Cameras.vue | ≥1 | 2 |
| `useEcho<` in useEnrollmentProgress.ts | 1 | 1 |
| `fras.enrollments` in useEnrollmentProgress.ts | 1 | 1 |
| `EnrollmentProgressPanel` in PersonnelForm.vue | ≥1 | 2 |
| `Retry this camera` + `Resync all cameras` in EnrollmentProgressPanel.vue | ≥2 | 3 |
| `Remove from Watch-list` in Personnel.vue | ≥1 | 3 |

## Commits

| Task | Hash | Summary |
|---|---|---|
| 1 | 9a36cf7 | feat(20-07): ship admin cameras UI with mapbox picker + status badge |
| 2 | 55ab756 | feat(20-07): ship admin personnel UI with live enrollment progress panel |
| 3 | (auto-approved) | checkpoint:human-verify — automated gates passed; manual UAT deferred to wave-verify |

## Self-Check: PASSED

**Files verified present:**
- `resources/js/pages/admin/Cameras.vue` — FOUND (real implementation)
- `resources/js/pages/admin/CameraForm.vue` — FOUND (real implementation)
- `resources/js/pages/admin/Personnel.vue` — FOUND (real implementation)
- `resources/js/pages/admin/PersonnelForm.vue` — FOUND (real implementation)
- `resources/js/components/admin/CameraLocationPicker.vue` — FOUND
- `resources/js/components/fras/CameraStatusBadge.vue` — FOUND
- `resources/js/components/fras/EnrollmentProgressPanel.vue` — FOUND
- `resources/js/composables/useEnrollmentProgress.ts` — FOUND

**Commits verified in git log:**
- 9a36cf7 (Task 1) — FOUND
- 55ab756 (Task 2) — FOUND

**Verification gates (all green):**
- `npm run types:check` → 0 new errors (1 pre-existing UnitForm error unchanged)
- `npm run build` → 3511 modules transformed; ✓ built in 15.91s
- `npx eslint --fix` on 8 new files → clean

**ROADMAP.md not touched** per orchestrator contract.
