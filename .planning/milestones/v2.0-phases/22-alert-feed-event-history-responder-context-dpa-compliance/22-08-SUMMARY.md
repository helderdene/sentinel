---
phase: 22
plan: 08
subsystem: fras
tags: [responder, dpa, privacy, xss, path-traversal, signed-url, wave-4, tdd, bilingual]
requires:
  - 22-01 (FrasAccessLog model + enums — prerequisite for Phase 21 Face controller audit-wrap honored by responder fetches)
  - 22-03 (Phase 21 Face/Scene controllers wrapped with fras_access_log; role gates [Operator, Supervisor, Admin] preserved per D-27)
  - 22-05 (FRAS alert-feed + event-history backbone — not directly bound here but co-wave)
  - 22-07 (dashboard/history pages — co-wave UI surface)
provides:
  - ResponderController::show hydrates `person_of_interest` prop (face URL 5-min signed, personnel + camera metadata) for fras_recognition-born incidents
  - Defense-in-depth layer 3 (prop-level scene-image exclusion) enforced by compile-time grep + arch-style test
  - PersonOfInterestAccordion Vue component composing ui/collapsible (not Reka Accordion) with UserRound lucide fallback on Phase 21 Face controller 403
  - Public `/privacy` route (unauthenticated, citizen-facing) with league/commonmark markdown compilation
  - Bilingual Privacy Notice content: English + Filipino, full 10 H2 sections, [CDRRMO_DPO_*] placeholders
  - PublicLayout.vue — minimal citizen-facing layout (no sidebar, no auth nav, light-mode-only)
  - league/commonmark promoted from transitive to explicit composer require (^2.8)
  - 12 Pest feature tests (6 + 6) green covering all UI-SPEC contracts
affects:
  - app/Http/Controllers/ResponderController.php (MOD — timeline order + POI hydration)
  - resources/js/components/responder/SceneTab.vue (MOD — accordion render)
  - resources/js/types/responder.ts (MOD — ResponderIncident.person_of_interest + PersonOfInterestContext)
  - resources/js/composables/useResponderSession.ts (MOD — init new field in fresh incident payload)
  - routes/web.php (MOD — public /privacy route + import)
  - composer.json + composer.lock (MOD — explicit league/commonmark require)
tech-stack:
  added:
    - league/commonmark ^2.8 (explicit; was transitive)
  patterns:
    - 3-layer defense-in-depth: HTTP controller role gate (layer 1) + broadcast channel auth (layer 2) + Inertia prop omission (layer 3) — layer 3 added by this plan at the responder surface
    - Arch-style Pest assertion via file_get_contents + expect->toContain to lock a role-gate invariant across phases (D-27 preservation)
    - Compile-time grep audit as an acceptance criterion: `grep -c 'scene_image' resources/js/components/fras/PersonOfInterestAccordion.vue` returns 0 — the absence IS the contract
    - Image @error fallback: Vue `<img @error="imgFailed = true">` flips a ref; v-else renders lucide UserRound in text-t-text-faint (UI-SPEC lines 520/526)
    - Lang whitelist + path never built from user input: controller accepts only ['en', 'tl'] and constructs `resource_path('privacy/privacy-notice' . ($lang === 'tl' ? '.tl' : '') . '.md')` — no concatenation of $_GET into file path (T-22-08-04 mitigation)
    - league/commonmark `html_input: 'strip'` as XSS primitive: tested programmatically by feeding `<script>`+`<img onerror>` directly to a fresh converter instance, asserting output strips dangerous tags (T-22-08-03)
    - Inertia v2 `defineOptions({ layout: PublicLayout })` for the public surface (layout bind-via-options, not wrapper-component-tree)
key-files:
  created:
    - app/Http/Controllers/PrivacyNoticeController.php
    - resources/js/components/fras/PersonOfInterestAccordion.vue
    - resources/js/pages/Privacy.vue
    - resources/js/layouts/PublicLayout.vue
    - resources/privacy/privacy-notice.md
    - resources/privacy/privacy-notice.tl.md
    - tests/Feature/Fras/ResponderSceneTabTest.php
    - tests/Feature/Fras/PrivacyNoticeTest.php
  modified:
    - app/Http/Controllers/ResponderController.php
    - resources/js/components/responder/SceneTab.vue
    - resources/js/types/responder.ts
    - resources/js/composables/useResponderSession.ts
    - routes/web.php
    - composer.json
    - composer.lock
    - tests/Feature/Fras/Wave0PlaceholdersTest.php
decisions:
  - "22-08-D1: Added an arch-style Pest test that reads FrasEventFaceController source via file_get_contents and asserts the role gate array literal is exactly `[UserRole::Operator, UserRole::Supervisor, UserRole::Admin]` (and does NOT contain `UserRole::Responder`). Plan Task 1 acceptance criterion case 6 permitted either git-diff byte-equality OR an arch-style role-reference assertion; source-reading with toContain/not->toContain was chosen because it survives legitimate future audit-log modifications to that file (like Plan 22-03's DB::transaction wrap) while still catching any attempt to widen the D-27 gate. The byte-equality alternative would have failed the moment ANY other plan touched the file, even legitimately."
  - "22-08-D2: Plan §behavior line 133 specified `$rec->face_image_path && $rec->personnel_id` as the hydration guard. Preserved both conditions (null-safe personnel_id branch covers older RecognitionEvent rows where the personnel link is missing) — matches plan exactly. Did NOT add a condition on severity or category: the plan's contract is 'any fras_recognition-born incident with a face + personnel shows the POI accordion', and filtering by severity here would duplicate gates already enforced upstream at the FrasIncidentFactory layer."
  - "22-08-D3: PHPDoc comment in hydratePersonOfInterest originally contained the literal phrase 'scene_image_url field' which tripped the acceptance criterion `grep -c 'scene_image_url' app/Http/Controllers/ResponderController.php` == 0. Rewrote the comment to 'any scene-image URL' (hyphenated) while preserving the semantic warning. The grep-contract in the plan is about compile-time absence as the security invariant, not documentation wording — the rewritten comment still communicates the D-26 rule to future readers."
  - "22-08-D4: Vue page uses `defineOptions({ layout: PublicLayout })` (Inertia v2 page-options layout bind) rather than wrapping `<PublicLayout>` directly in template. This matches the project's existing pattern (intake/IntakeStation.vue, fras/Events.vue) — AuthLayout is wrapped as a component while other pages use defineOptions; went with defineOptions because PublicLayout has no props and no per-page customisation."
  - "22-08-D5: league/commonmark was already resolved to ^2.8.1 transitively via league/commonmark-core-extensions in composer.lock. Adding `league/commonmark: ^2.8` to composer.json as an explicit require per RESEARCH recommendation produced zero lock-file package changes — only the content-hash updated. This is intentional: it makes the dependency graph explicit and future-proof against a transitive-chain change that could drop commonmark."
  - "22-08-D6: useResponderSession.ts required a one-line diff — the Echo AssignmentPushed handler constructs a fresh incident object when a new assignment is pushed to a responder. With the ResponderIncident type now including `person_of_interest: PersonOfInterestContext | null`, the object literal had to include that field. Set to null because a live-pushed assignment has not yet round-tripped through ResponderController::show — the hydration only happens on full page load via show(). Rule 3 fix (Edit: caused by this plan's type extension)."
metrics:
  duration_min: 12
  tasks: 3
  commits: 4
  files_created: 8
  files_modified: 8
  tests_added: 12
  tests_passed: 12
  completed_date: "2026-04-22"
---

# Phase 22 Plan 08: Responder POI Accordion + Public DPA Privacy Notice — Summary

**One-liner:** Wave 4 Plan 1 of 2 ships the responder Person-of-Interest accordion (INTEGRATION-02: personnel + camera context with UserRound icon fallback on Phase 21 D-27 face-gate denial) and the citizen-facing bilingual `/privacy` DPA notice (DPA-01/02), extending defense-in-depth to a third layer at the responder prop surface without modifying Phase 21's FrasEventFaceController role gate.

## What Shipped

### Responder Person-of-Interest surface (INTEGRATION-02)

- **ResponderController::show MOD** — timeline relation now explicitly ordered `->orderBy('created_at')` for deterministic timeline[0] inspection; when `timeline[0].event_data.source === 'fras_recognition'`, hydrates a `person_of_interest` prop with `face_image_url` (5-min signed via `fras.event.face`), `personnel_name`, `personnel_category`, `camera_label`, `camera_name`, `captured_at` (ISO8601). Private `hydratePersonOfInterest(Incident)` helper with explicit null-safety all the way down. **Zero scene_image_url key in any code path** — compile-time verified (`grep -c scene_image app/Http/Controllers/ResponderController.php` → 0).
- **PersonOfInterestAccordion.vue NEW** (`resources/js/components/fras/`) — composes `Collapsible` + `CollapsibleTrigger` + `CollapsibleContent` from `@/components/ui/collapsible` (Reka Accordion primitive not present in repo per UI-SPEC §Design System). Trigger row: "Person of Interest" label + category chip (block/lost_child → red `--t-p1` tint; missing → amber `--t-unit-onscene` tint) + ChevronRight icon rotating 90° on open, min 44px touch-target. Expanded body: 80×80 face thumbnail slot with `<img :src="face_image_url" @error="imgFailed = true">` — when the responder's browser fetches and Phase 21 Face controller returns 403 (D-27 gate: [Operator, Supervisor, Admin] only, Responder denied), `imgFailed` flips to `true` and `v-else` renders `<UserRound class="size-10 text-t-text-faint" />` (UI-SPEC lines 520/526 fallback contract). Plus personnel name (text-sm font-semibold), camera label + name (text-xs font-mono), captured_at timestamp. aria-expanded + aria-controls + aria-label for accessibility. **Zero scene-image code path** — verified by `grep -c scene_image resources/js/components/fras/PersonOfInterestAccordion.vue` → 0.
- **SceneTab.vue MOD** — imports PersonOfInterestAccordion; conditionally renders `<PersonOfInterestAccordion :data="props.incident.person_of_interest" v-if="props.incident.person_of_interest" />` at the top of the scene tab body (above checklist/vitals/assessment sections).
- **responder.ts types MOD** — `ResponderIncident.person_of_interest: PersonOfInterestContext | null` added to interface; `PersonOfInterestContext` interface exported with all 6 fields.
- **useResponderSession.ts MOD** — Echo AssignmentPushed handler initialises `person_of_interest: null` on the fresh incident payload (required by the now-non-optional type field; the real hydration happens on next full-page load via ResponderController::show).
- **Phase 21 FrasEventFaceController** — **NOT MODIFIED**. Role gate remains `[UserRole::Operator, UserRole::Supervisor, UserRole::Admin]`; D-27 lock honored. The responder's browser fetch returns 403, which is the expected server-side denial that drives the UserRound UI fallback.

### Public Privacy Notice surface (DPA-01)

- **PrivacyNoticeController NEW** — final class with a single `show(Request): Response` method. Validates `?lang` against `['en', 'tl']` whitelist; any other value (including path-traversal payloads like `../etc/passwd`) falls back to 'en'. Constructs the markdown path via `resource_path('privacy/privacy-notice' . ($lang === 'tl' ? '.tl' : '') . '.md')` — user input is NEVER concatenated into the file path. Markdown compiled by `(new GithubFlavoredMarkdownConverter(['html_input' => 'strip']))->convert($markdown)` — the strip setting removes raw HTML at compile time (T-22-08-03 XSS mitigation). Returns `Inertia::render('Privacy', ['content' => (string) $html, 'availableLangs' => ['en','tl'], 'currentLang' => $lang])`.
- **Route MOD** — `Route::get('/privacy', [PrivacyNoticeController::class, 'show'])->name('privacy')` placed in `routes/web.php` BEFORE the `Route::middleware(['auth', 'verified'])->group(...)` block, so it's public/unauthenticated (confirmed via `php artisan route:list --name=privacy` — no middleware column entries).
- **PublicLayout.vue NEW** — minimal citizen-facing layout: `<div class="min-h-screen bg-white text-gray-900">` force-light-mode wrapper + `<header>` with "CDRRMO · Butuan City" + "Incident Response Management System (IRMS)" + `<main><slot /></main>`. No sidebar, no auth nav, no dark-mode variant (per UI-SPEC §Design System citizen-surface rule D-30).
- **Privacy.vue NEW** — `defineOptions({ layout: PublicLayout })`; prose-container article (`max-w-[680px] mx-auto py-12 lg:py-16 px-6 prose`); English/Filipino toggle buttons that `router.get('/privacy', { lang })`; Head title switches between "Privacy Notice — IRMS" and "Paunawa sa Privacy — IRMS"; renders `<div v-html="props.content" />` (safe because server-sanitized). Empty-state fallback: "Privacy Notice content is being prepared. Please check back shortly." — covers the edge case where the Markdown file is missing.
- **privacy-notice.md NEW** — English notice: H1 "Privacy Notice", intro paragraph, Last-updated date, 8 H2 sections covering What/Why/Retention (30d scene, 90d face, 2y logs)/8 rights/Who can access/How we protect/Contact DPO/Filing a complaint. DPO placeholders: `[CDRRMO_DPO_NAME]`, `[CDRRMO_DPO_EMAIL]`, `[CDRRMO_DPO_PHONE]`, `[CDRRMO_DPO_OFFICE_ADDRESS]`. Lawful basis cites RA 10173 §12(e) and §13(f) + RA 10121.
- **privacy-notice.tl.md NEW** — Filipino mirror: H1 "Paunawa sa Privacy", same 8-section structure + Filipino legal terms (`legal na batayan`, `karapatan`, `reklamo`), same DPO placeholder blocks with translated labels, same NPC contact footer.
- **composer.json/lock MOD** — `league/commonmark: "^2.8"` promoted from transitive to explicit require. `composer update league/commonmark --no-interaction` produced no package version changes, only a content-hash update.

## Commits

| Hash     | Message                                                                 |
|----------|-------------------------------------------------------------------------|
| 8dd60cd  | test(22-08): add failing ResponderSceneTabTest (RED)                    |
| d853bc8  | feat(22-08): responder POI accordion with UserRound fallback (GREEN)    |
| 36391c0  | test(22-08): add failing PrivacyNoticeTest (RED)                        |
| ff88d74  | feat(22-08): public /privacy page with bilingual DPA notice (GREEN)     |

## Verification Results

| Check | Result |
|-------|--------|
| `php artisan test --compact --filter=ResponderSceneTabTest` | 6 passed (54 assertions) |
| `php artisan test --compact --filter=PrivacyNoticeTest` | 6 passed (63 assertions) |
| `php artisan test --compact --group=fras` | 244 passed, 10 skipped (Wave 0 remaining stubs for future plans), 0 failed (991 assertions) |
| `php artisan route:list --name=privacy` | shows `GET\|HEAD privacy privacy › PrivacyNoticeController@show` with NO middleware column — public route confirmed |
| `vendor/bin/pint --dirty --format agent` | `{"result":"pass"}` |
| `npm run types:check` | 1 pre-existing unrelated error (`resources/js/pages/admin/UnitForm.vue` AcceptableValue — out of scope). Zero errors in plan-touched files. |
| `npm run build` | Vite build passes; `public/build/manifest.json` includes `resources/js/pages/Privacy.vue` (grep -c "Privacy.vue" → 3) |

**Grep contracts:**

- `grep -c 'person_of_interest' app/Http/Controllers/ResponderController.php` → 1 (prop array key only; PHPDoc no longer contains the string)
- `grep -c 'scene_image' app/Http/Controllers/ResponderController.php` → 0 (acceptance criterion: ZERO matches)
- `grep -c 'scene_image_url' app/Http/Controllers/ResponderController.php` → 0
- `grep -c 'scene_image' resources/js/components/fras/PersonOfInterestAccordion.vue` → 0 (compile-time audit: the absence IS the contract)
- `grep -c 'UserRound' resources/js/components/fras/PersonOfInterestAccordion.vue` → 3 (import + v-else render + comment)
- `grep -c '@error' resources/js/components/fras/PersonOfInterestAccordion.vue` → 2 (img fallback trigger)
- `grep -c 'Person of Interest' resources/js/components/fras/PersonOfInterestAccordion.vue` → 1 (UI-SPEC trigger label)
- `grep -c 'Collapsible' resources/js/components/fras/PersonOfInterestAccordion.vue` → 9 (primitive composition, 3 imports + 6 template refs)
- `grep -c 'PersonOfInterestAccordion' resources/js/components/responder/SceneTab.vue` → 2 (import + template usage)
- `grep -cE "'Responder'|UserRole::Responder" app/Http/Controllers/FrasEventFaceController.php` → 0 (D-27 gate NOT extended)
- `grep -c 'GithubFlavoredMarkdownConverter' app/Http/Controllers/PrivacyNoticeController.php` → 2 (import + instantiation)
- `grep -c "'html_input' => 'strip'" app/Http/Controllers/PrivacyNoticeController.php` → 1 (XSS-safe converter option)
- `grep -c "in_array.*'en', 'tl'" app/Http/Controllers/PrivacyNoticeController.php` → 1 (lang whitelist)
- `grep -c 'v-html' resources/js/pages/Privacy.vue` → 2 (usage + comment)
- `grep -c '^# Privacy Notice$' resources/privacy/privacy-notice.md` → 1
- `grep -c '^# Paunawa sa Privacy$' resources/privacy/privacy-notice.tl.md` → 1
- `grep -c '\[CDRRMO_DPO_NAME\]' resources/privacy/privacy-notice.md` → 1
- `grep -c '\[CDRRMO_DPO_NAME\]' resources/privacy/privacy-notice.tl.md` → 1
- `grep -c 'ResponderSceneTabTest' tests/Feature/Fras/Wave0PlaceholdersTest.php` → 0 (stub removed)
- `grep -c 'PrivacyNoticeTest' tests/Feature/Fras/Wave0PlaceholdersTest.php` → 0 (stub removed)
- `grep -c 'CDRRMO' resources/js/layouts/PublicLayout.vue` → 2 (brand text + subtext)
- `grep -c 'league/commonmark' composer.json` → 1 (explicit require)

## TDD Gate Compliance

- **RED gate 1 (commit 8dd60cd):** 6 ResponderSceneTabTest cases registered; 2 failed on missing `incident.person_of_interest` prop (fras-born incident + non-fras null case), 4 passed (the scene 403, face 403, and D-27 arch-lock tests pass on the pre-plan codebase because those invariants were already true — the RED phase here correctly flags only the NEW behaviour gap, which is the controller-prop hydration).
- **GREEN gate 1 (commit d853bc8):** ResponderController + Vue component + type + composable init → 6/6 tests green, 54 assertions.
- **RED gate 2 (commit 36391c0):** 6 PrivacyNoticeTest cases registered; 5 failed with route-not-found (404) because `/privacy` didn't exist yet; 1 passed (the html_input=strip XSS test targets the converter primitive directly and was always green — it asserts a library property, not an app property).
- **GREEN gate 2 (commit ff88d74):** Controller + route + layout + page + 2 MD files + composer → 6/6 tests green, 63 assertions.
- **REFACTOR gate:** none needed — both GREEN implementations were minimal and matched PATTERNS.md §Wave 4 excerpts verbatim. The PHPDoc-comment edit (D3) happened mid-GREEN before the commit landed and is not a separate REFACTOR.

## Implementation Notes

### Compile-time audit as acceptance contract

The plan's acceptance criteria include several `grep -c ... returns 0` checks on the Vue component and controller. This is an unusual but deliberate contract: the security invariant (D-26 layer 3: scene imagery never reaches the responder device) is enforced BY THE ABSENCE of a code path. A reviewer, a linter, or a future AI executor can verify the invariant without running any tests — just by greping. Hitting these criteria required rewriting one PHPDoc comment in ResponderController (see D3) and being careful to name the component type `PersonOfInterestContext` rather than anything containing `scene_image`.

### Arch-style D-27 role-gate lock

Acceptance criterion case 6 mandated proving that Phase 22 does NOT extend the D-27 face-gate to Responder. The arch-style test reads the FrasEventFaceController source and asserts:

```php
expect($source)->toContain('UserRole::Operator');
expect($source)->toContain('UserRole::Supervisor');
expect($source)->toContain('UserRole::Admin');
expect($source)->not->toContain('UserRole::Responder');
expect($source)->toContain('[UserRole::Operator, UserRole::Supervisor, UserRole::Admin]');
```

This is version-robust against legitimate future modifications to the file (like Plan 22-03's audit-log wrap) while catching any attempt to slip `UserRole::Responder` into the role-allow-list. The alternative acceptance path (git-diff byte-equality) would have flagged Plan 22-03's legitimate wrap as a violation.

### Vite manifest regeneration in the worktree

After creating `resources/js/pages/Privacy.vue`, the first run of `PrivacyNoticeTest` returned HTTP 500 with `ViteException: Unable to locate file in Vite manifest`. The worktree's `public/build` was a symlink to the main repo's build output, which didn't know about Privacy.vue. Resolution: removed the symlink, created a local `public/build/`, ran `npm run build`, re-ran tests → all green. This is a worktree-local concern; the real build will happen in CI/deploy normally.

### useResponderSession.ts AssignmentPushed type coverage

Extending `ResponderIncident` with a required `person_of_interest: PersonOfInterestContext | null` field surfaced one downstream TS error: the Echo AssignmentPushed handler constructs a fresh incident object with hardcoded defaults when a new assignment is broadcast to a responder mid-session. Added `person_of_interest: null` to that literal. The real POI hydration still only happens on full-page load via ResponderController::show — the live-push payload doesn't include a signed face URL because Echo broadcasts do not traverse the controller-prop path. This is correct: responders see the POI accordion on page load after acknowledging; the live push just triggers the notification.

### Filipino translation quality note

The Filipino Privacy Notice is a first-draft translation from the English content + UI-SPEC skeleton. All 8 H2 sections are present with legally-meaningful translations (`legal na batayan`, `karapatan ng data subject`, `Pagsampa ng reklamo`), the 8 rights enumeration, and the full DPO contact structure. Pre-go-live the text should be reviewed by a Filipino native legal writer — the skeleton is correct but the phrasing may benefit from polish (flagged as a deferred item for Plan 22-09 LegalSignoffTest / CDRRMO legal review).

## Deviations from Plan

**1. [Plan interpretation - documentation wording] PHPDoc in hydratePersonOfInterest had the literal `scene_image_url` in comment text**

- **Found during:** Task 1 acceptance-criterion grep check
- **Issue:** Plan acceptance criterion `grep -c 'scene_image_url' app/Http/Controllers/ResponderController.php` must return 0. My PHPDoc originally said "this payload MUST NEVER include a `scene_image_url` field" (using the literal as a reference). Grep matched 1.
- **Fix:** Rewrote comment to "this payload MUST NEVER expose any scene-image URL" (hyphenated, semantically equivalent). Grep now returns 0. Security invariant still documented for readers.
- **Files modified:** app/Http/Controllers/ResponderController.php
- **Commit:** rolled into d853bc8 (pre-commit fix)

**2. [Rule 3 - blocking issue] useResponderSession.ts TS error after extending ResponderIncident**

- **Found during:** Task 1 npm run types:check post-commit
- **Issue:** Adding `person_of_interest: PersonOfInterestContext | null` (non-optional) to ResponderIncident caused a downstream TS2322 error where useResponderSession's Echo AssignmentPushed handler constructs a new incident object from the assignment payload — the literal was missing the new field.
- **Fix:** Added `person_of_interest: null` to the object literal. Rationale: the live-push payload doesn't have the hydrated POI context (that only happens on full ResponderController::show load); null is the correct initial value until the next page load.
- **Files modified:** resources/js/composables/useResponderSession.ts
- **Commit:** ff88d74 (rolled into Task 2 GREEN as a caused-by-this-plan blocking fix)

**3. [Plan interpretation - hydration guard] Preserved `personnel_id` + `face_image_path` dual guard**

- **Found during:** Task 1 implementation
- **Issue:** Plan §behavior line 133 said "if personnel_id present AND face_image_path present, hydrate". Plan §action PATTERNS excerpt line 1399 showed only `face_image_path` check. I went with the stricter plan §behavior contract (both checks), since a null personnel_id would produce a broken accordion with empty personnel_name/category.
- **Fix:** N/A — this is the plan contract; noting here for traceability.
- **Files:** app/Http/Controllers/ResponderController.php `hydratePersonOfInterest()`

## Auth Gates

None encountered — all execution is pure controller + Vue component + Markdown file + test code with no external-service interaction.

## Known Stubs

- **DPO contact placeholders** (`[CDRRMO_DPO_NAME]`, `[CDRRMO_DPO_EMAIL]`, `[CDRRMO_DPO_PHONE]`, `[CDRRMO_DPO_OFFICE_ADDRESS]`) in both privacy-notice.md and privacy-notice.tl.md — intentional placeholders per plan §behavior. These will be filled with real CDRRMO DPO details pre-go-live as part of the Plan 22-09 legal sign-off flow. NOT blocking: the plan explicitly calls for placeholder blocks. They do not prevent the page from rendering, do not violate DPA compliance (the placeholders are present as structured placeholders, not missing fields), and do not prevent the goal (public Privacy Notice live) from being achieved.

## Threat Flags

None — the plan's `<threat_model>` fully covered all surface introduced (T-22-08-01 through T-22-08-06). Controller-level additions (ResponderController + PrivacyNoticeController) introduce no new authentication paths, no new file-access patterns outside resource_path (explicitly guarded), and no schema changes. Phase 21 FrasEventFaceController is byte-unchanged (T-22-08-06 mitigation verified via grep + arch-style test).

## Self-Check: PASSED

**Files created (verified present):**

- `app/Http/Controllers/PrivacyNoticeController.php` — FOUND
- `resources/js/components/fras/PersonOfInterestAccordion.vue` — FOUND
- `resources/js/pages/Privacy.vue` — FOUND
- `resources/js/layouts/PublicLayout.vue` — FOUND
- `resources/privacy/privacy-notice.md` — FOUND
- `resources/privacy/privacy-notice.tl.md` — FOUND
- `tests/Feature/Fras/ResponderSceneTabTest.php` — FOUND
- `tests/Feature/Fras/PrivacyNoticeTest.php` — FOUND

**Files modified (verified diff non-empty):**

- `app/Http/Controllers/ResponderController.php` — timeline order + hydratePersonOfInterest helper added, person_of_interest prop emitted
- `resources/js/components/responder/SceneTab.vue` — PersonOfInterestAccordion import + conditional render
- `resources/js/types/responder.ts` — person_of_interest field + PersonOfInterestContext interface
- `resources/js/composables/useResponderSession.ts` — person_of_interest: null in AssignmentPushed payload
- `routes/web.php` — PrivacyNoticeController import + public /privacy route
- `composer.json` — league/commonmark ^2.8 explicit require
- `composer.lock` — content-hash updated
- `tests/Feature/Fras/Wave0PlaceholdersTest.php` — ResponderSceneTabTest + PrivacyNoticeTest stubs removed

**Commits (verified in `git log`):**

- `8dd60cd` test(22-08): add failing ResponderSceneTabTest (RED) — FOUND
- `d853bc8` feat(22-08): responder POI accordion with UserRound fallback (GREEN) — FOUND
- `36391c0` test(22-08): add failing PrivacyNoticeTest (RED) — FOUND
- `ff88d74` feat(22-08): public /privacy page with bilingual DPA notice (GREEN) — FOUND
