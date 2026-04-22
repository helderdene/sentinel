---
phase: 21
slug: recognition-iot-intake-bridge-dispatch-map-intakestation-rai
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-22
---

# Phase 21 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Sourced from `21-RESEARCH.md` §Validation Architecture — planner to refine per-task.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4.6 (`pestphp/pest-plugin-laravel` 4.1) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=Fras` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30–90 seconds (full suite), <10s (FRAS filter) |
| **FRAS-specific note** | Tests in `tests/Feature/Fras/` run against PostgreSQL per FRAMEWORK-05 |

---

## Sampling Rate

- **After every task commit:** `php artisan test --compact --filter=Fras`
- **After every plan wave:** `php artisan test --compact` + `vendor/bin/pint --test --format agent` + `npm run types:check`
- **Before `/gsd-verify-work`:** Full suite green + lint + type-check clean
- **Max feedback latency:** ~10 seconds (filtered suite) / ~90s (full)

---

## Per-Task Verification Map

> Planner fills this table task-by-task during plan generation; each `<automated_verify>` block must map to one row here.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| _pending_ | — | — | — | — | — | — | — | — | ⬜ pending |

---

## Phase Requirements → Test Map

| Req ID | Behavior | Test File | File Exists? |
|--------|----------|-----------|-------------|
| RECOGNITION-01 | Every RecPush persists to `recognition_events` regardless of severity | `tests/Feature/Fras/RecognitionHandlerTest.php` (Phase 19) | Extend |
| RECOGNITION-02 | Critical recognition → Incident with correct channel/priority/timeline | `tests/Feature/Fras/FrasIncidentFactoryTest.php` | ❌ Wave 0 |
| RECOGNITION-03 | `IoTWebhookController` refactor preserves existing IoT sensor tests (gate) | `tests/Feature/IoTWebhookControllerTest.php` (existing) | Must pass UNCHANGED |
| RECOGNITION-04 | Escalate-to-P1 button renders + submits + audits | `tests/Feature/Fras/EscalateToP1Test.php` | ❌ Wave 0 |
| RECOGNITION-05 | Warning severity broadcasts but no Incident | `FrasIncidentFactoryTest::it_broadcasts_warning_without_creating_incident` | ❌ Wave 0 |
| RECOGNITION-06 | Dedup within 60s for same (camera,personnel) pair | `FrasIncidentFactoryTest::it_dedups_within_window` | ❌ Wave 0 |
| RECOGNITION-07 | Below-threshold confidence → no broadcast, no Incident | `FrasIncidentFactoryTest::it_skips_below_threshold` | ❌ Wave 0 |
| RECOGNITION-08 | Thresholds read from config (no hardcoded values) | `FrasIncidentFactoryTest::it_respects_config_overrides` | ❌ Wave 0 |
| INTEGRATION-01 | Pulse triggered via Echo event + RecognitionAlertReceived payload shape | `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` | ❌ Wave 0 |
| INTEGRATION-03 | IntakeStation 6th rail renders + Echo-hydrates | `tests/Feature/Fras/IntakeStationFrasRailTest.php` | ❌ Wave 0 |
| INTEGRATION-04 | `useDispatchFeed.ts` unchanged (file identity) | `git diff main -- resources/js/composables/useDispatchFeed.ts` (expect zero diff) | — (git check) |

---

## Success Criteria → Validation Map

| SC | Behavior | Validation Approach |
|----|----------|--------------------|
| SC1 | RecPush against BOLO personnel at ≥0.75 → P2 Incident, pulse on map, rail card, escalate-to-P1 flow | `php artisan test --compact --filter=Fras` + manual UAT with `mosquitto_pub` |
| SC2 | Dedup: second event within 60s does NOT create 2nd Incident but DOES persist | `FrasIncidentFactoryTest::it_dedups_within_window` |
| SC3 | `tests/Feature/IoTWebhookControllerTest.php` passes unchanged | Run file directly; zero modifications |
| SC4 | Lost-child recognition → P1 directly (no escalate needed) | `FrasIncidentFactoryTest::it_creates_p1_for_lost_child` |
| SC5 | Warning severity broadcasts + pulses map but NO Incident created | `FrasIncidentFactoryTest::it_broadcasts_warning_without_creating_incident` + mock `setFeatureState` assertion |
| SC6 | Map + Reverb sustain 50 events/sec/camera | **Planner picks:** (a) `scripts/fras-burst.sh` + Chrome DevTools FPS meter, (b) manual UAT baseline, (c) defer to Phase 22. **Recommendation: (a)** at Phase 21 merge, not CI. |

---

## Wave 0 Requirements

- [ ] `tests/Feature/Fras/FrasIncidentFactoryTest.php` — 5 gates × 2 methods + full payload
- [ ] `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` — payload + channel + severity paths
- [ ] `tests/Feature/Fras/EscalateToP1Test.php` — render conditions + route reuse + audit trigger + gate
- [ ] `tests/Feature/Fras/IntakeStationFrasRailTest.php` — prop shape + Echo wiring + 6th rail render
- [ ] Extend `tests/Feature/Fras/RecognitionHandlerTest.php` — assert factory called after persist
- [ ] Optional: `scripts/fras-burst.sh` — 50 events/sec MQTT stress for SC6 validation

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Pulse animation visual fidelity at 60fps | INTEGRATION-01 | Animation quality is subjective; GPU-accelerated paint expression, can't assert "looks smooth" in a test | Run `mosquitto_pub` loop → open dispatch map → Chrome DevTools Performance panel → confirm 60fps hold |
| SC6 50 events/sec load behaviour | SC6 | Synthetic load + visual FPS are easier manually at Phase 21; automation is Phase 22 scope | Use `scripts/fras-burst.sh` if added; otherwise 30s burst + dev-tools FPS meter |
| Rail visual polish (card layout, badge colors, modal layout) | RECOGNITION-04, INTEGRATION-03 | Design-review concern; UI-SPEC.md is approved but pixel verification is human-in-loop | Follow UI-SPEC.md §Acceptance in browser at `irms.test/intake` |
| Face thumbnail signed-URL flow (if D-20 option a chosen) | RECOGNITION-04 | Image rendering + signed-URL expiry are easier eyeballed | Load rail → inspect Network tab → confirm signed URL + 401 after 5-min expiry |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies (planner sets)
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s on Fras-filtered suite
- [ ] `nyquist_compliant: true` set in frontmatter after plan-checker approves coverage

**Approval:** pending
