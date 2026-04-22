---
phase: 22
plan: 09
subsystem: fras
tags: [dpa, compliance, dompdf, legal-signoff, artisan-command, wave-4]
requires: [22-01, 22-08]
provides:
  - docs/dpa/PIA-template.md
  - docs/dpa/signage-template.md
  - docs/dpa/signage-template.tl.md
  - docs/dpa/operator-training.md
  - resources/views/dpa/export.blade.php
  - "artisan fras:dpa:export"
  - "artisan fras:legal-signoff"
  - fras_legal_signoffs milestone-close gate mechanism
affects: []
tech_stack:
  added: []
  patterns:
    - "GithubFlavoredMarkdownConverter(html_input:strip) → Blade view → dompdf render pipeline"
    - "Hardcoded base_path() VALIDATION.md target with config() override for hermetic tests"
    - "storage/app/dpa-exports/{YYYY-MM-DD}/ server-local output directory (not symlinked to public/)"
    - "Append-only FrasLegalSignoff row pattern for milestone-close audit gate"
    - "Config-driven test sandbox: config('fras.signoff.validation_path') redirects prod append target"
key_files:
  created:
    - docs/dpa/PIA-template.md
    - docs/dpa/signage-template.md
    - docs/dpa/signage-template.tl.md
    - docs/dpa/operator-training.md
    - resources/views/dpa/export.blade.php
    - app/Console/Commands/FrasDpaExport.php
    - app/Console/Commands/FrasLegalSignoff.php
    - tests/Feature/Fras/DpaDocsExistTest.php
    - tests/Feature/Fras/FrasDpaExportTest.php
    - tests/Feature/Fras/LegalSignoffTest.php
  modified:
    - tests/Feature/Fras/Wave0PlaceholdersTest.php
decisions:
  - "VALIDATION.md append target is config-overridable for test hermetics: config('fras.signoff.validation_path', base_path('.planning/phases/22-…/22-VALIDATION.md')) — plan spec's 'hardcoded base_path' is preserved as the fallback; tests redirect to a sandbox under storage/framework/testing/"
  - "Task 3 (human-verify) is auto-approved under auto-mode. Full Nyquist per-task map fill-in, legal signoff dry-run, and final PDF visual review are governance actions delegated to the real CDRRMO milestone close — the mechanism ships here."
  - "FrasLegalSignoff command returns self::INVALID (code 2) on missing args rather than self::FAILURE — this is the idiomatic Symfony Console code for 'bad invocation' and passes tests asserting exit != 0 while remaining semantically correct"
  - "PDF title is a human-readable mapping ('Privacy Impact Assessment', 'FRAS CCTV Zone Notice', 'Paunawa sa FRAS CCTV Zone', 'FRAS Operator Training') rather than ucfirst($doc) — cleaner output for legal/DPO review"
  - "Tagalog signage title switches with --lang=tl ('Paunawa sa FRAS CCTV Zone') to match the Filipino translation of the Markdown body"
metrics:
  duration_minutes: 22
  tasks_completed: 2
  files_changed: 11
  tests_added: 15
  commits: 3
  completed_at: 2026-04-22
---

# Phase 22 Plan 09: DPA Docs + PDF Export + Legal Sign-off CLI Summary

**One-liner:** Ship the 4-doc DPA package (PIA, EN+TL signage, operator training), the dompdf-backed `fras:dpa:export` CLI, the milestone-close gate `fras:legal-signoff` CLI, and 15 Pest tests — Phase 22's final delivery for DPA-06 (docs) and DPA-07 (legal sign-off mechanism).

## Objective

Deliver Wave 4 / Plan 2 of Phase 22: Markdown-first DPA documentation (PR-reviewable, dompdf-renderable), a language-aware PDF export CLI for CDRRMO hand-off, and an append-only legal sign-off CLI that writes a `fras_legal_signoffs` row plus appends to `22-VALIDATION.md` — the latter of which is the milestone-close gate. After this plan lands, CDRRMO legal can sign off at any time via `php artisan fras:legal-signoff --signed-by=... --contact=...` and the row blocks milestone close until present.

## What Shipped

### Task 1 — DPA docs + Blade template + DpaDocsExistTest

**4 Markdown docs** (new `docs/dpa/` directory):

1. **`PIA-template.md`** — 10 H2 sections per CONTEXT D-33: Scope / Biometric Data Types / Lawful Basis (RA 10173 § 12(e), 13(b), 13(f) + RA 10121) / Retention (with DPA legal-wall exception) / Data Flows (ASCII diagram) / Risks (8 enumerated) / Mitigations (risk→control table) / DSR Handling / Incident Response (72-hr NPC notification) / DPO Sign-off block. Placeholders tagged `[CDRRMO_SPECIFIC_FILLIN]`.
2. **`signage-template.md`** — CCTV zone notice with 4 merge fields (`{CAMERA_LOCATION}`, `{CONTACT_DPO}`, `{CONTACT_OFFICE}`, `{RETENTION_WINDOW}`). DPA rights bullet list + DPO contact block + full-privacy-notice URL.
3. **`signage-template.tl.md`** — Filipino sibling with identical merge-field tokens preserved; idiomatic Tagalog translation of all sections including "Paunawa" heading.
4. **`operator-training.md`** — Role matrix (7-column table showing which of admin/supervisor/operator/dispatcher/responder can access each FRAS surface); ACK vs Dismiss semantics with 4-enum dismiss-reason whitelist; When-to-Promote rules (8–500 char reason, P1–P4 priority); Scene Image Access Restrictions (explains why responders never get scene_image_url); Signed URL Expiry (5-min, fresh audit row each hydration); Retention Purge Cadence (daily 02:00 Asia/Manila with active-incident legal-wall exception).

**1 shared Blade template** (`resources/views/dpa/export.blade.php`): DejaVu Sans 11pt body, 680px max-width, H1 with border-bottom, H2/H3 hierarchy, table + blockquote + code styling for dompdf. Unescaped `{!! $content !!}` is safe because only sanitized HTML from `GithubFlavoredMarkdownConverter(html_input:strip)` is passed in.

**1 file-presence test** (`DpaDocsExistTest.php` @ `pest()->group('fras')`):
- ships the PIA template markdown doc
- PIA template contains all 10 H2 sections
- ships English signage template with 4 merge fields
- ships Filipino signage template with the same 4 merge fields
- ships the operator training doc with DPA role matrix
- ships the shared dompdf Blade template
→ 6 tests, 19 assertions, 0.83s

### Task 2 — `fras:dpa:export` + `fras:legal-signoff` commands + tests

**`FrasDpaExport` command** (`app/Console/Commands/FrasDpaExport.php`):
- Signature: `fras:dpa:export {--doc=all : pia|signage|training|all} {--lang=en : en|tl}`.
- `handle(): int` — expands `doc=all` to `[pia, signage, training]`, creates `storage/app/dpa-exports/{YYYY-MM-DD}/` if missing, then for each doc: resolves the MD source via `match`, compiles Markdown via `GithubFlavoredMarkdownConverter(['html_input' => 'strip'])`, renders the shared Blade template, saves PDF via `Barryvdh\DomPDF\Facade\Pdf::loadView('dpa.export', [...])->save($outPath)`, prints the absolute `$outPath` to stdout for operator hand-off.
- `signage` is the only doc that switches path on `--lang=tl`; `pia` and `training` are English-only (no Tagalog body in plan scope).

**`FrasLegalSignoff` command** (`app/Console/Commands/FrasLegalSignoff.php`):
- Signature: `fras:legal-signoff {--signed-by=} {--contact=} {--notes=}`.
- `handle(): int` — validates required args (returns `self::INVALID` if missing), creates `FrasLegalSignoff` row (HasUuids + immutable_datetime on signed_at per Plan 22-01), then appends a `- [x] CDRRMO legal sign-off recorded…` line to `22-VALIDATION.md`. The VALIDATION path is resolved via `config('fras.signoff.validation_path', base_path('.planning/phases/22-…/22-VALIDATION.md'))` — production uses the hardcoded base_path() fallback per the plan spec; tests override with a sandbox path for hermetics.

**`FrasDpaExportTest`** (`pest()->group('fras')`):
- exports the PIA doc to PDF with `--doc=pia --lang=en` (verifies exit 0 + file exists + size > 0)
- exports all 3 docs with `--doc=all --lang=en` (verifies 3 PDFs exist: pia-en, signage-en, training-en)
- exports the Filipino signage with `--doc=signage --lang=tl` (verifies signage-tl.pdf)
- prints the absolute output path to stdout (verifies stdout contains the full path)
→ 4 tests, 8 assertions, 2.22s

**`LegalSignoffTest`** (`uses(RefreshDatabase::class)` + `pest()->group('fras')`):
- records a legal signoff row with the given signer and contact
- aborts non-zero when `--signed-by` is missing
- aborts non-zero when `--contact` is missing
- appends a sign-off line to VALIDATION.md (via sandbox config override)
- persists notes when provided
→ 5 tests, 13 assertions, 0.73s

**Wave 0 stub removed:** the `it('Wave 0 placeholder — LegalSignoffTest')` line was deleted from `Wave0PlaceholdersTest.php` now that the real test ships.

## Commits

| Hash | Message |
|------|---------|
| 920c47c | feat(22-09): add DPA docs package + Blade template + DpaDocsExistTest |
| 350af5d | test(22-09): add failing FrasDpaExportTest + LegalSignoffTest (RED) |
| ad87adf | feat(22-09): add fras:dpa:export + fras:legal-signoff commands (GREEN) |

## Verification

| Check | Result |
|-------|--------|
| `php artisan test --compact --filter=DpaDocsExistTest` | 6 passed (19 assertions) |
| `php artisan test --compact --filter=FrasDpaExportTest` | 4 passed (8 assertions) |
| `php artisan test --compact --filter=LegalSignoffTest` | 5 passed (13 assertions) |
| `php artisan list \| grep fras:dpa:export` | match, description matches |
| `php artisan list \| grep fras:legal-signoff` | match, description matches |
| `grep fras:dpa:export app/Console/Commands/FrasDpaExport.php` | match |
| `grep fras:legal-signoff app/Console/Commands/FrasLegalSignoff.php` | match |
| `grep FrasLegalSignoff::create` command file (via `FrasLegalSignoffModel::create`) | match (aliased import) |
| `grep dpa.export app/Console/Commands/FrasDpaExport.php` | match |
| `grep LegalSignoffTest tests/Feature/Fras/Wave0PlaceholdersTest.php` | 0 matches (stub removed) |
| `grep '^## ' docs/dpa/PIA-template.md \| wc -l` | 10 |
| `grep '{CAMERA_LOCATION}' docs/dpa/signage-template.md` | match |
| `grep '{CAMERA_LOCATION}' docs/dpa/signage-template.tl.md` | match |
| `grep 'Role Matrix' docs/dpa/operator-training.md` | match |
| `grep 'DejaVu Sans' resources/views/dpa/export.blade.php` | match |
| `vendor/bin/pint --dirty --format agent` | pass |

## TDD Gate Compliance

- **RED gate:** `350af5d` committed failing `FrasDpaExportTest` (4 tests) + `LegalSignoffTest` (5 tests) — both aborted with `The command "fras:dpa:export/fras:legal-signoff" does not exist.` confirming pre-implementation failure.
- **GREEN gate:** `ad87adf` added the two command classes; all 9 RED tests now pass; no test was modified post-RED.
- **REFACTOR gate:** not required — Pint passed on first run.

Task 1 (`DpaDocsExistTest`) shipped in a single non-TDD commit because the test is a file-presence assertion and the 4 MD docs + Blade template are planner-authored content, not behavior. The plan declares `tdd="false"` on Task 1 accordingly.

## Deviations from Plan

### Auto-fixed issues

**1. [Rule 3 - Blocking] Composer vendor/ missing in worktree**
- **Found during:** Task 1 initial test run.
- **Issue:** `php artisan` aborted with "vendor/autoload.php not found" — same pre-condition as Plans 22-01 and 22-04.
- **Fix:** `composer install --no-interaction --prefer-dist`.
- **Files modified:** `vendor/` (not tracked).
- **Commit:** n/a.

**2. [Rule 2 - Critical functionality] VALIDATION.md append path made test-overridable**
- **Found during:** Task 2 `LegalSignoffTest` write.
- **Issue:** Plan spec says "VALIDATION.md path is resolved via base_path()" which is correct for production but would cause the test suite to append to the real phase VALIDATION.md (destructive test side-effect).
- **Fix:** Changed the command to resolve the path via `config('fras.signoff.validation_path', base_path('.planning/phases/22-…/22-VALIDATION.md'))`. Production behavior is unchanged (the fallback resolves to the same base_path). Tests set `config(['fras.signoff.validation_path' => $sandbox])` in `beforeEach` to redirect the append to `storage/framework/testing/22-VALIDATION.sandbox.md`, which is cleaned up in `afterEach`. This honors T-22-09-05 (hardcoded base_path relative path, no user-input in path) because the config override is server-side-only (no HTTP surface touches it).
- **Files modified:** `app/Console/Commands/FrasLegalSignoff.php`, `tests/Feature/Fras/LegalSignoffTest.php`.
- **Commit:** Rolled into `ad87adf` (GREEN).

### Deferred / out-of-scope observations

- **Pre-existing `fras` group failures unrelated to this plan** — `php artisan test --compact --group=fras` reports 25 failures across `AdminCameraControllerTest`, `AdminPersonnelControllerTest`, `DispatchConsoleCamerasPropTest`, `FrasAlertFeedTest`, `FrasEventHistoryTest`, and `IntakeStationFrasRailTest`. These are all from prior Phase 22 waves (Wave 3 plans 22-05/22-06/22-07) or pre-existing Admin/Dispatch tests that depend on either `npm run build` (Vite manifest) or full PostgreSQL fixture seeding in the test DB. My new tests all pass; no regression introduced. This matches the pre-existing deferred pattern documented in SUMMARIES 22-01 and 22-04.
- **Task 3 human-verify auto-approved** — Full Nyquist Per-Task Verification Map fill-in, PDF visual review, and final CDRRMO legal signoff dry-run are governance activities to be performed by a human at real milestone close. The delivery mechanism ships here and is fully tested.

## Known Stubs

None. The 4 DPA docs ship as production templates with `[CDRRMO_SPECIFIC_FILLIN]` placeholders the DPO is expected to complete before go-live (these are not code stubs — they are intentional template variables documented for legal review, matching the pattern of `{CAMERA_LOCATION}` merge fields in signage).

## Auth Gates

None. Both commands are CLI-only and require Artisan access (infrastructure boundary, outside the application auth surface per T-22-09-02 mitigation).

## Threat Flags

None. All threats in `<threat_model>` are mitigated as declared:

- **T-22-09-01** (Tampering via malicious MD → dompdf RCE): `html_input:'strip'` on the CommonMark converter strips raw HTML before Blade/dompdf ever sees it. Verified in source: `grep "'html_input' => 'strip'" app/Console/Commands/FrasDpaExport.php` matches.
- **T-22-09-02** (CLI spoofed by unauthorized operator): accepted — CLI boundary is infrastructure, not application.
- **T-22-09-03** (`storage/app/dpa-exports/` public exposure): accepted — the directory is under `storage/app/` but NOT under `storage/app/public/`, so the Laravel `storage:link` symlink does not expose it to the webroot. Verified by convention.
- **T-22-09-04** (`fras_legal_signoffs` row lost): mitigated via append-only Eloquent model (immutable_datetime cast on `signed_at` per Plan 22-01) + DB-backup retention (Phase 18 baseline).
- **T-22-09-05** (VALIDATION.md path escape): mitigated — the path is resolved from `config(…)` with a hardcoded `base_path()` fallback; no user-input (from CLI args or HTTP request) influences the path. The config override is server-side-only for test hermetics.

## Self-Check: PASSED

- docs/dpa/PIA-template.md: FOUND
- docs/dpa/signage-template.md: FOUND
- docs/dpa/signage-template.tl.md: FOUND
- docs/dpa/operator-training.md: FOUND
- resources/views/dpa/export.blade.php: FOUND
- app/Console/Commands/FrasDpaExport.php: FOUND
- app/Console/Commands/FrasLegalSignoff.php: FOUND
- tests/Feature/Fras/DpaDocsExistTest.php: FOUND
- tests/Feature/Fras/FrasDpaExportTest.php: FOUND
- tests/Feature/Fras/LegalSignoffTest.php: FOUND
- Commit 920c47c: FOUND
- Commit 350af5d: FOUND
- Commit ad87adf: FOUND
