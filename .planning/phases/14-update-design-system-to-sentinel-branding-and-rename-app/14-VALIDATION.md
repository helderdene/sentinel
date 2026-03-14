---
phase: 14
slug: update-design-system-to-sentinel-branding-and-rename-app
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-15
---

# Phase 14 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact` + `npm run build`
- **After every plan wave:** Run `php artisan test --compact` + `npm run types:check` + `npm run lint`
- **Before `/gsd:verify-work`:** Full suite must be green + `npm run build` clean
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

This phase has no formal requirement IDs (rebrand phase). Validation is primarily visual with regression checks:

| Task ID | Plan | Wave | Behavior | Test Type | Automated Command | Status |
|---------|------|------|----------|-----------|-------------------|--------|
| 14-01-01 | 01 | 1 | CSS tokens remap to Sentinel palette | regression | `php artisan test --compact` | ⬜ pending |
| 14-01-02 | 01 | 1 | Font loading (Bebas Neue, DM Mono) | build | `npm run build` | ⬜ pending |
| 14-02-01 | 02 | 1 | APP_NAME = "Sentinel" | regression | `php artisan test --compact` | ⬜ pending |
| 14-02-02 | 02 | 1 | Auth page renders with new shield | smoke | `php artisan test --compact tests/Feature/Auth/` | ⬜ pending |
| 14-02-03 | 02 | 1 | Sidebar logo updated | build | `npm run build` | ⬜ pending |
| 14-03-01 | 03 | 2 | Hardcoded priority colors updated | regression | `php artisan test --compact` | ⬜ pending |
| 14-03-02 | 03 | 2 | Badge style updated | build | `npm run build` | ⬜ pending |
| 14-04-01 | 04 | 2 | Report app rebranded | build | `cd report-app && npm run build` | ⬜ pending |
| 14-04-02 | 04 | 2 | PWA manifest updated | build | `npm run build` | ⬜ pending |
| 14-04-03 | 04 | 2 | TypeScript compiles | type-check | `npm run types:check` | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new test files needed for a visual rebrand phase.

---

## Manual-Only Verifications

| Behavior | Why Manual | Test Instructions |
|----------|------------|-------------------|
| Auth page shows animated Sentinel shield | SVG animation requires visual inspection | Load login page, verify pulse ring and sweep line animate |
| Sidebar shows simplified shield icon | Visual inspection | Log in, verify sidebar icon is Sentinel shield |
| Dark/light mode colors match brand guide | Visual color matching | Toggle appearance, compare against brand guide HTML |
| Priority badges use pill+dot+border style | Visual component inspection | View incidents list, compare badge style |
| DM Mono renders for monospace elements | Font rendering check | Inspect incident codes, timestamps — verify DM Mono font |
| PWA icons show Sentinel shield on #042C53 | PWA icon rendering | Install PWA, verify home screen icon |

---

## Validation Sign-Off

- [x] All tasks have automated verify or visual check
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (none — existing infra sufficient)
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
