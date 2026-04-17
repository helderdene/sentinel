# Roadmap: IRMS

## Overview

IRMS (Incident Response Management System) is a full-stack platform for the CDRRMO of Butuan City. It manages the full emergency incident lifecycle: Report → Intake → Triage → Dispatch → Response → Resolution → Reporting. The system has five operational layers: Intake, Dispatch, Responder, Integration, and Analytics.

## Milestones

- ✅ **v1.0 IRMS MVP** — Phases 1-16 (shipped 2026-04-17) → [archive](milestones/v1.0-ROADMAP.md)
- 📋 **v2.0** — not yet scoped (run `/gsd-new-milestone` to start)

## Phases

<details>
<summary>✅ v1.0 IRMS MVP (Phases 1-16) — SHIPPED 2026-04-17</summary>

- [x] Phase 1: Foundation (3/3 plans) — PostgreSQL + PostGIS, core data models, RBAC with 4 roles, barangay boundaries
- [x] Phase 2: Intake (3/3 plans) — Multi-channel incident triage, geocoding, priority classification, dispatch queue
- [x] Phase 3: Real-Time Infrastructure (2/2 plans) — Laravel Reverb WebSocket server, broadcast events, channel auth
- [x] Phase 4: Dispatch Console (4/4 plans) — 2D MapLibre map, unit assignment, proximity ranking, audio alerts
- [x] Phase 5: Responder Workflow (4/4 plans) — Mobile-optimized assignment receipt, GPS tracking, scene docs, messaging
- [x] Phase 6: Integration Layer (3/3 plans) — Stubbed external connectors (SMS, geocoding, weather, hospital, agencies)
- [x] Phase 7: Analytics (3/3 plans) — KPI dashboard, heatmap, DILG/NDRRMC/quarterly/annual compliance reports
- [x] Phase 8: Operator Role & Intake Station (4/4 plans) — 5th role, TRIAGED status, full-screen intake station UI
- [x] Phase 9: Public Citizen Reporting App (3/3 plans) — Mobile-first Vue SPA for citizen emergency reports
- [x] Phase 10: Design System Alignment (5/5 plans) — CSS variable remapping, auth branding, data tables, token alignment
- [x] Phase 11: Implement Units CRUD (2/2 plans) — Admin CRUD with auto-generated IDs, crew assignment, decommission
- [x] Phase 12: Bi-directional Communication (4/4 plans) — Incident-level group chat, dispatch UI, responder multi-participant
- [x] Phase 13: PWA Setup (3/3 plans) — Installable PWA, service worker caching, Web Push for assignments + P1 alerts
- [x] Phase 14: Sentinel Rebrand (3/3 plans) — Full visual rebrand: navy/blue palette, DM Mono, animated shield, app rename
- [x] Phase 15: Close RSPDR Real-Time Dispatch Visibility (2/2 plans) — Gap closure for RSPDR-06, RSPDR-10 broadcast wiring
- [x] Phase 16: v1.0 Hygiene & Traceability Cleanup (3/3 plans) — Wayfinder URL swaps, REQUIREMENTS.md backfill (102→123)

**Totals:** 16 phases, 51 plans, 111 tasks — full archive: [v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md)

</details>

### 📋 v2.0 (Not Yet Scoped)

Run `/gsd-new-milestone` to define the next milestone. See STATE.md `## Deferred Items` for 5 open items carried over from v1.0 (Phase 15 human verification, chat-input debug session, etc.) that may inform v2 scope.

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundation | v1.0 | 3/3 | Complete | 2026-03-12 |
| 2. Intake | v1.0 | 3/3 | Complete | 2026-03-13 |
| 3. Real-Time Infrastructure | v1.0 | 2/2 | Complete | 2026-03-13 |
| 4. Dispatch Console | v1.0 | 4/4 | Complete | 2026-03-13 |
| 5. Responder Workflow | v1.0 | 4/4 | Complete | 2026-03-13 |
| 6. Integration Layer | v1.0 | 3/3 | Complete | 2026-03-13 |
| 7. Analytics | v1.0 | 3/3 | Complete | 2026-03-13 |
| 8. Operator Role & Intake Station | v1.0 | 4/4 | Complete | 2026-03-13 |
| 9. Public Citizen Reporting App | v1.0 | 3/3 | Complete | 2026-03-13 |
| 10. Design System Alignment | v1.0 | 5/5 | Complete | 2026-03-13 |
| 11. Implement Units CRUD | v1.0 | 2/2 | Complete | 2026-03-13 |
| 12. Bi-directional Communication | v1.0 | 4/4 | Complete | 2026-03-14 |
| 13. PWA Setup | v1.0 | 3/3 | Complete | 2026-03-14 |
| 14. Sentinel Rebrand | v1.0 | 3/3 | Complete | 2026-03-14 |
| 15. Close RSPDR Real-Time Dispatch Visibility | v1.0 | 2/2 | Complete | 2026-04-17 |
| 16. v1.0 Hygiene & Traceability Cleanup | v1.0 | 3/3 | Complete | 2026-04-17 |
