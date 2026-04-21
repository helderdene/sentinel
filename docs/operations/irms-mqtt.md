# IRMS MQTT Listener — Operations Runbook

> **Scope:** IRMS Phase 19 MQTT pipeline listener infrastructure.
> **Audience:** CDRRMO ops admin and developers setting up the MQTT listener locally or in production.
> **Acceptance:** MQTT-01 — end-to-end listener runs locally (6th `composer run dev` process) and in production (dedicated Supervisor program).
> **Source:** `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` (D-16, Pitfalls 6/7).
> **Last updated:** 2026-04-21 (Phase 19 Wave 5 completion).

---

## 1. Overview

Phase 19 ships the MQTT ingestion pipeline that powers the FRAS (Face Recognition
Access/Safety) integration. The architecture is:

```
Mosquitto broker  →  irms:mqtt-listen command (php-mqtt/client)
                             │
                             ▼
                        TopicRouter
                             │
              ┌──────────────┼──────────────┬──────────────┐
              ▼              ▼              ▼              ▼
      RecognitionHandler   AckHandler  HeartbeatHandler  OnlineOfflineHandler
              │
              ▼
   recognition_events (DB) + images on fras_events disk
              │
              ▼
   watchdog heartbeat → dispatch console banner (loud + early)
```

Key runtime components:

- **Listener command:** `php artisan irms:mqtt-listen` (Plan 19-04) — long-running process, auto-exits after `--max-time=3600` to allow Supervisor to restart with fresh code (Pitfall 7 mitigation aid).
- **Watchdog command:** `php artisan irms:mqtt-watchdog` (Plan 19-05) — scheduler-driven; broadcasts `MqttListenerHealthChanged` when listener goes silent.
- **Log channel:** `mqtt` (see `config/logging.php`) — daily file at `storage/logs/mqtt-YYYY-MM-DD.log`.
- **Storage disk:** `fras_events` — evidence images captured from face recognition events.

---

## 2. Local Dev Prerequisites

The MQTT listener requires a running Mosquitto broker on your dev machine.

### Install Mosquitto

**macOS (Homebrew):**
```bash
brew install mosquitto
brew services start mosquitto
```

**Linux (Debian/Ubuntu):**
```bash
sudo apt-get install -y mosquitto mosquitto-clients
sudo systemctl enable --now mosquitto
```

### Verify the broker is reachable

In one terminal, subscribe to the FRAS topic tree:
```bash
mosquitto_sub -h localhost -t 'mqtt/face/#' -v
```

In another terminal, publish a heartbeat:
```bash
mosquitto_pub -h localhost -t mqtt/face/heartbeat -m '{}'
```

You should see the message print in the subscriber terminal.

### Start the 6-process dev stack

`composer run dev` now starts 6 concurrent processes instead of 5:

| # | Process                            | Name      | Color       |
|---|------------------------------------|-----------|-------------|
| 1 | `php artisan serve`                | `server`  | `#93c5fd`   |
| 2 | `php artisan reverb:start`         | `reverb`  | `#c4b5fd`   |
| 3 | `php artisan horizon`              | `horizon` | `#fb7185`   |
| 4 | `php artisan pail --timeout=0`     | `logs`    | `#fdba74`   |
| 5 | `npm run dev`                      | `vite`    | `#86efac`   |
| 6 | `php artisan irms:mqtt-listen`     | `mqtt`    | `#f59e0b`   |

If Mosquitto is not running when you start `composer run dev`, the listener exits with
a connection-refused error. Because `concurrently --kill-others` is set, the rest of
the stack shuts down with it. This is intentional (D-16: loud + early) — a silently
broken MQTT pipeline is far more dangerous than a noisy startup failure.

---

## 3. Production Supervisor Block

Run the listener under its own Supervisor program. **Do not run it under Horizon.**
This is Pitfall 6: Horizon restarts (deploy, `horizon:terminate`, or crashes) must
not affect the long-lived MQTT connection.

Create `/etc/supervisor/conf.d/irms-mqtt.conf`:

```ini
[program:irms-mqtt]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/irms/artisan irms:mqtt-listen --max-time=3600
autostart=true
autorestart=unexpected
stopwaitsecs=30
stopsignal=TERM
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/irms-mqtt.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
```

Reload Supervisor and start the program:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start irms-mqtt:*
```

### Why these settings?

- `autorestart=unexpected` — if the listener exits cleanly via `--max-time=3600`
  (code 0), Supervisor restarts it as intended (hourly rotation). If it crashes,
  Supervisor also restarts it. This is the CONTEXT discretion from 19-CONTEXT.md.
- `stopwaitsecs=30` — give the listener 30 seconds to flush an in-flight message
  and disconnect cleanly before Supervisor escalates to SIGKILL. MQTT messages are
  tiny and acknowledgements are fast; 30 s is plenty (unlike Horizon's 3600 s drain).
- `stopsignal=TERM` — the listener traps SIGTERM and disconnects gracefully.
- `numprocs=1` — only one listener process per host. MQTT subscriber fan-out is a
  broker concern, not a worker concern.

**Never put this under Horizon** — Pitfall 6 mandates separation. Horizon restart
must not affect the listener.

---

## 4. Deploy Protocol (Pitfall 7 mitigation)

The deploy script MUST restart the MQTT listener after pulling new code. Otherwise
the listener keeps running against the PREVIOUS deploy's code indefinitely — this
is Pitfall 7.

Add the following to your deploy script **AFTER** `supervisorctl restart irms-horizon:*`
and **AFTER** migrations run:

```bash
sudo supervisorctl restart irms-mqtt:*
```

### Verify the listener picked up new code

Immediately after the restart command:

```bash
tail -n 20 storage/logs/mqtt-$(date +%Y-%m-%d).log | grep "MQTT listener started"
```

**Expected:** a fresh `MQTT listener started` log line timestamped within the last
few seconds. The new timestamp proves the new deploy's code is running.

**If missing:** the listener did NOT restart — investigate before leaving the deploy.
Common causes:
- Supervisor program name mismatch (check `sudo supervisorctl status`).
- File permissions on `/var/log/supervisor/irms-mqtt.log` prevent the new process
  from writing.
- `--max-time=3600` timer clock skew (rare, but possible after NTP correction).

---

## 5. Post-Deploy smoke test runbook

After every deploy, publish a sentinel message and verify the pipeline processes it.

### Step 1 — Watch the MQTT log

In one terminal on the production host:

```bash
tail -f storage/logs/mqtt-$(date +%Y-%m-%d).log
```

### Step 2 — Publish a sentinel heartbeat

From a machine that can reach the broker:

```bash
mosquitto_pub -h <MQTT_HOST> -p 1883 -u <MQTT_USERNAME> -P <MQTT_PASSWORD> \
  -t mqtt/face/heartbeat -m '{"facesluiceId":"irms-smoketest"}'
```

### Step 3 — Verify the pipeline

**Expected in Terminal 1:** a log line showing the heartbeat topic was routed. The
sentinel `irms-smoketest` is intentionally not a registered camera, so you should
also see a `Heartbeat for unknown camera` warning — this is the expected Phase 19
smoke test path (it exercises the listener, the TopicRouter, and the HeartbeatHandler
without polluting real camera state).

**If nothing appears in the log:** the listener is not receiving messages. Check:
- Broker reachability from the production host: `nc -zv <MQTT_HOST> 1883`.
- Broker credentials in `.env` match what `mosquitto_pub` used.
- Topic ACL on the broker permits subscription to `mqtt/face/#`.

---

## 6. Troubleshooting

### Listener will not start

Check the MQTT log for connection errors:

```bash
tail storage/logs/mqtt-*.log
```

Common symptoms:
- `Connection refused` → Mosquitto not running or `MQTT_HOST` / `MQTT_PORT` wrong
  in `.env`.
- `Not authorized` → `MQTT_USERNAME` / `MQTT_PASSWORD` wrong or broker ACL denies
  the client ID.
- `Socket error: Name or service not known` → DNS lookup failure for `MQTT_HOST`.

### Banner stuck on SILENT

The `mqtt:listener:last_message_received_at` Redis key is how the watchdog decides
listener health. If the dispatch console banner stays red:

```bash
# Verify the scheduler is running
php artisan schedule:work   # dev only; production uses cron

# Check the watchdog heartbeat key
redis-cli GET 'mqtt:listener:last_message_received_at'
```

If the Redis key is stale, the listener is silent (either the process is dead or
broker connectivity has failed). The watchdog will broadcast `MqttListenerHealthChanged`
every tick until a message lands.

### Duplicate RecPush showing up multiple times in logs

This is expected. Phase 19 D-03 implements idempotency at the DB layer: duplicate
RecPush rows log `Duplicate RecPush rejected at DB layer` and return early. You'll
see the duplicate log line but the row is not re-inserted.

---

## 7. Deferred

The following MQTT-adjacent work is deferred to later phases:

- **Phase 20 — Camera admin:** CRUD UI to register cameras (replaces the
  "Heartbeat for unknown camera" smoke test path with real device registration).
- **Phase 21 — FrasIncidentFactory bridge:** Auto-create incidents from recognition
  events that match watchlist criteria.
- **Phase 22 — Retention + privacy:** Automatic purge of old FRAS events, signed
  URLs for evidence images, DPA audit log for every access.

---

**Runbook source:** `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md`.
**Linked requirements:** MQTT-01 (this runbook + Supervisor block).
**Threat mitigations:** T-19-01 (stale-code deploy — §4), broker auth (§5 — operator-configured).
