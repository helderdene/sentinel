---
status: resolved
trigger: "No incident report for INC-2026-00014 was created after resolving the incident (Phase 17 L13 UAT)"
created: 2026-04-21T00:00:00Z
updated: 2026-04-22T00:00:00Z
resolved_at: 2026-04-21T14:59:46Z
resolution_commit: 25ec02a
---

## Current Focus

hypothesis: PDF IS generated (file exists, DB column set) but no UI/download route exposes it → user perceives it as "no report created"
test: Query DB + check storage + search UI/routes for download link
expecting: Confirm file exists, DB has path, but no route/UI reveals it
next_action: Formalize diagnosis

## Symptoms

expected: Responder resolves incident → IncidentReportJob dispatched → DomPDF generates PDF → report.pdf downloadable from admin view
actual: Incident INC-2026-00014 was resolved but no report PDF was generated
errors: [none reported in UAT — silent failure]
reproduction: Run full dispatch flow in Phase 17 UAT Test 1, resolve incident, observe no PDF
started: Post Laravel 12 → 13 upgrade (Phase 17)

## Eliminated

(none yet)

## Evidence

(gathering)

## Resolution

root_cause: |
  Missing download route + missing UI affordance for the generated incident
  report PDF. The GenerateIncidentReport job runs successfully on resolve
  (INC-2026-00014.pdf existed at storage/app/private/incident-reports/ with
  `incidents.report_pdf_url` set correctly). However, `routes/web.php` had
  NO route serving the incident PDF, and no component rendered a link or
  button to download it. Pre-existing v1.0 gap, not an L13 regression.
fix: |
  Added IncidentController::downloadReport + GET /incidents/{incident}/report.pdf
  gated by a new `download-incident-report` Gate (operators/dispatchers/
  supervisors/admins + assigned responders via raw pivot query that bypasses
  the unassigned_at scope). Added a conditional "Download Report" button on
  incidents/Show.vue wired via Wayfinder (visible only when
  report_pdf_url !== null).
verification: |
  10 Pest feature cases in tests/Feature/Incidents/DownloadReportTest.php
  cover the 200/404/403/302 matrix including post-resolution responder
  access. Live-verified during 17-HUMAN-UAT L13. Shipped 2026-04-21 in
  commit 25ec02a feat(17-04): add incident report PDF download route +
  Gate + tests.
files_changed:
  - app/Http/Controllers/IncidentController.php
  - app/Providers/AppServiceProvider.php
  - routes/web.php
  - resources/js/pages/incidents/Show.vue
  - tests/Feature/Incidents/DownloadReportTest.php
