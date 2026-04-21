# Phase 17 — L12 Test Baseline (captured post-Wave-1)

**Captured:** 2026-04-21
**Laravel version:** 12.54.1 (unchanged from v1.0 shipped state)
**Purpose:** Regression oracle for FRAMEWORK-01 / SC1 — Phase 17 succeeds if the L13 upgrade introduces ZERO new failures beyond this baseline, OR reduces the failure count.

---

## Baseline counts

| Metric | L12 full-suite (post-Wave-1) |
|--------|------------------------------|
| Total tests | 581 (529 pass + 50 fail + 2 skipped) |
| Passed | 529 |
| **Failed** | **50** |
| Skipped | 2 |
| Assertions | 1,999 |
| Duration | ~25s |

## User decision (recorded 2026-04-21)

**SC1 reinterpreted:** Phase 17 succeeds when `failed_count_L13 <= 50` with the SAME root-cause families as this baseline. The 50 baseline failures are pre-existing v1.0 debt and out-of-scope for Phase 17 (feature-free upgrade discipline, D-06). They will be tracked as a separate follow-up phase in the v2.0 milestone backlog.

## Root-cause families (observed)

All 50 failures reduce to two root causes — both are **test-isolation bugs**, not production-code bugs:

### Family A: `incident_categories_name_unique` collision (majority)
- **Symptom:** `SQLSTATE[23505]: Unique violation: Key (name)=(autem) already exists`
- **Trigger:** `IncidentCategoryFactory::definition()` uses `fake()->unique()->word()`, which drifts after the first few seeded/test rows land. Pest parallel workers or RefreshDatabase + seeder combinations collide.
- **Affected test classes (representative):** `AdminIncidentCategoryTest`, `AdminIncidentTypeTest`, `AdminUnitTest`, `AdminUserTest`, `Analytics\HeatmapTest`, `Analytics\KpiDashboardTest`, `Dispatch\DispatchConsolePageTest`, `Dispatch\ProximityRankingTest`, `Foundation\IncidentModelTest`, `Foundation\UserUnitTest`, `Intake\BarangayAssignmentTest`, `Intake\CreateIncidentTest`, `Intake\DispatchQueueTest`, `Intake\IoTWebhookTest`, `Intake\SmsWebhookTest`, `Intake\TriageIncidentTest`, `RealTime\StateSyncTest`, `Responder\MessagingTest`
- **Fix (out of scope for Phase 17):** reset `fake()->unique()->default->reset()` in factory OR use deterministic name pool OR `$faker->unique(true)`.

### Family B: `users_pkey` collision on broadcast snapshot tests
- **Symptom:** `SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "users_pkey"`
- **Trigger:** Wave 1 snapshot tests pin `user_id = 42` for determinism. When the full suite runs, another earlier test class creates a user with ID 42 first; RefreshDatabase does not isolate across test classes in the same process. The snapshot fixtures themselves are correct — the full-suite run order is non-deterministic.
- **Affected test classes:** 5 broadcast snapshot tests (IncidentCreated, IncidentTriaged, ChecklistUpdated, ResourceRequested, UnitAssigned). Note: filter-mode `--filter=Broadcasting` passes green for all 6 — this is a full-suite interaction only.
- **Fix (out of scope for Phase 17):** use `forceFill()` with `User::find(42) ?? User::factory()->create(['id' => 42])` pattern OR use `DatabaseTransactions` trait instead of `RefreshDatabase` on snapshot tests, OR delete the earlier conflicting test row in `beforeEach`.

## Regression gate for Wave 3

**Phase 17 verifier MUST compare post-L13 failure count and root-cause families against this baseline:**

1. Run `php artisan test --compact` on L13 (after Wave 3 completes).
2. Extract failed count: if `failed_L13 > 50`, Phase 17 FAILS verification — L13 introduced new regressions.
3. If `failed_L13 <= 50`, inspect the L13 failure list — every failure MUST map to Family A or Family B above. Any new root-cause family → L13 regression → verification FAILS.
4. If `failed_L13 == 50` with same families AND the 6 broadcast snapshots still byte-match their Wave-1 fixtures (run via `--filter=Broadcasting`), Phase 17 PASSES SC1 + SC2.

## Reference outputs

Full baseline run captured at `/tmp/baseline_failures.txt` (60-line grep of FAIL/ERROR lines) during the Wave 1 verification spot-check.
