# Deferred Items - Phase 08

## Pre-existing Type Errors (from 08-01)

These type errors exist from plan 08-01 which added `operator` role and `TRIAGED` status but did not update all Record<> mappings:

1. `resources/js/components/AppSidebar.vue` — Missing `operator` key in `Record<UserRole, NavItem[]>`
2. `resources/js/pages/Dashboard.vue` — Missing `operator` key in `Record<UserRole, string>`
3. `resources/js/pages/incidents/Index.vue` — Missing `TRIAGED` key in `Record<IncidentStatus, string>`
4. `resources/js/pages/incidents/Show.vue` — Missing `TRIAGED` key in `Record<IncidentStatus, string>`

These are NOT caused by 08-02 changes and should be fixed in a subsequent plan.
