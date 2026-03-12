# Technology Stack

**Analysis Date:** 2026-03-12

## Languages

**Primary:**
- PHP 8.2+ - Backend framework and application logic
- TypeScript 5.2.2 - Frontend type safety
- Vue 3.5.13 - Frontend UI framework

**Secondary:**
- SQL - Database queries (via Eloquent ORM)

## Runtime

**Environment:**
- PHP-FPM via Laravel Herd (development)
- Node.js (for frontend build tooling)

**Package Manager:**
- Composer - PHP dependencies
- npm - JavaScript/TypeScript dependencies
- Lockfiles: `composer.lock`, `package-lock.json`

## Frameworks

**Core:**
- Laravel 12 - Full-stack PHP framework
- Inertia.js v2 - Server-side rendered SPA adapter (`inertiajs/inertia-laravel` v2)
- Laravel Fortify v1 - Authentication scaffolding with 2FA support
- Laravel Wayfinder v0 - Type-safe route generation for frontend

**Frontend Build:**
- Vite 7.0.4 - Module bundler and dev server
- `laravel-vite-plugin` v2.0.0 - Laravel/Vite integration
- `@tailwindcss/vite` v4.1.11 - Tailwind CSS 4 build integration

**Testing:**
- Pest v4.4 - PHP testing framework
- `pestphp/pest-plugin-laravel` v4.1 - Pest Laravel integration
- PHPUnit v12 - Test runner (underlying Pest)

**Development Tools:**
- Laravel Pint v1.24 - PHP code formatter
- Laravel Pail v1.2.2 - Log viewing utility
- Laravel Sail v1.41 - Docker containerization
- Laravel Boost v2 - MCP server with development tools

## Key Dependencies

**Critical:**
- `laravel/framework` v12.0 - Foundation of application
- `inertiajs/inertia-laravel` v2.0 - SPA rendering without SPA complexity
- `laravel/fortify` v1.30 - Authentication features (registration, 2FA, password reset)
- `laravel/wayfinder` v0.1.9 - Type-safe route generation for frontend controllers

**Frontend UI:**
- `@inertiajs/vue3` v2.3.7 - Vue 3 Inertia adapter
- `reka-ui` v2.6.1 - Unstyled component library (headless UI)
- `tailwindcss` v4.1.1 - Utility-first CSS framework
- `lucide-vue-next` v0.468.0 - Icon library
- `class-variance-authority` v0.7.1 - CSS class composition utility
- `clsx` v2.1.1 - Conditional className utility
- `tailwind-merge` v3.2.0 - Tailwind CSS class merging
- `tw-animate-css` v1.2.5 - Animation utilities
- `vue-input-otp` v0.3.2 - OTP input component

**Frontend Utilities:**
- `@vueuse/core` v12.8.2 - Vue composition utilities
- `@laravel/vite-plugin-wayfinder` v0.1.3 - Wayfinder integration with Vite

**Development:**
- `eslint` v9.17.0 - JavaScript linting
- `prettier` v3.4.2 - Code formatter
- `typescript` v5.2.2 - TypeScript compiler
- `vue-tsc` v2.2.4 - Vue 3 type checking
- `mockery/mockery` v1.6 - PHP mocking library
- `fakerphp/faker` v1.23 - Fake data generation
- `nunomaduro/collision` v8.6 - Error display formatter

**Backend Utilities:**
- `laravel/tinker` v2.10.1 - Interactive REPL

## Configuration

**Environment:**
- Configuration files in `config/` directory
- Environment variables read from `.env` file
- Environment-specific settings:
  - `APP_ENV`: local, testing, production
  - `APP_DEBUG`: debug mode toggle
  - `APP_URL`: base application URL

**Build:**
- `vite.config.ts` - Vite build configuration with Laravel, Tailwind, Vue, and Wayfinder plugins
- `tailwind.config.js` - Tailwind CSS configuration (auto-generated)
- `tsconfig.json` - TypeScript compiler configuration with path alias `@/*` → `resources/js/*`
- `eslint.config.js` - ESLint rules for Vue, TypeScript, import ordering, and code style
- `.prettierrc` - Prettier formatting with Tailwind CSS plugin
- `phpunit.xml` - PHPUnit/Pest test suite configuration

## Platform Requirements

**Development:**
- PHP 8.2 or higher
- Composer
- Node.js (for frontend development)
- npm or yarn
- Laravel Herd (macOS/Windows desktop app for local development)

**Database (configurable):**
- Default: SQLite (`database/database.sqlite`)
- Alternative: PostgreSQL (configured in `.env` as primary)
- Alternative: MySQL/MariaDB (supported)
- Other: SQL Server

**Production:**
- PHP 8.2+ application server (Apache/Nginx)
- PostgreSQL or MySQL database
- Redis (optional, for caching/sessions/queues)
- Beanstalkd, SQS, or database queue worker (for background jobs)

---

*Stack analysis: 2026-03-12*
