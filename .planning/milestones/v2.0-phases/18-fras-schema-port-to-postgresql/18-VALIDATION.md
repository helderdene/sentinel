---
phase: 18
slug: fras-schema-port-to-postgresql
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 18 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Source: 18-RESEARCH.md §Validation Architecture (lines 1131-1220).

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.6 (`pestphp/pest ^4.6`) |
| **Config file** | `tests/Pest.php` (binds `TestCase + RefreshDatabase` to `Feature/*` via `uses()->in('Feature')`) |
| **Quick run command** | `./vendor/bin/pest --group=fras` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~3-5 seconds (quick) / ~30-60 seconds (full) |
| **Test DB** | PostgreSQL 17 (`.env.testing: DB_CONNECTION=pgsql`; FRAMEWORK-05 satisfied on inspection) |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/pest --group=fras` (runs only FRAS-tagged tests; fast feedback)
- **After every plan wave:** Run `php artisan test --compact` (full suite; regression gate against existing ~300 v1.0 tests)
- **Before `/gsd-verify-work`:** Full suite must be green **AND** `php artisan migrate:fresh --seed` must exit 0 on clean pgsql DB
- **Max feedback latency:** 5 seconds (quick) / 60 seconds (full)

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD-01-01 | 01 | 1 | FRAMEWORK-04 | — | N/A (schema-only, no runtime surface) | integration | `php artisan migrate:fresh && php artisan test --compact --filter=SchemaTest` | ❌ W0 | ⬜ pending |
| TBD-01-02 | 01 | 1 | FRAMEWORK-04 | — | N/A | unit | `./vendor/bin/pest --group=fras --filter=geography_column_type` | ❌ W0 | ⬜ pending |
| TBD-02-01 | 02 | 2 | FRAMEWORK-06 | — | DB-layer idempotency rejects duplicate RecPush redelivery | feature | `./vendor/bin/pest --group=fras tests/Feature/Fras/RecognitionEventIdempotencyTest.php` | ❌ W0 | ⬜ pending |
| TBD-02-02 | 02 | 2 | FRAMEWORK-04 | — | Spatial query correctness on geography column | feature | `./vendor/bin/pest --group=fras tests/Feature/Fras/CameraSpatialQueryTest.php` | ❌ W0 | ⬜ pending |
| TBD-02-03 | 02 | 2 | FRAMEWORK-05 | — | N/A (verification gate — `.env.testing: DB_CONNECTION=pgsql`) | verification | `grep 'DB_CONNECTION=pgsql' .env.testing && ./vendor/bin/pest --group=fras` | ✓ (passes on inspection) | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Note: Task IDs will be finalized by gsd-planner once plans are split into waves. The above is a reference skeleton — the planner may rename to (e.g.) 18-01-01 format matching existing phase conventions.*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Fras/CameraSpatialQueryTest.php` — covers SC5 (ST_DWithin spatial feature test), **REQUIRED per D-59**
- [ ] `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` — covers SC2 + FRAMEWORK-06, **REQUIRED per D-59**
- [ ] (Optional) `tests/Feature/Fras/SchemaTest.php` — introspects `information_schema.columns` for uuid / jsonb / timestamptz(6) / varchar caps; low-cost regression insurance
- [ ] (Optional) `tests/Unit/Conventions/FrasEnumCheckParityTest.php` — asserts each PHP enum's string values match the corresponding DB CHECK IN clause
- [ ] `database/seeders/FrasPlaceholderSeeder.php` — empty class; satisfies SC4 "every new table has a seeder" wording
- [ ] Framework install: **none** — Pest 4.6, PostgreSQL 17, PostGIS, Magellan 2.1.0 all shipped by Phase 17

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| None | — | All Phase 18 behaviors have automated verification (schema introspection + feature tests + migrate:fresh) | — |

*No manual-only gates. Phase 18 is pure schema; everything testable via `information_schema` + Pest feature tests.*

---

## Nyquist Dimension Coverage

From 18-RESEARCH.md §Nyquist Dimension Coverage:

| # | Dimension | Status | Justification |
|---|-----------|--------|---------------|
| 1 | Data integrity | COVERED | FK + CHECK + UNIQUE constraints enforced at DB layer; idempotency test confirms UNIQUE violation; schema-introspection tests read `information_schema.table_constraints` |
| 2 | Persistence | COVERED | `migrate:fresh --seed` exits 0; full round-trip factory → DB → Eloquent read preserves all casts (Point, DateTime(6), enums) |
| 3 | Security | N/A | No HTTP surface, no auth, no secrets. `personnel.photo_hash` (MD5) is dedup, not auth. Security enforcement applies Phase 19+ |
| 4 | Performance | COVERED (targeted) | Index-by-inspection via `EXPLAIN` on representative queries (GIN, GIST, composite BTree). No load tests — no runtime code to load |
| 5 | Observability | N/A | No runtime code that logs/broadcasts/emits metrics. Laravel logs migration execution automatically |
| 6 | Accessibility | N/A | No UI ships in Phase 18 |
| 7 | i18n | N/A | No user-facing strings. Enum `->label()` placeholders are English for future UI; translation in Phase 20/22 |
| 8 | Adversarial | COVERED (narrow) | Idempotency test covers duplicate-insert; CHECK constraints reject invalid enum strings; NOT NULL enforcement tested via factory round-trip |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references (2 mandatory feature tests + optional schema test)
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s (quick) / 60s (full)
- [ ] `nyquist_compliant: true` set in frontmatter (pending planner task finalization)

**Approval:** pending (awaiting gsd-planner task structure)
