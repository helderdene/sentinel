---
phase: 05-responder-workflow
plan: 02
subsystem: ui
tags: [vue3, typescript, composables, websocket, geolocation, mobile-first, provide-inject]

# Dependency graph
requires:
  - phase: 03-real-time-infrastructure
    provides: Echo composables, WebSocket channel auth, useConnectionStatus
  - phase: 04-dispatch-console
    provides: useAlertSystem, useAckTimer, PriBadge, design tokens, IncidentStatus/Priority types
  - phase: 08-implement-operator-role-and-intake-layer-ui
    provides: IntakeLayout pattern, design system tokens, color-mix opacity tints
provides:
  - Responder TypeScript types (ResponderIncident, ResponderUnit, VitalsData, AssignmentPayload, etc.)
  - useResponderSession composable with WebSocket subscriptions for assignment push and messaging
  - useGpsTracking composable with interval-based geolocation broadcasting
  - ResponderLayout with mobile-first topbar + tab bar + status button
  - Station.vue page with tab switching, standby screen, and status button
affects: [05-03, 05-04, 05-responder-workflow]

# Tech tracking
tech-stack:
  added: []
  patterns: [provide-inject layout bridge, interval-based GPS broadcasting, ack timer via useIntervalFn]

key-files:
  created:
    - resources/js/types/responder.ts
    - resources/js/composables/useResponderSession.ts
    - resources/js/composables/useGpsTracking.ts
    - resources/js/layouts/ResponderLayout.vue
    - resources/js/pages/responder/Station.vue
    - resources/js/components/responder/ResponderTopbar.vue
    - resources/js/components/responder/ResponderTabbar.vue
    - resources/js/components/responder/StandbyScreen.vue
    - resources/js/components/responder/StatusButton.vue
  modified: []

key-decisions:
  - "Provide/inject bridge pattern for layout-page communication (Inertia layouts don't receive props or emit to children)"
  - "GPS broadcast URL hardcoded as /responder/update-location since Plan 01 backend not yet built (will be wired to Wayfinder action when route exists)"
  - "Ack timer implemented directly in Station.vue with useIntervalFn rather than reusing dispatch useAckTimer (different API shape -- responder gets live countdown, dispatch has static assignedAt/acknowledgedAt)"
  - "Event handlers provided via ref callbacks from page to layout (onAdvance, onShowOutcomeSheet) to enable bidirectional communication"

patterns-established:
  - "Provide/inject bridge: layout provides refs, page injects and syncs composable state to layout refs via watchers"
  - "Interval-based GPS: watchPosition for position updates, throttled broadcasting based on incident status (10s en route, 60s on scene)"
  - "Contextual tab bar: middle tab changes from Nav to Scene based on isOnScene computed without shifting tab positions"

requirements-completed: [RSPDR-01, RSPDR-02, RSPDR-03, RSPDR-04]

# Metrics
duration: 6min
completed: 2026-03-13
---

# Phase 5 Plan 02: Responder Frontend Foundation Summary

**Mobile-first responder UI shell with TypeScript types, useResponderSession/useGpsTracking composables, ResponderLayout with 44px topbar + 56px tab bar, StandbyScreen, StatusButton, and Station.vue page with tab switching**

## Performance

- **Duration:** 6 min
- **Started:** 2026-03-13T10:26:18Z
- **Completed:** 2026-03-13T10:32:18Z
- **Tasks:** 2
- **Files modified:** 9

## Accomplishments
- Created complete TypeScript type definitions for responder workflow (ResponderIncident, ResponderUnit, VitalsData, ChecklistTemplate, AssignmentPayload, MessagePayload, etc.)
- Built useResponderSession composable with WebSocket subscriptions for AssignmentPushed and MessageSent events on user.{userId} private channel
- Built useGpsTracking composable wrapping browser Geolocation API with status-aware broadcast intervals (10s en route, 60s on scene)
- Created mobile-first ResponderLayout with compact topbar, content area, fixed status button, and bottom tab bar
- Created StandbyScreen with pulsing radio wave CSS animation for idle responder state
- Created StatusButton with progressive status actions, color coding, and 90-second ack countdown display
- Created Station.vue page orchestrating all composables with provide/inject layout bridge

## Task Commits

Each task was committed atomically:

1. **Task 1: Create responder TypeScript types and composables** - `961dc29` (feat)
2. **Task 2: Create ResponderLayout, components, and Station.vue page** - `8b3f28e` (feat)

## Files Created/Modified
- `resources/js/types/responder.ts` - All responder-specific TypeScript types and interfaces
- `resources/js/composables/useResponderSession.ts` - Central state hub with WebSocket subscriptions for assignments and messages
- `resources/js/composables/useGpsTracking.ts` - GPS tracking with interval-based broadcasting via Geolocation API
- `resources/js/layouts/ResponderLayout.vue` - Mobile-first full-screen layout with topbar, tab bar, and status button
- `resources/js/pages/responder/Station.vue` - Main responder page with tab switching and composable initialization
- `resources/js/components/responder/ResponderTopbar.vue` - Compact 44px topbar with callsign, incident info, status chip
- `resources/js/components/responder/ResponderTabbar.vue` - Bottom 56px tab bar with 3 contextual tabs and unread badge
- `resources/js/components/responder/StandbyScreen.vue` - Calm standby screen with pulsing radio wave animation
- `resources/js/components/responder/StatusButton.vue` - Fixed status transition button with ack timer countdown

## Decisions Made
- **Provide/inject bridge pattern:** Inertia layouts via `defineOptions({ layout })` don't receive props from or emit events to page children. Used provide/inject with ref synchronization watchers to bridge state between layout and page. This follows the established DispatchLayout pattern but extends it with bidirectional callback refs for event handling.
- **GPS URL hardcoded:** Since Plan 01 (backend) hasn't been executed yet, the GPS tracking composable uses a hardcoded `/responder/update-location` URL. This will be updated to use a Wayfinder action import when the route is created.
- **Separate ack timer implementation:** Rather than reusing the dispatch `useAckTimer` composable (which takes `assignedAt` and `acknowledgedAt` static strings), implemented a simpler countdown in Station.vue using `useIntervalFn` that starts when an assignment notification arrives and stops when acknowledged.
- **Event callback refs:** Layout provides `onAdvance` and `onShowOutcomeSheet` as `Ref<(() => void) | null>` which the page sets. This enables the layout's StatusButton to call page-level handlers without $emit.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- TypeScript error on `connectionStatus?.value` in template: Vue auto-unwraps refs in templates, but TypeScript couldn't resolve the type of an injected `ComputedRef<string> | undefined` with `.value` access. Fixed by wrapping in a local `computed()` for clean template access.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Responder UI skeleton complete with all types, composables, and layout components
- Plan 03 can build Scene documentation tab (checklist, vitals, assessment tags) and Chat tab against the defined types and useResponderSession composable
- Plan 04 can build Assignment notification full-screen takeover, Assignment tab content, and Navigation tab against the Station.vue tab switching framework
- Backend routes (Plan 01) not yet created -- GPS tracking and status advancement will need wiring when those routes exist

## Self-Check: PASSED

All 9 created files verified present. Both task commits (961dc29, 8b3f28e) verified in git log.

---
*Phase: 05-responder-workflow*
*Completed: 2026-03-13*
