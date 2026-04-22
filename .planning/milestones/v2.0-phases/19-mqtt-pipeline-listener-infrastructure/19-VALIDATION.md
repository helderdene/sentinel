---
phase: 19
slug: mqtt-pipeline-listener-infrastructure
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 19 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (on PHPUnit 12) |
| **Config file** | `phpunit.xml` (existing), `tests/Pest.php` (existing) |
| **Quick run command** | `php artisan test --compact --filter=Mqtt` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~45 seconds (quick), ~90 seconds (full) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=Mqtt`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

Filled in by planner (`gsd-planner`) against each task's `<acceptance_criteria>` + `<automated>` blocks. See `19-RESEARCH.md` §Validation Architecture for the requirement-to-test-file mapping the planner consumes.

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Verify `/Users/helderdene/fras/app/Enums/AlertSeverity.php` exists OR confirm `RecognitionHandler` severity-classification source (A1 assumption from research)
- [ ] Confirm `MQTT::fake()` helper availability in `php-mqtt/laravel-client` ^1.8 OR fall back to Mockery on the subscriber connection (A2 assumption from research)
- [ ] Confirm whether `AppBanner.vue` exists in `resources/js/components/` or create new `MqttListenerHealthBanner.vue`

*Existing Pest 4 + PHPUnit 12 infrastructure covers Phase 19; no framework install needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Live `mosquitto_pub` RecPush smoke test after `composer run dev` | MQTT-02 | Requires running Mosquitto broker; cannot run in CI | Start `composer run dev`; `mosquitto_pub -t 'mqtt/face/{device_id}/Rec' -m '{payload}'` with both `personName` and `persionName` spellings; assert one `recognition_events` row, base64 face + scene images on `fras_events` disk, no errors in `storage/logs/mqtt-*.log` |
| Supervisor `irms-mqtt` program startup + clean shutdown | MQTT-01, MQTT-06 | Requires Supervisor running on host OS | Deploy `irms-mqtt.conf`; run `sudo supervisorctl reread && sudo supervisorctl update`; assert `sudo supervisorctl status irms-mqtt` shows `RUNNING`; `horizon:terminate` leaves `irms-mqtt` untouched (verify via second `status` check) |
| Broker bounce reconnect | MQTT-03 | Requires restarting Mosquitto mid-run | With listener running, `brew services restart mosquitto` (or `systemctl restart mosquitto`); assert listener reconnects without manual restart, next `mosquitto_pub` lands in DB |
| `--max-time=3600` hourly rotation | MQTT-03 | 1-hour wall clock wait | Run `php artisan irms:mqtt-listen --max-time=60` (shortened); assert clean exit after 60s, Supervisor restart triggers new process |
| Deploy protocol: `supervisorctl restart irms-mqtt:*` | MQTT-01 (Pitfall 7) | Requires deployment rehearsal | Add to `docs/operations/irms-mqtt.md` §Deploy Protocol; verify runbook correctness during first prod-path deploy |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references (A1, A2, banner component)
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
