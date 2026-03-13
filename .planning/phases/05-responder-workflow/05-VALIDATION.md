---
phase: 5
slug: responder-workflow
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 with pestphp/pest-plugin-laravel |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=Responder` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=Responder`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 01 | 1 | RSPDR-02 | feature | `php artisan test --compact tests/Feature/Responder/AcknowledgeAssignmentTest.php` | ❌ W0 | ⬜ pending |
| 05-01-02 | 01 | 1 | RSPDR-03 | feature | `php artisan test --compact tests/Feature/Responder/StatusTransitionTest.php` | ❌ W0 | ⬜ pending |
| 05-01-03 | 01 | 1 | RSPDR-04 | feature | `php artisan test --compact tests/Feature/Responder/LocationUpdateTest.php` | ❌ W0 | ⬜ pending |
| 05-01-04 | 01 | 1 | RSPDR-05 | feature | `php artisan test --compact tests/Feature/Responder/MessagingTest.php` | ❌ W0 | ⬜ pending |
| 05-01-05 | 01 | 1 | RSPDR-06 | feature | `php artisan test --compact tests/Feature/Responder/ChecklistTest.php` | ❌ W0 | ⬜ pending |
| 05-01-06 | 01 | 1 | RSPDR-07 | feature | `php artisan test --compact tests/Feature/Responder/VitalsTest.php` | ❌ W0 | ⬜ pending |
| 05-01-07 | 01 | 1 | RSPDR-08 | feature | `php artisan test --compact tests/Feature/Responder/AssessmentTagsTest.php` | ❌ W0 | ⬜ pending |
| 05-01-08 | 01 | 1 | RSPDR-09 | feature | `php artisan test --compact tests/Feature/Responder/ResolutionTest.php` | ❌ W0 | ⬜ pending |
| 05-01-09 | 01 | 1 | RSPDR-10 | feature | `php artisan test --compact tests/Feature/Responder/ResourceRequestTest.php` | ❌ W0 | ⬜ pending |
| 05-01-10 | 01 | 1 | RSPDR-11 | feature | `php artisan test --compact tests/Feature/Responder/PdfGenerationTest.php` | ❌ W0 | ⬜ pending |
| 05-01-11 | 01 | 1 | RSPDR-01 | unit | `php artisan test --compact tests/Feature/Responder/AssignmentNotificationTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Responder/AcknowledgeAssignmentTest.php` — stubs for RSPDR-02
- [ ] `tests/Feature/Responder/StatusTransitionTest.php` — stubs for RSPDR-03
- [ ] `tests/Feature/Responder/LocationUpdateTest.php` — stubs for RSPDR-04
- [ ] `tests/Feature/Responder/MessagingTest.php` — stubs for RSPDR-05
- [ ] `tests/Feature/Responder/ChecklistTest.php` — stubs for RSPDR-06
- [ ] `tests/Feature/Responder/VitalsTest.php` — stubs for RSPDR-07
- [ ] `tests/Feature/Responder/AssessmentTagsTest.php` — stubs for RSPDR-08
- [ ] `tests/Feature/Responder/ResolutionTest.php` — stubs for RSPDR-09
- [ ] `tests/Feature/Responder/ResourceRequestTest.php` — stubs for RSPDR-10
- [ ] `tests/Feature/Responder/PdfGenerationTest.php` — stubs for RSPDR-11
- [ ] `tests/Feature/Responder/AssignmentNotificationTest.php` — stubs for RSPDR-01
- [ ] Framework install: `composer require barryvdh/laravel-dompdf` — DomPDF not yet in composer.json
- [ ] Hospital seeder: `database/seeders/HospitalSeeder.php` — Butuan City hospitals for outcome picker

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Audio cue plays on assignment | RSPDR-01 | Browser AudioContext requires user gesture; cannot simulate in Pest | Open responder station → dispatch assigns unit → verify audio plays |
| 44px touch targets | RSPDR-03 | Visual/dimensional check | Inspect elements in mobile devtools → verify min-h-[44px] min-w-[44px] |
| MapLibre mini-map renders | RSPDR-04 | WebGL rendering cannot be tested in Pest | Open Nav tab → verify map loads with route polyline and markers |
| GPS position updates on map | RSPDR-04 | Browser Geolocation API not available in test env | Use Chrome devtools geolocation override → verify position updates |
| Google Maps deep-link opens | RSPDR-04 | External app launch | Tap "Open in Google Maps" → verify Maps app opens with correct destination |
| Bottom sheet slide animation | RSPDR-09 | CSS animation verification | Advance to Resolving → verify outcome sheet slides up smoothly |
| In-app message banner | RSPDR-05 | WebSocket + DOM animation | Send message from dispatch → verify banner slides down on other tabs |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
