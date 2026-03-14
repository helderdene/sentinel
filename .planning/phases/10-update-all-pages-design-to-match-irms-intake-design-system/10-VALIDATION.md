---
phase: 10
slug: update-all-pages-design-to-match-irms-intake-design-system
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-14
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact` |
| **Full suite command** | `php artisan test --compact && npm run build && npm run types:check` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact && npm run build`
- **After every plan wave:** Run `php artisan test --compact && npm run types:check && npm run lint`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 10-01-01 | 01 | 1 | N/A (visual) | Build | `npm run build` | N/A | ✅ green |
| 10-01-02 | 01 | 1 | N/A (visual) | Regression | `php artisan test --compact` | ✅ | ✅ green |
| 10-02-01 | 02 | 1 | N/A (visual) | Build | `npm run build && npm run types:check` | N/A | ✅ green |
| 10-03-01 | 03 | 2 | N/A (visual) | Regression | `php artisan test --compact tests/Feature/Auth/` | ✅ | ✅ green |
| 10-04-01 | 04 | 2 | N/A (visual) | Regression | `php artisan test --compact tests/Feature/Settings/` | ✅ | ✅ green |
| 10-05-01 | 05 | 3 | N/A (visual) | Build + Lint | `npm run build && npm run lint` | N/A | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new test files needed — this is a visual-only phase. Verification relies on:
- Existing Pest feature tests passing (regression)
- TypeScript compilation clean (`npm run types:check`)
- Frontend build successful (`npm run build`)
- ESLint clean (`npm run lint`)
- Visual inspection for design system compliance

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Design system visual compliance | N/A | CSS visual appearance cannot be programmatically verified | Compare each page against `docs/IRMS-Intake-Design-System.md` tokens — verify typography, colors, spacing, elevation |
| Dark mode consistency | N/A | Requires visual comparison | Toggle dark mode on each page group — verify all tokens switch correctly |
| Auth page branding | N/A | Visual/branding check | Verify CDRRMO logo, branded text, Level 4 shadow, fadeUp animation on all auth pages |
| Sidebar layout styling | N/A | Layout visual check | Verify sidebar bg, nav active states, section labels, user chip placement |
| Data table styling | N/A | Visual check | Verify row shadow, column header font, badge colors, border-left indicators |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-03-14

## Validation Audit 2026-03-14

| Metric | Count |
|--------|-------|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |

All test files pre-exist from phase execution. No new tests needed.
