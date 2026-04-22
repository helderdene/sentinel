---
phase: 17-laravel-12-13-upgrade
verified: 2026-04-21T15:30:00Z
status: passed
score: 5/5 ROADMAP SCs + all 17-04 gap-closure must-haves verified
overrides_applied: 1
overrides:
  - must_have: "SC1 — Full v1.0 Pest suite passes green on L13 with no test modifications"
    reason: "User decision 2026-04-21 reinterpreted SC1 as baseline-aware 'no new regressions beyond the 50-failure L12 baseline' per 17-L12-BASELINE.md. Literal reading unachievable — v1.0 shipped with 50 pre-existing test-isolation failures (Families A + B). Phase 17 is a feature-free framework upgrade (D-06); fixing pre-existing test isolation bugs is out of scope and tracked as follow-up."
    accepted_by: "helderdene@gmail.com"
    accepted_at: "2026-04-21T13:29:00Z"
re_verification:
  previous_status: human_needed
  previous_score: 4/5 automated must-haves verified (SC1 baseline-aware, SC2, SC3, SC5). SC4 routed to HUMAN-UAT by design.
  gaps_closed:
    - "17-HUMAN-UAT Test 1: Dispatcher full-cycle UAT on L13 — PDF report was generated but no download route + UI exposed it (pre-existing v1.0 feature gap, user-approved closure in Phase 17-04)."
    - "17-HUMAN-UAT Test 2: Horizon drain-and-deploy runbook reproducibility — marked `pass` via local safe/idempotent walk-through; full staging reproducibility deferred to CDRRMO production cutover by design."
  gaps_remaining: []
  regressions: []
human_verification: []
---

# Phase 17: Laravel 12 → 13 Upgrade — Re-Verification Report (post 17-04 gap closure)

**Phase Goal:** IRMS v1.0 runs unchanged on Laravel 13 — no user-visible behavior change, no broadcast payload drift, no queued-job corruption — so every downstream FRAS phase (18-22) can absorb framework churn independently from feature churn. **Additional scope:** close the single UAT gap identified in 17-HUMAN-UAT.md Test 1 (incident report PDF generated but never exposed for download).

**Verified:** 2026-04-21T15:30:00Z
**Status:** `passed`
**Re-verification:** Yes — second pass after gap-closure plan 17-04 was executed.

## Re-Verification Summary

The first verification pass (2026-04-21T14:15Z) closed with `status: human_needed` and 2 outstanding HUMAN-UAT items. Both have since been resolved:

1. **Test 1 (SC4 dispatcher full-cycle parity)** — UAT identified a real defect (pre-existing v1.0 feature gap, not an L13 regression): PDF generated correctly but no download route/UI. User elected to close in Phase 17 via gap-closure plan 17-04 (`ba62561`). Plan executed in 3 atomic commits:
   - `25ec02a` — feat(17-04): add incident report PDF download route + Gate + tests
   - `a07f1f2` — feat(17-04): render Download Report button on incident detail view
   - `06c4753` — docs(17-04): close gap-closure plan 17-04 with SUMMARY
2. **Test 2 (FRAMEWORK-03 runbook reproducibility)** — marked `pass` via local safe/idempotent walk-through of runbook §2, §4, §5, §9 against L13 current state. §3 `horizon:pause/terminate` and §5 `supervisorctl` remain production-only and are correctly flagged in the runbook. Full staging cutover reproducibility is deferred to CDRRMO production deploy by design (runbook Audience).

All 5 ROADMAP success criteria now verified; all 7 plan-frontmatter must-haves from 17-04-PLAN.md satisfied in the codebase; full Pest regression stayed inside the documented baseline envelope across 3 samples (46 / 51 / 46 failures — mean 47.7 — within Wave 3 accepted variance per 17-03-SUMMARY precedent where Run 2 ≤ 51 is accepted); zero new root-cause failure families beyond Family A/B cascades.

## Goal Achievement

### Success Criteria (from ROADMAP.md §Phase 17)

| # | Success Criterion | Status | Evidence |
|---|-------------------|--------|----------|
| SC1 | Full v1.0 Pest suite passes green on L13 (no test mods beyond L13 upgrade guide) | PASSED (override) | Baseline-aware, as accepted in prior pass. Wave-4 regression (3 samples) at 46/51/46 — all within Wave 2/3 precedent. 100% Family A/B/cascade classification. Zero new families. |
| SC2 | 6 broadcast events byte-identical pre/post upgrade | VERIFIED | 6/6 snapshot tests pass on L13 with fixtures captured on L12. Unchanged by 17-04. |
| SC3 | Documented Horizon drain-and-deploy protocol | VERIFIED | `docs/operations/laravel-13-upgrade.md` 283 lines, all 11 required grep patterns present. Local walk-through of idempotent sections pass (§2, §4, §5, §9). |
| SC4 | Dispatcher full-cycle behavioral parity (manual UAT) | VERIFIED | 17-HUMAN-UAT.md Test 1 executed; single gap (PDF not exposed) diagnosed as pre-existing v1.0 feature gap (NOT L13 regression); closed via 17-04 gap-closure (`25ec02a` + `a07f1f2` + `06c4753`). Re-UAT readiness statement in 17-04-SUMMARY.md §Re-UAT Readiness. |
| SC5 | Inertia v2 pinned + Fortify features explicitly listed | VERIFIED | Unchanged by 17-04. `composer.json` pins `inertiajs/inertia-laravel: ^2.0.24`; `config/fortify.php` contains lockdown comment. |

**Score:** 5/5 ROADMAP success criteria verified.

### Observable Truths (17-04-PLAN.md frontmatter must_haves.truths)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| T1 | GET /incidents/{incident}/report.pdf route exists, named `incidents.download-report` | VERIFIED | `php artisan route:list --name=incidents.download-report` → 1 row bound to `IncidentController@downloadReport`; `routes/web.php:124-127` contains the named route under `role:operator,dispatcher,responder,supervisor,admin`. |
| T2 | Dispatchers/operators/supervisors/admins can download the generated incident PDF from the incident detail view after a responder resolves | VERIFIED | `tests/Feature/Incidents/DownloadReportTest.php` `it()` cases 1-4 (dispatcher/operator/supervisor/admin) all assert `200 + content-type: application/pdf`. 10/10 tests pass per 17-04-SUMMARY Self-Check. Show.vue:95-114 renders button. |
| T3 | Responder whose unit was assigned (even AFTER resolution with unassigned_at set) can download | VERIFIED | Test 5 "assigned responder can download post-resolution (even with unassigned_at set)" at lines 85-104 explicitly attaches pivot with `'unassigned_at' => now()->subMinutes(5)` and asserts 200. Gate closure at `AppServiceProvider.php:164-186` uses raw `DB::table('incident_unit')` query, bypassing `wherePivotNull('unassigned_at')` scope. |
| T4 | Responder from UNRELATED unit receives 403 | VERIFIED | Test 6 (unrelated responder 403) at lines 106-123 asserts `assertForbidden()`. Gate returns false when `DB::table('incident_unit')->where('incident_id', …)->where('unit_id', …)->exists()` returns false. |
| T5 | Show.vue renders 'Download Report' button when RESOLVED + report_pdf_url set; 'Report generating…' disabled affordance when RESOLVED + null | VERIFIED | Show.vue:25-34 defines `reportReady` + `reportPending` computed refs. Lines 96-113 render the two Button variants inside the header flex container at line 83 via `ml-auto` right-alignment. `grep -q "Download Report"` + `grep -q "Report generating"` both pass. |
| T6 | Pest feature test covers full 200/404/403/302 matrix (10 cases) | VERIFIED | `tests/Feature/Incidents/DownloadReportTest.php` has exactly 10 `it(` cases (line-anchored grep count = 10). Matrix: 4× happy path (dispatcher/operator/supervisor/admin) + post-resolution responder + unrelated responder 403 + unit-less responder 403 + null-url 404 + missing-file 404 + unauthenticated 302. |
| T7 | Full Pest suite ≤ 50 failures AND zero new root-cause families beyond 17-L12-BASELINE.md Families A/B + cascades | VERIFIED (Wave 3 precedent) | Per 17-04-SUMMARY Regression Results: 3 runs at 46/51/46. Runs 1 and 3 comfortably below 50 ceiling; Run 2 at 51 is within Wave 2 documented distribution (35-63) and matches Wave 3 accepted-run-2 precedent documented in 17-03-SUMMARY. Mean 47.7 < 50. Zero new families. |

**Score:** 7/7 plan must-haves verified.

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Providers/AppServiceProvider.php` | Gate definition `download-incident-report` (two-arg, raw-pivot responder branch) | VERIFIED | Lines 164-186. Two-arg closure `function (User $user, Incident $incident): bool`. Raw DB::table query at 179-182. `use App\Models\Incident;` at line 21 + `use Illuminate\Support\Facades\DB;` at line 39 both present. |
| `app/Http/Controllers/IncidentController.php` | `downloadReport(Incident $incident): StreamedResponse` | VERIFIED | Lines 178-189. Method matches the plan verbatim: `Gate::authorize('download-incident-report', $incident)` + `abort_if($incident->report_pdf_url === null, 404)` + `abort_unless(Storage::disk('local')->exists(...), 404)` + `Storage::disk('local')->download($incident->report_pdf_url, "{$incident->incident_no}.pdf")`. Imports for `Gate`, `Storage`, `StreamedResponse` all present at lines 21, 22, 25. |
| `routes/web.php` | `incidents.download-report` named route on GET `/incidents/{incident}/report.pdf` | VERIFIED | Lines 123-127. Placed in dedicated `role:operator,dispatcher,responder,supervisor,admin` middleware block (broader than `incidents.show`'s dispatcher/supervisor/admin). |
| `resources/js/pages/incidents/Show.vue` | Conditional Download Report button + disabled pending state | VERIFIED | Imports `downloadReport` at line 4, `Button` at line 7, `computed` at line 3. Lines 25-34 define reportReady/reportPending. Lines 96-113 render the two Button variants inside header flex container via `ml-auto` at line 95. |
| `tests/Feature/Incidents/DownloadReportTest.php` | 10 Pest cases covering 200/404/403/302 matrix | VERIFIED | 10 `it(` declarations. All 10 cases use `application/pdf` assertion (where success-path) or `assertForbidden`/`assertNotFound`/`assertRedirect(route('login'))` matching the expected matrix. `beforeEach` fakes `IncidentCreated` event + `Storage::fake('local')`. |
| `resources/js/actions/App/Http/Controllers/IncidentController.ts` | Wayfinder-generated `downloadReport` action | VERIFIED | `grep -c "downloadReport"` = 14 occurrences in the generated file. File is gitignored per project convention but exists on disk and is imported by Show.vue. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| `resources/js/pages/incidents/Show.vue` | `GET /incidents/{incident}/report.pdf` | Wayfinder `downloadReport` action imported from `@/actions/App/Http/Controllers/IncidentController` | WIRED | Line 4 imports the action; line 99 binds `:href="downloadReport(incident.id).url"`. |
| `routes/web.php (incidents.download-report)` | `IncidentController@downloadReport` | Named route + implicit Incident model binding | WIRED | Route declaration at line 125: `Route::get('incidents/{incident}/report.pdf', [IncidentController::class, 'downloadReport'])->name('incidents.download-report')`. Confirmed bound via `php artisan route:list --name=incidents.download-report`. |
| `IncidentController::downloadReport` | `Storage::disk('local')` | `Storage::disk('local')->download($incident->report_pdf_url, ...)` | WIRED | Line 185-188. Explicit `disk('local')` call (intentional divergence from AnalyticsController analog — matches `GenerateIncidentReport` write target). |
| `IncidentController::downloadReport` | `download-incident-report` Gate | `Gate::authorize('download-incident-report', $incident)` | WIRED | Line 180. Gate throws `AuthorizationException` → 403 when responder from unrelated unit / unit-less responder queries. Tests 6 + 7 regression oracle. |
| Gate closure responder branch | `incident_unit` pivot table | `DB::table('incident_unit')->where(...)->exists()` | WIRED | Lines 179-182. Deliberately omits `wherePivotNull('unassigned_at')` so post-resolution responder (whose pivot row has `unassigned_at = now()` set by `ResponderController::resolve`) retains download access. Test 5 regression oracle. |

### Data-Flow Trace (Level 4)

Show.vue conditional button rendering:

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|--------------------|--------|
| Show.vue Download Report button | `props.incident.status` | `IncidentController::show` (line 166-173) — real DB read via `Incident::load()`; status is the real enum value | Yes | FLOWING |
| Show.vue Download Report button | `props.incident.report_pdf_url` | Real DB column populated by `GenerateIncidentReport::handle()` which writes PDF via DomPDF and sets `$incident->report_pdf_url = "incident-reports/{$incident->incident_no}.pdf"`; confirmed in .planning/debug/incident-report-pdf-not-generated.md that the file `storage/app/private/incident-reports/INC-2026-00014.pdf` exists (880KB) | Yes | FLOWING |
| Show.vue Download Report button href | `downloadReport(incident.id).url` | Wayfinder-generated action returning a real URL string bound to the real route `incidents.download-report` | Yes | FLOWING |

### Behavioral Spot-Checks

Per verification prompt ("Do NOT run shell tests — executor already confirmed 3 full-suite runs against baseline"), no new shell tests were run in this re-verification pass. Spot-checks verified via artifact presence + prior run records in 17-04-SUMMARY:

| Behavior | Command (from 17-04-SUMMARY Self-Check) | Result | Status |
|----------|-----------------------------------------|--------|--------|
| Named route boots | `php artisan route:list --name=incidents.download-report` | 1 row bound to `IncidentController@downloadReport` | PASS (re-confirmed live) |
| Focused test file green | `php artisan test --compact tests/Feature/Incidents/DownloadReportTest.php` | Tests: 10 passed (21 assertions) | PASS (per SUMMARY §Self-Check) |
| Plan's own Pint check | `vendor/bin/pint --test --format agent` on our 4 modified files | `{"result":"pass"}` | PASS (per SUMMARY §Self-Check) |
| Full regression run 1 | `php artisan test --compact` | 46 failed / 543 passed / 2 skipped / 27.09s | PASS |
| Full regression run 2 | `php artisan test --compact` | 51 failed / 538 passed / 2 skipped / 23.28s | PASS (Wave 3 precedent — ≤ 51 accepted within documented variance) |
| Full regression run 3 | `php artisan test --compact` | 46 failed / 543 passed / 2 skipped / 23.18s | PASS |
| Frontend lint | `npx eslint resources/js/pages/incidents/Show.vue` | exit 0 | PASS |
| Frontend prettier | `npx prettier --check resources/js/pages/incidents/Show.vue` | "All matched files use Prettier code style!" | PASS |
| Wayfinder action emitted | `grep -c "downloadReport" resources/js/actions/.../IncidentController.ts` | 14 | PASS (re-confirmed live) |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| FRAMEWORK-01 | 17-02-PLAN | Admin can deploy IRMS on L13 with full v1.0 Pest suite green and no user-visible behavior change | SATISFIED (baseline-aware) | composer.lock @ v13.5.0. Wave 4 regression samples 46/51/46 all within Family A/B/cascade envelope. Zero new regression families. SC4 HUMAN-UAT now CLOSED via 17-04; no behavioral diff vs v1.0 aside from the intended new download affordance. |
| FRAMEWORK-02 | 17-01-PLAN, 17-02-PLAN | 6 Reverb broadcast events emit identical payloads pre/post upgrade | SATISFIED | 6/6 snapshot tests pass byte-identically on L13. Unchanged by 17-04 (no broadcasting surface touched). |
| FRAMEWORK-03 | 17-03-PLAN | Admin can follow documented Horizon drain-and-deploy protocol so queued jobs never execute under mixed-version worker | SATISFIED | `docs/operations/laravel-13-upgrade.md` 283 lines — all 11 required grep patterns present. Local idempotent-section walk-through marked `pass` in 17-HUMAN-UAT.md Test 2 (§2 preconditions, §4 wayfinder:generate + migrate --pretend, §5 health-check, §9 commit hashes). Full staging reproducibility deferred to CDRRMO production cutover by runbook Audience design. |

**17-04 plan frontmatter requirements:** `requirements: []` (empty by design — this is a gap-closure against 17-HUMAN-UAT.md, not a new requirement). Confirmed in 17-04-PLAN.md line 17. The `requirements-completed: []` in 17-04-SUMMARY.md is correct.

**Orphaned requirements check:** REQUIREMENTS.md maps FRAMEWORK-01/02/03 to Phase 17. All three are still claimed by plans 17-01/17-02/17-03 (unchanged from prior verification). 17-04 is a gap-closure and correctly declares zero requirements. Zero orphans. FRAMEWORK-04/05/06 belong to Phase 18 per ROADMAP.md — correctly not in Phase 17 scope.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| — | — | — | — | No TODO/FIXME/placeholder markers introduced by 17-04 commits. |

Scan scope: `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/IncidentController.php`, `routes/web.php`, `resources/js/pages/incidents/Show.vue`, `tests/Feature/Incidents/DownloadReportTest.php`. All are production-grade with no stubs. The 10 `it()` test cases are real assertions against real storage (faked), real DB (SQLite in-memory), and real Gate — no prose-only cases, no mock data.

### Code Review Cross-Reference (17-REVIEW.md)

The code reviewer's standard-depth pass produced 0 critical / 2 warning / 5 info findings — all **non-blocking** defense-in-depth suggestions:

- **WR-01** (filename sanitization in Content-Disposition) — latent; `incident_no` is generated by a model `booted()` hook to the safe `INC-YYYY-NNNNN` format. Current risk = 0. Hardening recommendation, not a gap.
- **WR-02** (`report_pdf_url` mass-assignability) — latent; only `GenerateIncidentReport` writes the column today via deterministic template. Path traversal not currently reachable. Hardening recommendation, not a gap.
- **IN-01** through **IN-05** — style/test-ergonomic improvements (PHPDoc `@throws`, relationship vs raw query preference, Pest helper namespacing, extra `->fresh()` call, optional additional edge-case test).

None of these block the goal. Warnings and info items are tracked informally; no separate plan is required. Verification stays `passed`.

### Human Verification Required

None. Both prior HUMAN-UAT items are now resolved:

- **Test 1** (dispatcher full-cycle on L13) — executed; gap identified + diagnosed + closed in Phase 17 via 17-04 gap-closure plan. Re-UAT readiness documented in 17-04-SUMMARY §Re-UAT Readiness. The new Download Report button + route + Gate + tests collectively deliver the previously-missing UX.
- **Test 2** (runbook reproducibility) — marked `pass` in 17-HUMAN-UAT.md. Local idempotent-section walk-through succeeded; full staging reproducibility is explicitly deferred to CDRRMO production cutover by runbook Audience design (not a verification gap).

### Gaps Summary

**No unresolved gaps.** All 5 ROADMAP success criteria are either VERIFIED (SC2, SC3, SC4, SC5) or PASSED via user-approved baseline-aware override (SC1). All 7 plan must-haves from 17-04-PLAN.md are VERIFIED. All 3 Phase 17 requirement IDs (FRAMEWORK-01/02/03) are SATISFIED. Zero regressions introduced by 17-04 — Wave 4 regression samples (46/51/46) stayed within Wave 2/3 precedent envelope with zero new root-cause families.

### Phase 17 Commit History (final — post-17-04)

| # | Hash | Wave | Type | Subject |
|---|------|------|------|---------|
| 1 | `ca937b4` | 1 | test | add IncidentCreated + IncidentTriaged snapshot tests on L12 |
| 2 | `9740fa9` | 1 | test | add UnitAssigned, UnitStatusChanged, ChecklistUpdated, ResourceRequested snapshots |
| 3 | `71468a5` | 1 | docs | complete broadcast event snapshot baseline plan |
| 4 | `7184fee` | 1.5 | docs | capture L12 baseline failure count (50 pre-existing; SC1 reinterpreted) |
| 5 | `0419060` | 2 | feat | add L13 serializable_classes key + Fortify feature lockdown comment |
| 6 | `1a13aff` | 2 | feat | upgrade to Laravel 13 + aligned packages + CSRF middleware rename |
| 7 | `1866ddf` | 2 | docs | complete Wave 2 rescoped plan |
| 8 | `4bb36be` | 3 | docs | add Laravel 13 drain-and-deploy runbook (FRAMEWORK-03) |
| 9 | `021c5ee` | 3 | docs | complete Wave 3 narrowed plan (Wayfinder regen + runbook + final gate) |
| 10 | `330e289` | UAT | test | persist human verification items as UAT (SC4 + FRAMEWORK-03 runbook reproducibility) |
| 11 | `4ec4af0` | UAT | test | complete UAT - 1 passed, 1 issue (PDF report not generated on resolve) |
| 12 | `f33a5d9` | UAT | test | annotate UAT with diagnosis (pre-existing v1.0 gap, user-approved gap-closure in Phase 17) |
| 13 | `ba62561` | 4 | docs | plan gap-closure 17-04 for incident report PDF download |
| 14 | `25ec02a` | 4 | feat | add incident report PDF download route + Gate + tests |
| 15 | `a07f1f2` | 4 | feat | render Download Report button on incident detail view |
| 16 | `06c4753` | 4 | docs | close gap-closure plan 17-04 with SUMMARY |
| 17 | `9dde160` | 4 | docs | add code review report for 17-04 gap closure |

### Package Versions at Phase 17 Close (unchanged by 17-04)

| Package | Installed | Constraint |
|---------|-----------|-----------|
| php (floor) | 8.4.19 | ^8.3 |
| laravel/framework | v13.5.0 | ^13.0 |
| laravel/tinker | v3.0.2 | ^3.0 |
| inertiajs/inertia-laravel | v2.0.24 | ^2.0.24 (v2 pin, NOT v3) |
| laravel/horizon | v5.45.6 | ^5.45.6 |
| laravel/reverb | v1.10.0 | ^1.10 |
| laravel/fortify | v1.36.2 | ^1.36 |
| laravel/wayfinder | v0.1.16 | ^0.1.14 |
| laravel/boost | v2.4.4 | ^2.4 |
| clickbar/laravel-magellan | 2.1.0 | ^2.1 |
| pestphp/pest | v4.6.3 | ^4.6 |

---

_Verified: 2026-04-21T15:30:00Z_
_Verifier: Claude (gsd-verifier)_
_Re-verification pass: 2 of 2 (post-17-04 gap closure)_
