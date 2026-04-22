# Phase 19: MQTT Pipeline + Listener Infrastructure - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-21
**Phase:** 19-mqtt-pipeline-listener-infrastructure
**Areas discussed:** Inline vs queued handlers, Listener-health liveness state, Banner delivery to dispatch UI, Unknown-camera RecPush + storage/dev-flag details

---

## Gray area selection

| Area | Description | Selected |
|------|-------------|----------|
| Inline vs queued handlers | Listener does everything in-process vs parse/route + dispatch Horizon jobs | ✓ |
| Listener-health liveness state | Where alive-signal state lives (cache / DB table / file marker) | ✓ |
| Banner delivery to dispatch UI | Transport for the "listener down" banner | ✓ |
| Unknown-camera RecPush + storage/dev-flag | Bundle: unknown device_id handling + photo disk + composer run dev process | ✓ |

**Mode:** all 4 selected (multiSelect).

---

## Inline vs queued handlers

### Q1 — How much work runs inside the MQTT listener process vs behind a queue?
| Option | Description | Selected |
|--------|-------------|----------|
| Everything inline (FRAS-parity) | Listener parses, persists, saves images, broadcasts synchronously | ✓ |
| Split: DB inline, images+broadcast queued | DB write synchronous, image + broadcast off the hot path | |
| All handlers dispatch to jobs | Router → job per message; maximum isolation | |

**User's choice:** Everything inline (FRAS-parity)
**Notes:** Aligns with CDRRMO scale (≤8 cameras); inherits validated FRAS v1.0 shape.

### Q2 — Does Phase 19 introduce the dedicated 'fras' Horizon queue now, or defer to Phase 20?
| Option | Description | Selected |
|--------|-------------|----------|
| Introduce 'fras' queue now | Register fras-supervisor block in Phase 19 for Phase 20 readiness | ✓ |
| Use 'default' queue for Phase 19 | Defer queue config to Phase 20 where it becomes load-bearing | |

**User's choice:** Introduce 'fras' queue now
**Notes:** Queue sits idle in Phase 19 (handlers are inline); registered early so Phase 20 + Phase 22 don't re-touch horizon.php.

### Q3 — Duplicate RecPush behavior at the DB idempotency gate?
| Option | Description | Selected |
|--------|-------------|----------|
| Catch UniqueConstraintViolation, log info, drop | DB enforces, handler absorbs silently at info level | ✓ |
| Pre-check with firstOrCreate | Two queries per event; safer under race | |
| Let the exception propagate | Visible failure signal but adds log noise | |

**User's choice:** Catch UniqueConstraintViolation, log info, drop
**Notes:** Matches FRAMEWORK-06 intent; Phase 18 D-54 constraint is the enforcement point.

### Q4 — TopicRouter handler binding style?
| Option | Description | Selected |
|--------|-------------|----------|
| Keep FRAS's app($handlerClass) pattern | Simple map + app()->make() dispatch | ✓ |
| Bind MqttHandler interface + explicit registry | Heavier, matches Contracts convention | |

**User's choice:** Keep FRAS's app($handlerClass) pattern
**Notes:** Handlers still implement MqttHandler interface for type safety; the router just uses app()->make() for dispatch rather than a DI-injected registry.

---

## Listener-health liveness state

### Q1 — Where does 'listener is alive' state live?
| Option | Description | Selected |
|--------|-------------|----------|
| Redis cache key, bumped every 10s | Fast, no schema, matches v1.0 Redis pattern | ✓ |
| New mqtt_listener_health DB table | Auditable history, survives Redis restarts | |
| File marker touched every 10s | Supervisor-native, fragile on multi-host | |

**User's choice:** Redis cache key, bumped every 10s

### Q2 — What counts as the 'healthy' signal?
| Option | Description | Selected |
|--------|-------------|----------|
| Loop-tick heartbeat only | Proves process alive but not broker connection | |
| Last-message-received timestamp | Proves broker + subscription alive | ✓ |
| Both — track separately | Diagnostic value; tiny complexity cost | |

**User's choice:** Last-message-received timestamp
**Notes:** At ≥1 active camera, heartbeat traffic is near-continuous; silence = real problem.

### Q3 — How does the liveness check run?
| Option | Description | Selected |
|--------|-------------|----------|
| routes/console.php Schedule every 30s + command | IRMS-native Schedule facade, broadcasts on state transition | ✓ |
| Frontend polls an endpoint every 20s | HTTP roundtrip per open dispatch tab | |
| Listener self-broadcasts on reconnect/disconnect | Can't broadcast if listener is dead; complements only | |

**User's choice:** routes/console.php Schedule every 30s + command

### Q4 — When does CameraStatus::Degraded get set?
| Option | Description | Selected |
|--------|-------------|----------|
| Phase 19 only sets online/offline; defer 'degraded' to Phase 20 | Heartbeat watchdog thresholds live in Phase 20 | ✓ |
| Phase 19 sets 'degraded' on heartbeat gap ≥30s but <90s | Scope creep risk | |
| Never use 'degraded' — retire the enum case | Requires Phase 18 enum amendment | |

**User's choice:** Phase 19 only sets online/offline; defer 'degraded' to Phase 20

### Q5 (follow-up) — Zero-cameras false-fire mitigation?
| Option | Description | Selected |
|--------|-------------|----------|
| Require at least one active camera before arming watchdog | Cameras.active_count gate; banner stays neutral | ✓ |
| Track both loop-tick AND last-message | More diagnostic signal | |
| Accept the false-fire — document it | Simpler; ops runbook carries the caveat | |

**User's choice:** Require at least one active camera before arming watchdog
**Notes:** Broadcast payload includes NO_ACTIVE_CAMERAS enum so UI renders nothing.

---

## Banner delivery to dispatch UI

### Q1 — Transport channel?
| Option | Description | Selected |
|--------|-------------|----------|
| New broadcast event on existing dispatch.incidents channel | Reuses existing channel auth; zero channels.php wiring | ✓ |
| Dedicated fras.listener private channel | Cleaner separation for fras.* namespace | |
| Reuse/extend dispatch.units channel | Conflates MQTT infra with unit telemetry | |

**User's choice:** New broadcast event on existing dispatch.incidents channel

### Q2 — Payload shape?
| Option | Description | Selected |
|--------|-------------|----------|
| Boolean healthy + last_message_at + reason | Minimal; works for Phase 19 | |
| Full enum health state | Future-proof for HEALTHY/SILENT/DISCONNECTED/NO_ACTIVE_CAMERAS | ✓ |

**User's choice:** Full enum health state
**Notes:** Enum reserves DISCONNECTED for Phase 20+ broker-specific signals.

### Q3 — Initial state on page load?
| Option | Description | Selected |
|--------|-------------|----------|
| Inertia shared prop at page load | First paint accurate; broadcast updates live | ✓ |
| Client fetches on mount, then subscribes | Extra HTTP roundtrip; flash of 'unknown' | |
| Assume healthy until first broadcast | Worst UX during active incident if listener was already down | |

**User's choice:** Inertia shared prop at page load

### Q4 — Banner UX?
| Option | Description | Selected |
|--------|-------------|----------|
| Persistent top banner on DispatchConsole + FRAS-facing pages only | Scoped to FRAS-aware audiences | ✓ |
| Persistent banner everywhere dispatchers go | More intrusive | |
| Toast + non-blocking indicator | Risks missing during Critical event | |

**User's choice:** Persistent top banner on DispatchConsole + FRAS-facing pages only

---

## Unknown-camera RecPush + storage + dev-flag + logging

### Q1 — RecPush for unknown device_id?
| Option | Description | Selected |
|--------|-------------|----------|
| Drop with warning log (FRAS default) | Operational discipline: register camera first | ✓ |
| Auto-create stub Camera row (unclaimed) | Preserves history; requires schema amendment | |
| Dead-letter log table + drop from main table | Another Phase 18-style migration | |

**User's choice:** Drop with warning log (FRAS default)

### Q2 — Photo storage disk?
| Option | Description | Selected |
|--------|-------------|----------|
| Dedicated fras_events disk in config/filesystems.php | Isolated retention blast radius | ✓ |
| Existing 'local' private disk under fras/recognition/ subdir | Fewer moving parts; matches FRAS source | |

**User's choice:** Dedicated fras_events disk in config/filesystems.php

### Q3 — composer run dev MQTT process?
| Option | Description | Selected |
|--------|-------------|----------|
| Always-on 6th process; document Mosquitto as dev prerequisite | Predictable; loud early failure if broker missing | ✓ |
| Opt-in via MQTT_LISTENER_DEV_AUTOSTART env flag | Friendlier to non-FRAS contributors | |
| Separate composer run dev:fras script | No env branching; two dev scripts | |

**User's choice:** Always-on 6th process; document Mosquitto as dev prerequisite

### Q4 — Log channel routing?
| Option | Description | Selected |
|--------|-------------|----------|
| Dedicated 'mqtt' log channel in config/logging.php | Clean separation; Pitfall 6 mandate | ✓ |
| Default stack — inherit v1.0 logging | Simpler; noise risk | |

**User's choice:** Dedicated 'mqtt' log channel in config/logging.php

---

## Claude's Discretion

Areas where the planner has flexibility (detailed in CONTEXT.md):
- Exact TopicRouter pattern strings (port FRAS verbatim, prefix configurable)
- Redis cache key TTL (120s vs 300s — outcome identical)
- Horizon `fras-supervisor` minProcesses/maxProcesses/tries/timeout (match v1.0 defaults)
- Banner Vue component structure (reuse AppBanner if present, else new component)
- Watchdog cadence syntax (`->everyThirtySeconds()` vs cron)
- Supervisor `[program:irms-mqtt]` block exact wording (mirror irms-horizon pattern)
- Post-deploy smoke-test payload shape (sentinel device_id + conditional seeded Camera)

## Deferred Ideas

Captured in CONTEXT.md `<deferred>` section:
- CameraStatus::Degraded semantics → Phase 20
- Auto-create stub Camera on unknown RecPush → revisit if ops feedback warrants
- Dead-letter event table → add if forensics gap surfaces
- Separate loop-tick heartbeat → if broker-silent-but-listener-alive scenarios emerge
- MQTT retain flag / ResumefromBreakpoint → explicitly inherited "no" from FRAS
- Stranger-detection Snap topic → REQUIREMENTS out-of-scope
- EnrollPersonnelBatch jobs → Phase 20
- FrasPhotoProcessor (resize/MD5) → Phase 20
- FrasIncidentFactory IoT bridge → Phase 21
- Signed URLs + fras_access_log → Phase 22
- fras.* private channels (alerts/cameras/enrollments) → Phase 20/22
- TLS posture for routed subnets → future multi-site deploy
- php-mqtt exponential backoff tuning → revisit under prod instability
