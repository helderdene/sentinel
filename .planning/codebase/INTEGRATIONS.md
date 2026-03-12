# External Integrations

**Analysis Date:** 2026-03-12

## APIs & External Services

**Email Services (Configurable):**
- SMTP - Local development (Mailgun/Mailtrap compatible)
  - Default host: `127.0.0.1:2525` (MailHog or similar)
  - Alternative: `MAIL_MAILER` env var supports Postmark, Resend, SES
- AWS SES - Via `config/services.php`
- Postmark - Via `config/services.php`
- Resend - Via `config/services.php`

**Third-Party Integrations (Configured but Unused):**
- Slack - Notification channel (`config/services.php`)
  - Config key: `SLACK_BOT_USER_OAUTH_TOKEN`

## Data Storage

**Databases:**
- Primary (configured): PostgreSQL
  - Connection config: `config/database.php` - `pgsql` driver
  - Environment vars: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - Client: Laravel Eloquent ORM
- Fallback (development): SQLite
  - File: `database/database.sqlite`
- Supported alternatives: MySQL, MariaDB, SQL Server

**Session Storage:**
- Driver: Database (default)
  - Config: `config/session.php`
  - Table: `sessions` (Laravel default)
  - Alternative: File, Cookie, Redis, Memcached

**File Storage:**
- Local filesystem only
  - Driver: `FILESYSTEM_DISK=local`
  - Storage path: `storage/app/`
  - Config: `config/filesystems.php`
- AWS S3 support available (not configured)

**Caching:**
- Primary: Database cache store
  - Config: `config/cache.php` - `database` driver
  - Table: `cache`
  - Alternative drivers: Redis, Memcached, File, Array
- Test environment: Array cache (in-memory)

**Queue System:**
- Default: Database queue
  - Driver: `queue.connections.database`
  - Table: `jobs`
  - Config: `config/queue.php`
  - Retry after: 90 seconds
- Test environment: Sync queue (synchronous)
- Alternative drivers available: Redis, Beanstalkd, AWS SQS, Deferred, Background
- Failed jobs tracked in `failed_jobs` table
- Job batches tracked in `job_batches` table

## Authentication & Identity

**Auth Provider:**
- Custom Laravel Fortify implementation
  - Guard: `web` (session-based)
  - Provider: Eloquent (users table)
  - Model: `App\Models\User` (`app/Models/User.php`)

**Features Enabled:**
- User registration
- Email verification
- Password reset
- Two-factor authentication (2FA) with backup codes
  - Optional password confirmation
  - Recovery codes generation

**Session Management:**
- Driver: Database
  - Lifetime: 120 minutes (default)
  - Encrypt cookies: Yes (except `appearance`, `sidebar_state`)

## Monitoring & Observability

**Error Tracking:**
- Not detected - Error handling delegated to Laravel exceptions in `bootstrap/app.php`

**Logs:**
- Driver: Stack (multiple channels)
  - Primary channel: `single`
  - Deprecations: Separate channel (null = ignored)
  - Level: `debug`
  - Config: `config/logging.php`

**Broadcasting:**
- Driver: Log (disabled - `BROADCAST_CONNECTION=log`)
  - Only for local development logging

## CI/CD & Deployment

**Hosting:**
- Development: Laravel Herd (local desktop app)
- Production: Not configured - standard PHP/Laravel hosting

**CI Pipeline:**
- No CI detected - run tests locally via `php artisan test` or `composer test`
- Verification commands available in `composer.json`:
  - `composer test` - Full test suite
  - `composer lint:check` - Lint verification
  - `composer ci:check` - Complete CI checks (lint + format + types + test)

## Environment Configuration

**Required env vars:**
```
APP_NAME=IRMS
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://irms.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=irms
DB_USERNAME=root
DB_PASSWORD=password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_FROM_ADDRESS=hello@example.com
```

**Optional env vars:**
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` - S3 integration
- `POSTMARK_API_KEY` - Postmark email service
- `RESEND_API_KEY` - Resend email service
- `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL` - Slack notifications

**Secrets location:**
- `.env` file (not committed to version control)
- Environment variables in hosting platform

## Webhooks & Callbacks

**Incoming:**
- Not detected

**Outgoing:**
- Not detected - queue system uses database polling

## Testing Environment

**Test Configuration (from `phpunit.xml`):**
- Database: SQLite in-memory (`:memory:`)
- Cache: Array driver (in-memory)
- Queue: Sync driver (immediate execution)
- Mail: Array driver (captured for assertions)
- Session: Array driver (in-memory)
- Broadcasting: Disabled
- Monitoring: Disabled (Pulse, Telescope, Nightwatch)

---

*Integration audit: 2026-03-12*
