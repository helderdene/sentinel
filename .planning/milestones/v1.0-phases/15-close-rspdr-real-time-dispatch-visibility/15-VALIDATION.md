---
phase: 15
slug: close-rspdr-real-time-dispatch-visibility
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-17
---

# Phase 15 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from 15-RESEARCH.md § Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) — backend; manual verification for frontend (D-15) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=<pattern>` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~50 seconds (full); < 5 seconds (phase-focused filter) |

---

## Sampling Rate

- **After every task commit:** `php artisan test --compact tests/Feature/Responder/ChecklistTest.php tests/Feature/Responder/ResourceRequestTest.php tests/Feature/RealTime/StateSyncTest.php`
- **After every plan wave:** `php artisan test --compact`
- **Before `/gsd-verify-work`:** Full suite green + manual D-16 checklist executed
- **Max feedback latency:** ~50 seconds (full); < 5 seconds (filtered)

---

## Per-Task Verification Map

> Filled during planning. Each task that modifies source should map to one of the automated commands below OR be marked manual-only per D-15.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 15-??-?? | TBD  | TBD  | RSPDR-06    | —          | ChecklistUpdated broadcasts on dispatch.incidents | integration | `php artisan test --compact tests/Feature/Responder/ChecklistTest.php` | ✅ | ⬜ pending |
| 15-??-?? | TBD  | TBD  | RSPDR-10    | —          | ResourceRequested broadcasts on dispatch.incidents with full payload | integration | `php artisan test --compact tests/Feature/Responder/ResourceRequestTest.php` | ✅ | ⬜ pending |
| 15-??-?? | TBD  | TBD  | RSPDR-10    | —          | State-sync includes incident.resource_requests[] | integration | `php artisan test --compact tests/Feature/RealTime/StateSyncTest.php` | ✅ | ⬜ pending |
| 15-??-?? | TBD  | TBD  | RSPDR-06    | —          | Frontend subscriber mutates checklist_pct | manual (D-15) | Run D-16 manual checklist step 2 | n/a | ⬜ pending |
| 15-??-?? | TBD  | TBD  | RSPDR-10    | —          | Frontend subscriber pushes to ticker + Map + plays tone | manual (D-15) | Run D-16 manual checklist step 3 | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Gsd-planner MUST populate task IDs and waves once PLAN.md files are created.*

---

## Wave 0 Requirements

- [x] `tests/Feature/Responder/ChecklistTest.php` exists — add `Event::assertDispatched` closure with channel + payload assertions
- [x] `tests/Feature/Responder/ResourceRequestTest.php` exists — add closure assertions for 7-key payload
- [x] `tests/Feature/RealTime/StateSyncTest.php` exists — add new case for `resource_requests[]` hydration
- [x] Pest 4 + Laravel 12 toolchain already present — no install step

*All Wave 0 requirements satisfied by existing test infrastructure. No new framework install, no new fixtures.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Scene Progress bar visibility gated by status ∈ {ON_SCENE, RESOLVING, RESOLVED} | RSPDR-06 / D-02 | No Vitest harness (D-15); frontend rendering + status transitions | Select TRIAGED incident → no bar; advance to ON_SCENE → bar appears at current `checklist_pct` |
| Scene Progress bar moves in real time when responder updates checklist | RSPDR-06 / D-01 | WebSocket + DOM mutation; no frontend test harness | Open responder app, tick a checklist item → dispatch Scene Progress bar updates within ~1s without page reload; no audio cue; no ticker entry (D-03, D-04) |
| Resource request surfaces in ticker + detail-panel list + audio | RSPDR-10 / D-05, D-06, D-07, D-09 | WebSocket + DOM + Web Audio; no frontend test harness | Submit resource request from responder → dispatcher hears new tone, sees ticker entry with `resource_label`, and sees new row at top of "Resource Requests" list in detail panel |
| State-sync reload preserves resource requests | RSPDR-10 / D-08 | Hard-reload interaction; integration-shaped but tested via Pest for the API-shape portion | Make a request → Cmd+R dispatch console → Resource Requests list still shows request (Pest covers shape; manual covers user-visible behavior) |
| Audio tone is distinguishable from other tones | D-09 | Subjective acoustic judgment | Trigger new P2 incident, new message, resource request, mutual aid in quick succession → confirm each tone is distinct |

---

## Coverage Dimensions — Phase Success Criteria → Validation Type

| ROADMAP Success Criterion | Validation Type | Automated? | Assertion |
|---------------------------|-----------------|------------|-----------|
| `useDispatchFeed.ts` subscribes to `ChecklistUpdated` and mutates `localIncidents[id].checklist_pct` | Manual (D-15, D-16 step 2) | ❌ | Progress bar moves in real time |
| `useDispatchFeed.ts` subscribes to `ResourceRequested` and surfaces in ticker + detail panel + audio | Manual (D-15, D-16 step 3) | ❌ | Ticker + list + tone observed |
| Incident detail panel renders updated checklist % and resource request count reactively (no reload) | Manual (D-15, D-16 steps 2 + 3) | ❌ | Values update < 1s |
| Pest asserts events broadcast on correct channel + expected payload | Integration (Pest Feature) | ✅ | `Event::assertDispatched` closure returns true; `broadcastOn()` and `broadcastWith()` inspected |
| State-sync includes resource requests per incident | Integration (Pest Feature) | ✅ | `assertJsonPath('data.incidents.0.resource_requests.0.resource_label', ...)` |

---

## Validation Sign-Off

- [ ] All tasks mapped to `<automated>` verify OR marked manual-only per D-15
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify (frontend-only tasks may cluster — document why)
- [ ] Wave 0 covers all required test files (all exist — no gaps)
- [ ] No watch-mode flags in automated commands
- [ ] Feedback latency < 50s
- [ ] Manual D-16 checklist executed before `/gsd-verify-work`
- [ ] `nyquist_compliant: true` set in frontmatter once plans populate the task map

**Approval:** pending
