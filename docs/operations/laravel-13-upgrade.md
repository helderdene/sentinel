# Laravel 13 Upgrade — Deploy Runbook

> **Scope:** IRMS Phase 17 (Laravel 12 → 13) production cutover.
> **Audience:** CDRRMO ops admin following this runbook without AI assistance.
> **Acceptance:** FRAMEWORK-03 — no queued job executes under a mixed Laravel-version worker.
> **Source:** `.planning/phases/17-laravel-12-13-upgrade/17-RESEARCH.md` §Horizon drain-and-deploy.
> **Last updated:** 2026-04-21 (Phase 17 Wave 3 completion).

---

## 1. Purpose

This runbook is the reproducible procedure for deploying the Laravel 12 → 13 upgrade to
production. It exists because a mixed-version worker pool (L12 + L13 workers processing
the same Redis queue simultaneously) can corrupt queued job payloads. The protocol
**drains Horizon BEFORE deploying**, so every job that executes post-deploy runs under
L13 only. This is the `mitigate` disposition for threat T-17-01 in the Phase 17 threat
model.

Do not skip the drain. Do not abbreviate the drain to a single `horizon:terminate`. The
sequence is `pause → poll → terminate`. Each step has a distinct purpose:

- `pause` — stops Horizon from pulling NEW jobs into workers. Queued jobs stay in Redis.
- Poll `horizon:status` — waits for in-flight jobs in workers to finish naturally.
- `terminate` — shuts workers down cleanly after the queue has quieted.

---

## 2. Preconditions

Before beginning the deploy, verify each of the following:

- [ ] `main` branch passes full Pest suite green on L13. Run locally if CI is offline:
      `php artisan test --compact`
- [ ] The 6 broadcast payload snapshots match byte-for-byte:
      `php artisan test --compact --filter=Broadcasting` — must show `6 passed`.
- [ ] Production Supervisor has both `irms-horizon` and `irms-reverb` programs
      configured with `stopwaitsecs=3600` (see §8).
- [ ] Admin has SSH access to the production DO droplet.
- [ ] Active incident count on the ops dashboard is < 10. Very high-volume periods
      extend the drain window; prefer a quiet maintenance slot.
- [ ] A fresh database backup has been taken. For PostgreSQL:
      `pg_dump irms_prod > /var/backups/irms/pre-l13-$(date +%Y%m%d-%H%M).sql`
- [ ] The maintenance window is identified and announced to ops.
- [ ] Phase 17 commit hashes are known (see §9 — needed for rollback).

---

## 3. Pre-Deploy: Drain Horizon

The drain must be `pause → poll-status → terminate`, not `terminate` alone. Run on the
production app server as the deploy user (typically `www-data` or the configured deploy
account):

```bash
cd /var/www/irms

# 1. Pause Horizon — stops pulling new jobs into workers (queued jobs stay in Redis)
php artisan horizon:pause

# 2. Wait for in-flight jobs to finish (status count goes to 0)
#    Poll with timeout (typical drain: < 30s for IRMS; P1 alerts may hold ~5s each)
for i in {1..60}; do
    php artisan horizon:status 2>&1 | grep -q 'inactive\|paused' && break
    sleep 1
done

# 3. Optionally double-check queue depth (empty = fully drained)
php artisan queue:monitor default,notifications

# 4. Terminate — workers exit gracefully after current tick
php artisan horizon:terminate
```

If the poll loop exits without `inactive` or `paused` (unlikely — loop gives ~60 s),
Supervisor's `stopwaitsecs=3600` still provides a 1-hour safety buffer when we issue
`horizon:terminate`; the `terminate` signal is honored gracefully by workers.

---

## 4. Deploy

Run on the production app server:

```bash
cd /var/www/irms

# Pull the Phase 17 commit range
git pull --ff-only origin main

# Install production dependencies (L13 + aligned packages from Wave 2)
composer install --no-dev --optimize-autoloader

# Regenerate Wayfinder TypeScript (no-op if build artifacts are pre-built, but safe)
php artisan wayfinder:generate

# Run pending migrations — DRY RUN FIRST
php artisan migrate --pretend
# If the --pretend output is sane, apply for real:
php artisan migrate --force

# Clear any stale caches from the L12 build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If the frontend is served via Vite in production mode, also run:

```bash
npm ci
npm run build
```

---

## 5. Post-Deploy: Restart + Verify

```bash
# Supervisor will restart the horizon program automatically after horizon:terminate
# (stopwaitsecs=3600 in supervisor.conf gives up to 1h for a graceful drain window)
sudo supervisorctl restart irms-horizon
sudo supervisorctl restart irms-reverb

# Verify
php artisan horizon:status       # Should report 'running'
curl -sf https://irms.example.com/up   # Health check — replace domain as appropriate
```

Check the Horizon dashboard in a browser as well:

- `https://irms.example.com/horizon` → Dashboard loads without 500.
- Workers show as active.
- No failed jobs from the immediate post-cutover minute.

---

## 6. Smoke Test (Phase 17 Success Criterion 4)

Spot-check the v1.0 dispatch flow end-to-end on the upgraded build (human verification
— the Phase 17 automated test suite does not cover the full user journey):

1. Operator posts a P2 incident at `/intake`.
2. Dispatcher triages, assigns a unit at `/dispatch`.
3. Responder (second browser session) receives the Mapbox-directions push from Reverb,
   acknowledges within 90 s.
4. Responder advances through Dispatched → Acknowledged → OnScene → Resolved with an
   outcome.
5. PDF incident report generates correctly (`/admin/incidents/{id}/report.pdf`).
6. Verify broadcast payload shapes in the dispatcher browser DevTools → Network → WS
   frames match Phase 17 Wave 1 golden fixtures (found under
   `tests/Feature/Broadcasting/__snapshots__/`).

Expected time to green: ~5 minutes. If any step fails, proceed immediately to §7
(Rollback).

---

## 7. Rollback

Trigger rollback if ANY of the following is observed post-deploy:

- Any failing Pest test in a CI re-run against production state.
- Broadcast payload mismatch detected (dispatcher browser shows malformed event data).
- Queued job execution failure in the Horizon dashboard that is NOT a pre-existing
  Family A / Family B baseline failure (see 17-L12-BASELINE.md).
- Any user-facing 500 on a dispatch or responder endpoint.

### Rollback procedure

```bash
cd /var/www/irms

# 1. Drain Horizon (same as §3 Pre-Deploy)
php artisan horizon:pause
for i in {1..60}; do
    php artisan horizon:status 2>&1 | grep -q 'inactive\|paused' && break
    sleep 1
done
php artisan horizon:terminate

# 2. Revert the Phase 17 commit range.
#    The Phase 17 commits are listed in §9 below — revert them in reverse order.
#    The CURRENT wave's commits (at rollback time) must be identified by running
#    `git log --oneline` and identifying hashes whose subject starts with '17-0'.
#    Example (assumes the last 3 commits are Phase 17's Wave 2 + Wave 3 commits):
git revert --no-edit HEAD~2..HEAD

# 3. Restore L12 dependencies from the reverted composer.lock
composer install --no-dev --optimize-autoloader

# 4. Regenerate Wayfinder with the L12 framework
php artisan wayfinder:generate
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

# 5. Restart processes
sudo supervisorctl restart irms-horizon
sudo supervisorctl restart irms-reverb

# 6. Verify rollback
php artisan horizon:status
curl -sf https://irms.example.com/up
composer show laravel/framework | grep versions   # Should show v12.54.x, not v13.x
```

If the database migration from §4 executed something non-reversible before rollback,
restore from the `pre-l13-*.sql` backup taken in §2:

```bash
# DESTRUCTIVE — only use if migration cannot be rolled back via `php artisan migrate:rollback`
psql irms_prod < /var/backups/irms/pre-l13-YYYYMMDD-HHMM.sql
```

---

## 8. Supervisor Configuration Reference

Production Supervisor program blocks (excerpts — full config lives in ops repo):

```ini
[program:irms-horizon]
command=php /var/www/irms/artisan horizon
stopwaitsecs=3600   ; up to 1h graceful shutdown for drain-before-deploy
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/irms/horizon.log

[program:irms-reverb]
command=php /var/www/irms/artisan reverb:start
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/irms/reverb.log
```

The `stopwaitsecs=3600` setting is load-bearing: it tells Supervisor to wait up to 1
hour for a graceful SIGTERM shutdown before escalating to SIGKILL. This matters because
`horizon:terminate` relies on workers finishing their current job before exiting; a
premature SIGKILL mid-job is exactly the mixed-worker corruption T-17-01 mitigates.

---

## 9. Phase 17 Commit History (Rollback Reference)

The Phase 17 upgrade landed across the following commits on `main`. In chronological
order:

| # | Hash | Type | Subject |
|---|------|------|---------|
| 1 | `ca937b4` | test | add IncidentCreated + IncidentTriaged snapshot tests on L12 |
| 2 | `9740fa9` | test | add UnitAssigned, UnitStatusChanged, ChecklistUpdated, ResourceRequested snapshots |
| 3 | `71468a5` | docs | complete broadcast event snapshot baseline plan |
| 4 | `7184fee` | docs | capture L12 baseline failure count (50 pre-existing) |
| 5 | `0419060` | feat | add L13 serializable_classes key + Fortify feature lockdown comment |
| 6 | `1a13aff` | feat | upgrade to Laravel 13 + aligned packages + CSRF middleware rename |
| 7 | `1866ddf` | docs | complete Wave 2 rescoped plan (L13 framework + aligned packages) |
| 8 | _(this wave)_ | docs | add Laravel 13 drain-and-deploy runbook + Wave 3 SUMMARY |

For a full rollback to the pre-Phase-17 state, revert commits 5, 6, 7, 8 (the commits
that ship the L13 framework + aligned packages + runbook). Commits 1–4 (Wave 1 snapshot
baseline + L12 baseline docs) are safe to keep as they work against both L12 and L13.

Resolve actual hashes at rollback time via `git log --oneline --grep='17-0'`.

---

## 10. Post-Deploy Monitoring (first 24 hours)

- Watch `/var/log/irms/horizon.log` for worker restarts or job failures.
- Watch `/var/log/irms/reverb.log` for websocket connection churn.
- Monitor the Horizon dashboard at `/horizon` for job throughput vs baseline.
- Review Family A / Family B failure patterns from the baseline — if any NEW error
  family appears in production logs, treat as a regression and investigate.

---

**Runbook source:** `.planning/phases/17-laravel-12-13-upgrade/17-RESEARCH.md` §Horizon drain-and-deploy.
**Linked requirements:** FRAMEWORK-03 (this runbook), FRAMEWORK-01 (full suite green), FRAMEWORK-02 (snapshot byte-match).
**Threat mitigations:** T-17-01 (mixed-worker payload corruption), T-R3-01 (drain poll timeout).
