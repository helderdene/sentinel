# Roadmap: IRMS

## Overview

IRMS (Incident Response Management System) is a full-stack platform for the CDRRMO of Butuan City. It manages the full emergency incident lifecycle: Report → Intake → Triage → Dispatch → Response → Resolution → Reporting. The system has five operational layers: Intake, Dispatch, Responder, Integration, and Analytics.

As of v2.0, IRMS includes HDSystem's Face Recognition Alert System (FRAS): MQTT IP-camera ingestion, BOLO personnel enrollment, live recognition alerts, and an automated bridge that promotes Critical recognition events into dispatch-ready Incidents, all RA 10173 (Data Privacy Act) compliant.

## Milestones

- ✅ **v1.0 IRMS MVP** — Phases 1-16 (shipped 2026-04-17) → [archive](milestones/v1.0-ROADMAP.md)
- ✅ **v2.0 FRAS Integration** — Phases 17-22 (shipped 2026-04-22) → [archive](milestones/v2.0-ROADMAP.md)

## Phases

**Phase Numbering:**
- Integer phases (1-16): v1.0 milestone (shipped)
- Integer phases (17-22): v2.0 FRAS Integration (shipped)
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

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

<details>
<summary>✅ v2.0 FRAS Integration (Phases 17-22) — SHIPPED 2026-04-22</summary>

- [x] Phase 17: Laravel 12 → 13 Upgrade (4/4 plans) — Feature-free framework upgrade with byte-identical broadcast payloads; drain-and-deploy runbook; closes incident-report PDF download gap
- [x] Phase 18: FRAS Schema Port to PostgreSQL (6/6 plans) — UUID PKs, JSONB + GIN, TIMESTAMPTZ, PostGIS geography, CHECK-constrained enums, idempotency UNIQUE on recognition_events
- [x] Phase 19: MQTT Pipeline + Listener Infrastructure (6/6 plans) — Dedicated `[program:irms-mqtt]` Supervisor (not Horizon), TopicRouter + 4 handlers, listener-health watchdog banner, live-broker verified
- [x] Phase 20: Camera + Personnel Admin + Enrollment (8/8 plans) — Admin CRUD, MapLibre camera picker, FrasPhotoProcessor (Intervention Image v4), EnrollPersonnelBatch with per-camera mutex, retention expiry scheduler
- [x] Phase 21: Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail (5/5 plans) — FrasIncidentFactory bridges Critical recognitions to `IncidentChannel::IoT` at P2; dispatch map pulse; 4th IntakeStation rail
- [x] Phase 22: Alert Feed + Event History + Responder Context + DPA Compliance (9/9 plans) — `/fras/alerts` + `/fras/events`, POI accordion, public bilingual `/privacy`, access audit log, signed URLs, retention purge, CDRRMO legal sign-off CLI

**Totals:** 6 phases, 38 plans, 58 tasks — full archive: [v2.0-ROADMAP.md](milestones/v2.0-ROADMAP.md)

</details>

### 📋 v2.1 (TBD)

Next milestone to be scoped via `/gsd-new-milestone`.

## Progress

| Milestone | Phases | Plans | Status |
|-----------|--------|-------|--------|
| v1.0 IRMS MVP | 1-16 | 51/51 | ✅ Shipped 2026-04-17 |
| v2.0 FRAS Integration | 17-22 | 38/38 | ✅ Shipped 2026-04-22 |
| v2.1 (TBD) | — | — | 📋 Not yet scoped |
