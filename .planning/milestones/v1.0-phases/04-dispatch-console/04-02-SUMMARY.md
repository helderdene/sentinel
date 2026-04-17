---
phase: 04-dispatch-console
plan: 02
subsystem: ui
tags: [maplibre, webgl, geojson, web-audio-api, dispatch, vue, composable]

# Dependency graph
requires:
  - phase: 04-dispatch-console
    provides: DispatchConsoleController with 6 actions, ProximityRankingService, Agency model, stub Console.vue
  - phase: 03-real-time
    provides: useWebSocket composable, AudioContext singleton pattern, broadcast events
  - phase: 08-implement-operator-role-and-intake-layer-ui
    provides: IntakeLayout/IntakeTopbar/IntakeStatusbar patterns, design system tokens, UserChip component
provides:
  - useDispatchMap composable with MapLibre GL JS map, WebGL circle layers, GeoJSON sources, click handlers, flyTo, smooth animation
  - useAlertSystem composable with per-priority audio tones, ack expired tone, P1 red flash
  - DispatchLayout with 56px topbar (ACTIVE/CRITICAL/TOTAL/AVG HANDLE/UNITS stats), 24px statusbar
  - DispatchTopbar and DispatchStatusbar components
  - dispatch TypeScript types (DispatchIncident, DispatchUnit, DispatchAgency, DispatchMetrics, NearbyUnit, etc.)
  - MapLegend component with priority and unit status color keys
  - Console.vue wired with map, panels, selection state
affects: [04-03, 04-04, 05-responder]

# Tech tracking
tech-stack:
  added: [maplibre-gl]
  patterns: [direct MapLibre composable (no wrapper), WebGL circle layers with GeoJSON sources, requestAnimationFrame GPS interpolation, per-priority Web Audio oscillator tones, provide/inject for layout stats]

key-files:
  created:
    - resources/js/composables/useDispatchMap.ts
    - resources/js/composables/useAlertSystem.ts
    - resources/js/types/dispatch.ts
    - resources/js/layouts/DispatchLayout.vue
    - resources/js/components/dispatch/DispatchTopbar.vue
    - resources/js/components/dispatch/DispatchStatusbar.vue
    - resources/js/components/dispatch/MapLegend.vue
  modified:
    - resources/js/pages/dispatch/Console.vue
    - resources/css/app.css
    - package.json

key-decisions:
  - "MapLibre Map type aliased as MaplibreMap to avoid conflict with native JS Map"
  - "Layout uses provide/inject pattern (matching IntakeLayout) instead of props since Inertia defineOptions layout does not receive page props"
  - "Console.vue manages panel layout directly (left/right panels as flex siblings) rather than via named slots on layout"
  - "useDispatchMap stores GeoJSON state in composable closure for style-switch re-application"

patterns-established:
  - "Direct MapLibre GL JS composable: shallowRef for map instance, addSources/addLayers on load, callback registries for click events"
  - "WebGL multi-layer markers: halo + pulse + border + core layers for rich visual hierarchy"
  - "Smooth GPS interpolation: requestAnimationFrame with ease-out cubic, cancellation tracking per unit ID"
  - "Per-priority Web Audio tones: frequency/duration record with oscillator chain creation"

requirements-completed: [DSPTCH-01, DSPTCH-02, DSPTCH-03, DSPTCH-04, DSPTCH-09]

# Metrics
duration: 7min
completed: 2026-03-13
---

# Phase 04 Plan 02: Dispatch Console Map and Layout Summary

**Full-screen MapLibre GL JS map with WebGL incident/unit marker layers, priority-based audio tones, P1 red flash, and dispatch layout shell with stats topbar**

## Performance

- **Duration:** 7 min
- **Started:** 2026-03-13T00:15:13Z
- **Completed:** 2026-03-13T00:22:13Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments
- MapLibre GL JS map rendering on Butuan City (zoom 13) with CartoCDN Dark Matter tiles, 2D locked (no pitch/rotation)
- Incident markers as 4-layer WebGL circles (halo, pulse, border, core) colored by priority with match expressions
- Unit markers as 3-layer WebGL circles (glow, border, body) colored by status with match expressions
- Connection lines between assigned unit-incident pairs as dashed GeoJSON LineString layer colored by priority
- useAlertSystem with distinct audio tones per priority level (P1: 880/660Hz x3, P2: 700Hz x2, P3: 550Hz, P4: 440Hz chime) and P1 red screen flash animation
- DispatchLayout with 56px topbar (ACTIVE, CRITICAL, TOTAL, AVG HANDLE, UNITS stats), live ticker, clock, 24px statusbar
- Dispatch TypeScript type system covering all domain entities

## Task Commits

Each task was committed atomically:

1. **Task 1: Install MapLibre, dispatch types, layout shell** - `0eed8bd` (feat)
2. **Task 2: Map composable, alert system, legend, Console.vue** - `7a6d77f` (feat)

## Files Created/Modified
- `resources/js/composables/useDispatchMap.ts` - MapLibre map initialization, GeoJSON sources, WebGL layers, flyTo, smooth animation, click handlers
- `resources/js/composables/useAlertSystem.ts` - Per-priority audio tones via Web Audio API, ack expired tone, P1 red flash CSS class toggle
- `resources/js/types/dispatch.ts` - TypeScript types for dispatch domain (DispatchIncident, DispatchUnit, DispatchAgency, DispatchMetrics, NearbyUnit, etc.)
- `resources/js/layouts/DispatchLayout.vue` - Full-screen dispatch shell with topbar, statusbar, provide/inject stats
- `resources/js/components/dispatch/DispatchTopbar.vue` - DISPATCH branding, stat pills, live ticker, clock, UserChip
- `resources/js/components/dispatch/DispatchStatusbar.vue` - Connection status, CDRRMO label, dispatcher info
- `resources/js/components/dispatch/MapLegend.vue` - Priority and unit status color keys overlay
- `resources/js/pages/dispatch/Console.vue` - Wired page with map, panels, selection state, connection lines
- `resources/css/app.css` - MapLibre CSS import, unit status tokens, P1 flash animation
- `package.json` - Added maplibre-gl dependency

## Decisions Made
- MapLibre `Map` type aliased as `MaplibreMap` to avoid TypeScript conflict with native JavaScript `Map` used for position tracking
- Layout uses provide/inject pattern (consistent with IntakeLayout) because Inertia's `defineOptions({ layout })` does not pass page props to the layout component
- Console.vue manages panel layout directly as flex siblings rather than using named slots on the layout
- useDispatchMap stores current GeoJSON data in composable closure variables for re-application after style switches

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed MapLibre Map type name collision**
- **Found during:** Task 2 (useDispatchMap composable)
- **Issue:** Importing `Map` from maplibre-gl conflicted with native JavaScript `Map` used for `unitPositions` and `activeAnimations` tracking
- **Fix:** Aliased the import as `MaplibreMap` (`import type { Map as MaplibreMap }`)
- **Files modified:** resources/js/composables/useDispatchMap.ts
- **Verification:** `npm run types:check` passes clean
- **Committed in:** 7a6d77f (Task 2 commit)

**2. [Rule 1 - Bug] Restructured DispatchLayout to not use props**
- **Found during:** Task 2 (wiring Console.vue)
- **Issue:** DispatchLayout defined `metrics` and `units` as props, but Inertia's `defineOptions({ layout })` pattern renders the layout as a wrapper and does NOT pass page props to it
- **Fix:** Removed props from DispatchLayout, initialized stats as zero refs, have Console.vue inject and populate the stats from its own page props (matching IntakeLayout's provide/inject pattern)
- **Files modified:** resources/js/layouts/DispatchLayout.vue, resources/js/pages/dispatch/Console.vue
- **Verification:** `npm run types:check` and `npm run build` both pass
- **Committed in:** 7a6d77f (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (Rule 1 - bugs)
**Impact on plan:** Both fixes were necessary for type safety and correct Inertia integration. No scope creep.

## Issues Encountered
- Vite build warns about Console chunk size (1063KB) due to bundled maplibre-gl -- expected and acceptable for the dispatch console which is a heavy map-based page. Can be optimized with dynamic import in Plan 03/04 if needed.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Map renders with all marker layers and connection lines ready for data
- Click interactions wired to selection state (selectedIncidentId, selectedUnitId) ready for panel components (Plan 03)
- useAlertSystem ready to be called on WebSocket events (Plan 04)
- Panel placeholders in Console.vue ready to be replaced with DispatchQueuePanel, IncidentDetailPanel, UnitStatusPanel (Plan 03)
- useDispatchMap.switchStyle() ready for dark/light toggle wiring

## Self-Check: PASSED

All 7 created files verified present. All 2 task commits (0eed8bd, 7a6d77f) verified in git log. TypeScript compiles clean. Vite build succeeds. Full test suite passes (305 tests, 0 failures).

---
*Phase: 04-dispatch-console*
*Completed: 2026-03-13*
