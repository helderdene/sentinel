---
phase: 21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai
plan: 4
subsystem: ui
tags: [fras, mapbox, feature-state, laravel-echo, vue3, composables, tailwindcss, design-tokens, pulseCamera]

requires:
  - phase: 21-01
    provides: RecognitionAlertReceived broadcast payload shape (11-key denorm from app/Events/RecognitionAlertReceived::broadcastWith — mirrored in types/fras.ts)
  - phase: 21-02
    provides: FrasIncidentFactory dispatching RecognitionAlertReceived with incident_id populated post-Incident create — drives rail dedup update-in-place
  - phase: 21-03
    provides: frasConfig Inertia shared prop (pulseDurationSeconds) + recentFrasEvents top-50 SSR prop with pre-signed face_image_url (5-min TTL)
  - phase: 20-08
    provides: Camera symbol + halo layers on dispatch Mapbox instance with promoteId:'id' for UUID setFeatureState addressing
provides:
  - pulseCamera(cameraId, severity) export on useDispatchMap with severity-aware feature-state paint case expressions
  - useFrasAlerts composable — fras.alerts Echo subscription driving pulseCamera
  - useFrasRail composable — SSR-seeded + Echo-hydrated ring buffer (50-event cap, event_id dedup)
  - RecognitionAlertPayload / FrasRailEvent / FrasConfig / FrasSeverity TypeScript types
  - IntakeIconFras.vue (16x16 reticle + face motif) + ChBadge/ChannelFeed 6th-rail wiring
  - --t-ch-fras design token (#0e7490 light / #22d3ee dark) with @theme inline alias
affects: [21-05, alerts-feed-phase-22, dpa-compliance-phase-22]

tech-stack:
  added: []
  patterns:
    - "Mapbox feature-state case expressions for severity-aware layer paint (reduces JS-driven repaint cost; GPU-local state)"
    - "Module-scope Map<cameraId,timeoutHandle> for per-entity pulse timeout management (re-trigger resets timer)"
    - "Composable composition: channel subscription (useFrasAlerts) delegates to visual state-machine (useDispatchMap.pulseCamera) via function parameter — avoids cross-composable coupling"
    - "Ring buffer dedup via findIndex on event_id — SSR events updated in place when mid-session broadcast lands"

key-files:
  created:
    - resources/js/types/fras.ts
    - resources/js/composables/useFrasAlerts.ts
    - resources/js/composables/useFrasRail.ts
    - resources/js/components/intake/icons/IntakeIconFras.vue
    - .planning/phases/21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai/21-04-SUMMARY.md
  modified:
    - resources/js/composables/useDispatchMap.ts
    - resources/js/components/intake/ChBadge.vue
    - resources/js/components/intake/ChannelFeed.vue
    - resources/js/composables/useIntakeFeed.ts
    - resources/css/app.css

key-decisions:
  - "[21-04]: useDispatchMap map ref is shallowRef<mapboxgl.Map | null> named `map` (read-first confirmed line 164/169) — pulseCamera uses `map.value` not `mapInstance.value`"
  - "[21-04]: circle-color camera-halo case expression uses nested fallback form [case pulse_severity=critical → #A32D2D, pulse_severity=warning → #EF9F27, CAMERA_STATUS_COLORS] — preserves existing status-colored halo when no pulse active; Mapbox accepts nested match inside case via ExpressionSpecification cast"
  - "[21-04]: useFrasAlerts takes pulseCamera as a function parameter (not via import) — avoids circular dependency and keeps the map composable a pure state machine; the page-level orchestrator wires them together"
  - "[21-04]: useFrasRail live events get face_image_path/url = null — broadcast payload doesn't carry signed URLs; Phase 22 adds live signing. SSR seed is the only source of rendered face thumbnails until then"
  - "[21-04]: useIntakeFeed.channelCounts literal record extended with FRAS: 0 — Rule 3 auto-fix cascaded from ChannelKey union extension; required for types:check parity"
  - "[21-04]: IntakeIconFras glyph chose reticle brackets + face circle + eye dots + smile arc — matches planner's D-17 face/recognition motif and preserves the 4 IntakeIcon* stroke conventions (viewBox 0 0 16 16, stroke-width 1.3, round caps/joins)"
  - "[21-04]: --t-ch-fras dark override placed in .dark block alongside surface tokens (not in the @theme inline section) — follows Sentinel dark-mode convention from Phase 14; @theme inline alias --color-t-ch-fras resolves through the cascade automatically"

patterns-established:
  - "Feature-state severity pulse: setFeatureState({pulsing:true, pulse_severity:'critical'|'warning'}) drives case expressions on multiple paint properties (radius, color, opacity, icon-size); clears after config-driven timeout; reduced-motion snaps to 500ms"
  - "Echo composable separation: one composable owns the channel subscription + payload filtering, another owns the visual state; wired together at the page/layout orchestration layer"
  - "Ring-buffer + dedup: SSR-prop seed → useEcho prepends → findIndex dedup on event_id → hard trim via array.length = cap"

requirements-completed: [INTEGRATION-01, INTEGRATION-03, INTEGRATION-04, RECOGNITION-05]

duration: 7min
completed: 2026-04-22
---

# Phase 21 Plan 4: Dispatch Map Pulse + FRAS Rail Composables + 6th-Rail Design-Token Expansion Summary

**Severity-aware Mapbox feature-state pulse on the dispatch camera layer, paired with fras.alerts Echo composables (map-pulse driver + 50-event SSR-seeded rail buffer) and the full 6th-rail design-system expansion (ChannelKey extension, IntakeIconFras glyph, --t-ch-fras token) — all with INTEGRATION-04 gate held (useDispatchFeed.ts byte-identical to HEAD).**

## Performance

- **Duration:** 7 min
- **Started:** 2026-04-22T03:53:00Z
- **Completed:** 2026-04-22T03:59:57Z
- **Tasks:** 2
- **Files modified:** 9 (5 created, 4 modified, +1 summary)

## Accomplishments

- `useDispatchMap.pulseCamera(cameraId, severity)` exports a severity-aware pulse that drives Mapbox feature-state on both camera-body (icon-size 0.55→0.88) and camera-halo (circle-radius 18→32, circle-opacity 0.15→0.35, circle-color branches critical→#A32D2D / warning→#EF9F27 / fallback CAMERA_STATUS_COLORS).
- Module-scope `pulseTimeouts` Map<cameraId, timeoutId> implements D-15 re-trigger semantics — a new alert clears the prior timer and starts a fresh one; respects `prefers-reduced-motion` (500 ms) and `frasConfig.pulseDurationSeconds` Inertia prop.
- `useFrasAlerts.ts` subscribes to `fras.alerts` / `RecognitionAlertReceived` via `useEcho` and invokes a caller-supplied pulseCamera function for Critical + Warning severities — keeps channel subscription concerns orthogonal to visual state.
- `useFrasRail.ts` implements an SSR-seeded, Echo-hydrated ring buffer (MAX_FRAS_FEED_SIZE = 50) with event_id dedup (mid-session factory dispatch updates incident_id in place) and exposes `frasEvents` Ref + `frasCount` ComputedRef.
- `types/fras.ts` publishes canonical `RecognitionAlertPayload` (11-key broadcast shape), `FrasRailEvent`, `FrasConfig`, and narrowed severity unions — consumed by all three composables and ready for Plan 05 components.
- 6th-rail wiring: `ChBadge.ChannelKey` extended with `'FRAS'`; `ChannelFeed.channelRows` appended 6th entry after WALKIN; `IntakeIconFras.vue` bespoke face-reticle glyph (16×16 viewBox, stroke-width 1.3 — matches `IntakeIconIot.vue` family).
- `--t-ch-fras` design token declared in `:root` (#0e7490) and `.dark` (#22d3ee) with `@theme inline` alias `--color-t-ch-fras` → Tailwind utility classes (`bg-t-ch-fras`, `text-t-ch-fras`, `border-t-ch-fras`) resolve in both themes.
- INTEGRATION-04 gate held: `git diff --exit-code resources/js/composables/useDispatchFeed.ts` returns 0. Pre/post SHA1 byte-identity confirmed (4e09b1d23e78001e77047236db66642fa3b14ba4).

## Task Commits

Each task was committed atomically:

1. **Task 1: Types + CSS token + IntakeIconFras + ChBadge/ChannelFeed 6th-rail wiring** — `763147c` (feat)
2. **Task 2: useDispatchMap pulseCamera + useFrasAlerts + useFrasRail** — `15997f4` (feat)

**Plan metadata:** will be appended via final commit (SUMMARY.md + STATE.md + ROADMAP.md + REQUIREMENTS.md).

## Files Created/Modified

**Created:**
- `resources/js/types/fras.ts` — RecognitionAlertPayload (11-key broadcast shape) + FrasRailEvent + FrasConfig + FrasSeverity unions; 54 LOC
- `resources/js/composables/useFrasAlerts.ts` — fras.alerts → pulseCamera driver; 34 LOC
- `resources/js/composables/useFrasRail.ts` — SSR + Echo ring buffer with dedup; 71 LOC
- `resources/js/components/intake/icons/IntakeIconFras.vue` — 16×16 reticle + face circle + eye dots + smile arc; 34 LOC

**Modified:**
- `resources/js/composables/useDispatchMap.ts` — added `usePage` import, module-scope `pulseTimeouts` Map, feature-state paint case expressions on camera-body (icon-size) and camera-halo (circle-radius, circle-color nested case fallback to CAMERA_STATUS_COLORS, circle-opacity), `pulseCamera` function body, and export in the return object
- `resources/js/components/intake/ChBadge.vue` — `ChannelKey` union extended with `'FRAS'`; `IntakeIconFras` import; `FRAS` entry added to `channels` record
- `resources/js/components/intake/ChannelFeed.vue` — `IntakeIconFras` import; `channelRows` appended 6th entry (FRAS)
- `resources/js/composables/useIntakeFeed.ts` — `channelCounts` literal record extended with `FRAS: 0` (cascaded type-fix — Rule 3 auto-fix)
- `resources/css/app.css` — `--t-ch-fras` light (#0e7490) + dark (#22d3ee) token declarations + `--color-t-ch-fras` @theme inline alias

## Decisions Made

All recorded in frontmatter `key-decisions` above. The load-bearing ones:

1. **pulseCamera closure references `map.value`** — confirmed against useDispatchMap.ts line 169 (`const map = shallowRef<mapboxgl.Map | null>(null)`). No `mapInstance.value` in this codebase.
2. **Nested case inside circle-color** — Mapbox accepts `[case, pulse_severity=X → color, pulse_severity=Y → color, <fallback ExpressionSpecification>]` with a cast to ExpressionSpecification; the existing CAMERA_STATUS_COLORS match is the fallback branch, so when no pulse is active the halo behaves exactly like before.
3. **useFrasAlerts takes pulseCamera as a parameter** (not via import) — avoids needing a shared module-level pulseCamera singleton and keeps the map composable pure; the page/layout orchestrator wires them together at a single call site.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking] Cascaded type-check failure in useIntakeFeed.ts after ChannelKey extension**
- **Found during:** Task 1 (after ChBadge `ChannelKey` extended with `'FRAS'`)
- **Issue:** `npm run types:check` surfaced `TS2741: Property 'FRAS' is missing in type '{SMS, APP, VOICE, IOT, WALKIN}' but required in type 'Record<ChannelKey, number>'` in `useIntakeFeed.ts` line 42 — the hardcoded 5-key `channelCounts` initializer no longer satisfied the extended union.
- **Fix:** Added `FRAS: 0` to the literal record so `Record<ChannelKey, number>` constraint is satisfied.
- **Files modified:** `resources/js/composables/useIntakeFeed.ts` (single line)
- **Verification:** `npm run types:check` error count before plan = 15, after Task 1 with fix = 15 (zero regression — confirmed via `git stash && npm run types:check; git stash pop`).
- **Committed in:** `763147c` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking).
**Impact on plan:** Essential for types:check parity. Plan files_modified list did not include useIntakeFeed.ts; cascaded fix documented here for continuity. No scope creep — single-line additive change downstream of the ChannelKey contract change the plan explicitly mandates.

## Issues Encountered

**Pre-existing ESLint environmental noise:** `npm run lint` fails with a `consistent-type-imports` parser error inside `.claude/worktrees/agent-a36ab216/…` — a leftover parallel-agent worktree directory not covered by `eslint.config.js` ignores. This is out-of-scope environmental noise (the `.claude/worktrees/` dir is git-untracked and outside the project's logical src tree). Per-file lint on all changed files passes cleanly via `npx eslint --no-warn-ignored <paths>`. Adding `.claude/**` to ESLint ignores is deferred as a separate hygiene task — not part of this plan's files_modified.

**Pre-existing TypeScript errors (15):** Wayfinder-generated route definitions miss `.form()` method types on auth/settings route variables. Unchanged baseline count before and after this plan (verified via stash baseline comparison). Plan 05 is scoped to Wayfinder regeneration and will resolve these.

## Verification Summary

| Gate | Result |
|------|--------|
| `test -f resources/js/types/fras.ts` | PASS |
| `test -f resources/js/components/intake/icons/IntakeIconFras.vue` | PASS |
| `grep 'FRAS' ChBadge.vue / ChannelFeed.vue` | PASS |
| `grep '--t-ch-fras' app.css` | PASS |
| `grep '#0e7490' + '#22d3ee' app.css` | PASS |
| `grep 'pulseCamera' useDispatchMap.ts` | PASS |
| `grep 'feature-state' useDispatchMap.ts` | PASS |
| `grep 'pulse_severity' useDispatchMap.ts` | PASS |
| `grep 'useEcho' + 'fras.alerts' useFrasAlerts.ts` | PASS |
| `grep 'MAX_FRAS_FEED_SIZE' useFrasRail.ts` | PASS |
| `git diff --exit-code useDispatchFeed.ts` | PASS (INTEGRATION-04 gate) |
| `npm run types:check` error count | 15 (baseline unchanged — no regression) |
| Per-file `npx eslint` on all 9 changed files | PASS |
| `php artisan test --compact --filter=Fras` | **111 passed / 341 assertions** (no backend regression) |

## Next Phase Readiness

**For Plan 05 (UI components consuming this layer):**
- Import `useFrasAlerts(pulseCamera)` in the dispatch Console.vue after destructuring `pulseCamera` from `useDispatchMap(...)`.
- Import `useFrasRail(recentFrasEvents)` in IntakeStation.vue with the SSR prop; bind `frasEvents` into the new FrasRailCard component.
- Wire ChBadge to surface `FRAS` keyed badges wherever RecognitionEvent severity needs display.
- `IntakeIconFras`, `--t-ch-fras`, and `ChannelKey='FRAS'` are all ready for consumption; Plan 05 does not need to re-discover patterns.

**Blockers / concerns:**
- None for execution. Plan 05 Wayfinder regen will resolve the 15 pre-existing `.form()` TypeScript errors.

## Self-Check: PASSED

- Created files verified present: `resources/js/types/fras.ts`, `resources/js/composables/useFrasAlerts.ts`, `resources/js/composables/useFrasRail.ts`, `resources/js/components/intake/icons/IntakeIconFras.vue`
- Commits verified in git log: `763147c`, `15997f4`
- INTEGRATION-04 gate verified: `useDispatchFeed.ts` byte-identical to pre-plan HEAD
- Backend Fras suite verified: 111 passed, 341 assertions (no regression from plan scope)

---
*Phase: 21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai*
*Completed: 2026-04-22*
