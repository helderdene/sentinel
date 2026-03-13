---
phase: 10-update-all-pages-design-to-match-irms-intake-design-system
plan: 01
subsystem: ui
tags: [css, tailwind, design-system, shadcn, auth, cdrrmo, branding]

# Dependency graph
requires:
  - phase: 08-implement-operator-role-and-intake-layer-ui
    provides: Design system tokens (--t-*) in app.css
provides:
  - Shadcn CSS variables remapped to design system tokens (app-wide cascade)
  - DS-03 focus ring override for all Reka UI/Shadcn components
  - Shadow scale (5 levels) as CSS custom properties
  - fadeUp keyframe animation
  - CDRRMO-branded auth layout
affects: [10-02, 10-03, 10-04, all-shadcn-components, all-auth-pages]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CSS variable cascade: Shadcn vars -> design system tokens -> @theme inline -> Tailwind utilities"
    - "DS-03 focus pattern: border-color + box-shadow on [data-slot]:focus-visible"
    - "Shadow scale: 5 levels via --shadow-1 through --shadow-5 CSS custom properties"
    - "color-mix() for brand icon tinted backgrounds"

key-files:
  created: []
  modified:
    - resources/css/app.css
    - resources/js/layouts/AuthLayout.vue
    - resources/js/pages/auth/Login.vue
    - resources/js/pages/auth/ForgotPassword.vue
    - resources/js/pages/auth/VerifyEmail.vue

key-decisions:
  - "One-direction CSS variable cascade: Shadcn -> design system tokens (never reverse)"
  - "DS-03 focus ring targets [data-slot] selector for Reka UI components"
  - "Shadow scale defined as CSS custom properties for reuse across layouts"
  - "Auth layout consolidated to single CDRRMO-branded card layout"

patterns-established:
  - "Shadcn-to-token remapping: all Shadcn CSS variables reference --t-* tokens"
  - "Design system focus override: [data-slot]:focus-visible with border-color + box-shadow"
  - "Auth branding: shield icon + CDRRMO Butuan City + IRMS subtitle"

requirements-completed: [DS-01, DS-02, DS-03, DS-04]

# Metrics
duration: 4min
completed: 2026-03-14
---

# Phase 10 Plan 01: CSS Token Remapping & Auth Layout Summary

**Remapped ~30 Shadcn CSS variables to IRMS design system tokens for app-wide cascade, plus consolidated 3 auth layouts into single CDRRMO-branded layout**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-13T17:32:48Z
- **Completed:** 2026-03-13T17:36:44Z
- **Tasks:** 2
- **Files modified:** 8 (1 CSS + 1 layout + 3 auth pages + 3 deleted)

## Accomplishments
- All Shadcn CSS variables in both :root and .dark blocks now reference design system tokens -- every Shadcn component app-wide inherits design system colors
- DS-03 focus ring override implemented via [data-slot]:focus-visible with combined border-color (#2563eb) + box-shadow pattern
- AuthLayout.vue rebuilt as self-contained CDRRMO-branded layout with 52x52 shield icon, fadeUp animation, Level 4 shadow, 14px border-radius
- Three redundant auth layout variants deleted (AuthSimpleLayout, AuthCardLayout, AuthSplitLayout)

## Task Commits

Each task was committed atomically:

1. **Task 1: Remap Shadcn CSS variables to design system tokens** - `df84a6a` (feat)
2. **Task 2: Consolidate auth layouts and restyle auth pages** - `4cfe520` (feat)

## Files Created/Modified
- `resources/css/app.css` - Remapped all Shadcn CSS variables to --t-* tokens, added shadow scale, fadeUp animation, DS-03 focus ring
- `resources/js/layouts/AuthLayout.vue` - Rebuilt with CDRRMO branding, centered card, design system styling
- `resources/js/pages/auth/Login.vue` - Status message uses text-t-online instead of text-green-600
- `resources/js/pages/auth/ForgotPassword.vue` - Status message uses text-t-online instead of text-green-600
- `resources/js/pages/auth/VerifyEmail.vue` - Status message uses text-t-online instead of text-green-600
- `resources/js/layouts/auth/AuthSimpleLayout.vue` - DELETED
- `resources/js/layouts/auth/AuthCardLayout.vue` - DELETED
- `resources/js/layouts/auth/AuthSplitLayout.vue` - DELETED

## Decisions Made
- One-direction CSS variable cascade: Shadcn variables point TO design system tokens, never the reverse (prevents circular references)
- DS-03 focus ring uses [data-slot] selector to target all Reka UI/Shadcn components specifically
- Shadow scale defined as CSS custom properties (--shadow-1 through --shadow-5) for reuse across all layouts
- Auth layout consolidated to single self-contained component -- no delegation to sub-layouts

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All Shadcn components now inherit design system colors via CSS variable cascade
- Shadow scale and focus ring patterns ready for use in subsequent plans (sidebar, settings, admin, data tables)
- Auth pages complete with CDRRMO branding

## Self-Check: PASSED

All 5 modified files exist. All 3 deleted files confirmed absent. Both task commits (df84a6a, 4cfe520) verified in git log.

---
*Phase: 10-update-all-pages-design-to-match-irms-intake-design-system*
*Completed: 2026-03-14*
