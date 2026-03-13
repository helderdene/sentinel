---
phase: 7
slug: analytics
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 7 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --compact --filter=Analytics` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=Analytics`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 07-01-01 | 01 | 1 | ANLTCS-01 | feature | `php artisan test --compact tests/Feature/Analytics/KpiDashboardTest.php` | ❌ W0 | ⬜ pending |
| 07-01-02 | 01 | 1 | ANLTCS-01 | unit | `php artisan test --compact tests/Unit/AnalyticsServiceTest.php` | ❌ W0 | ⬜ pending |
| 07-01-03 | 01 | 1 | ANLTCS-02 | feature | `php artisan test --compact tests/Feature/Analytics/HeatmapTest.php` | ❌ W0 | ⬜ pending |
| 07-02-01 | 02 | 2 | ANLTCS-03 | feature | `php artisan test --compact tests/Feature/Analytics/DilgReportTest.php` | ❌ W0 | ⬜ pending |
| 07-02-02 | 02 | 2 | ANLTCS-04 | feature | `php artisan test --compact tests/Feature/Analytics/NdrrmcSitRepTest.php` | ❌ W0 | ⬜ pending |
| 07-02-03 | 02 | 2 | ANLTCS-05 | feature | `php artisan test --compact tests/Feature/Analytics/QuarterlyReportTest.php` | ❌ W0 | ⬜ pending |
| 07-02-04 | 02 | 2 | ANLTCS-06 | feature | `php artisan test --compact tests/Feature/Analytics/AnnualReportTest.php` | ❌ W0 | ⬜ pending |
| 07-01-04 | 01 | 1 | AUTH | feature | `php artisan test --compact tests/Feature/Analytics/AnalyticsAccessTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Analytics/KpiDashboardTest.php` — stubs for ANLTCS-01
- [ ] `tests/Unit/AnalyticsServiceTest.php` — stubs for ANLTCS-01 metric computation
- [ ] `tests/Feature/Analytics/HeatmapTest.php` — stubs for ANLTCS-02
- [ ] `tests/Feature/Analytics/DilgReportTest.php` — stubs for ANLTCS-03
- [ ] `tests/Feature/Analytics/NdrrmcSitRepTest.php` — stubs for ANLTCS-04
- [ ] `tests/Feature/Analytics/QuarterlyReportTest.php` — stubs for ANLTCS-05
- [ ] `tests/Feature/Analytics/AnnualReportTest.php` — stubs for ANLTCS-06
- [ ] `tests/Feature/Analytics/AnalyticsAccessTest.php` — stubs for role access
- [ ] `database/migrations/XXXX_create_generated_reports_table.php` — new table
- [ ] Framework install: `npm install chart.js vue-chartjs && composer require league/csv`

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Choropleth map renders correctly with barangay polygons | ANLTCS-02 | Visual rendering requires browser with WebGL | Open /analytics/heatmap, verify colored barangay polygons visible |
| PNG export includes legend and filter labels | ANLTCS-02 | Canvas capture quality is visual | Click Export, open PNG, verify legend and filter labels visible |
| Sparkline charts display on metric cards | ANLTCS-01 | Visual rendering verification | Open /analytics/dashboard, verify sparklines on all 5 KPI cards |
| PDF report formatting and layout | ANLTCS-03, ANLTCS-04 | PDF layout quality is visual | Download generated PDF, verify table alignment and headers |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
