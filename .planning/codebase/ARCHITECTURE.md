# Architecture

**Analysis Date:** 2026-03-12

## Pattern Overview

**Overall:** Monolithic Full-Stack SPA with Server-Driven Rendering

**Key Characteristics:**
- Laravel backend (API + server rendering) serving an Inertia Vue 3 frontend
- Fortified authentication with email verification and two-factor authentication
- Shared-nothing request/response architecture using Inertia's data bridge
- Form-centric interaction model with server-side validation
- Server-side rendering (SSR) enabled for initial page loads with client-side hydration

## Layers

**Backend Layer (PHP/Laravel):**
- Purpose: Handle HTTP requests, business logic, authentication, database operations
- Location: `app/`, `routes/`
- Contains: Controllers, Models, Requests, Actions, Middleware, Service Providers
- Depends on: Laravel framework, Fortify authentication package, Eloquent ORM
- Used by: Frontend (via HTTP responses); Console commands via `routes/console.php`

**Frontend Layer (TypeScript/Vue 3):**
- Purpose: Render user interface, handle user interactions, client-side state
- Location: `resources/js/`
- Contains: Pages, components, layouts, composables, types
- Depends on: Inertia, Vue 3, Tailwind CSS, Wayfinder (route generation)
- Used by: Browser rendering via Vite

**Middleware Layer:**
- Purpose: Cross-cutting request handling (authentication, appearance, Inertia setup)
- Location: `app/Http/Middleware/`
- Contains: `HandleInertiaRequests`, `HandleAppearance`
- Configured in: `bootstrap/app.php`

**Database Layer:**
- Purpose: Data persistence and retrieval
- Location: `database/` (migrations, seeders, factories)
- Models: `app/Models/`
- Uses Eloquent ORM with constructor property promotion

## Data Flow

**Page Request Flow:**

1. Browser requests page → Laravel router (routes/web.php or routes/settings.php)
2. Middleware chain executes: Cookie decryption → Appearance handling → Inertia setup
3. Controller method executes:
   - Loads authenticated user via `$request->user()`
   - Prepares props (data) for Inertia
   - Returns `Inertia::render('page/name', ['props' => 'data'])`
4. Inertia middleware wraps response with shared props (auth.user, sidebarOpen, etc.)
5. SSR/Vite renders component on server (if enabled) or client hydrates
6. Browser displays page component from `resources/js/pages/`

**Form Submission Flow:**

1. Vue component collects form data
2. Wayfinder-generated action (TypeScript) sends POST/PUT/PATCH/DELETE to backend
3. Form Request class validates input (e.g., `ProfileUpdateRequest`)
4. Controller updates model via Eloquent
5. Redirects back with session flash data or returns JSON
6. Inertia intercepts redirect and updates page without full reload
7. Updated props trigger Vue reactivity

**Authentication Flow:**

1. Fortify provides auth controllers and routes (login, register, password reset, 2FA)
2. `CreateNewUser` action validates and creates user
3. Middleware checks `auth` and `verified` guards
4. `HandleInertiaRequests` shares authenticated user via shared props
5. Frontend uses `auth.user` prop to determine UI state

## Key Abstractions

**Inertia Component:**
- Purpose: Single Vue component that represents a page
- Examples: `resources/js/pages/Dashboard.vue`, `resources/js/pages/settings/Profile.vue`
- Pattern: Script setup with TypeScript, template rendering props and shared data
- Props flow: Server → Client via Inertia middleware

**Layout Component:**
- Purpose: Wrap pages with consistent UI structure
- Examples: `resources/js/layouts/AppLayout.vue`, `resources/js/layouts/auth/AuthLayout.vue`
- Pattern: Slot-based composition, receives breadcrumbs and other layout props
- Uses: Sidebar, header, navigation from `resources/js/layouts/app/`

**Controller:**
- Purpose: Handle HTTP requests, coordinate Model and View (Inertia)
- Example: `app/Http/Controllers/Settings/ProfileController.php`
- Pattern: Methods return `Inertia::render()` for pages or `RedirectResponse` for mutations
- Responsibility: Load data, call model methods, prepare props

**Form Request:**
- Purpose: Encapsulate validation rules and authorization
- Examples: `app/Http/Requests/Settings/ProfileUpdateRequest.php`
- Pattern: Extend `FormRequest`, implement `rules()` method, can use traits for reusable rules
- Consumed by: Controllers receive validated data via `$request->validated()`

**Action Class:**
- Purpose: Encapsulate complex business logic separate from controllers
- Example: `app/Actions/Fortify/CreateNewUser.php`
- Pattern: Implements interface (e.g., `CreatesNewUsers`), public method receives input
- Used by: Fortify callbacks for registration, password reset, etc.

**Concern/Trait:**
- Purpose: Share validation rules and other cross-cutting logic
- Examples: `app/Concerns/ProfileValidationRules.php`, `app/Concerns/PasswordValidationRules.php`
- Pattern: Methods return arrays of rules, used by FormRequests and Actions
- Benefit: Single source of truth for validation

**Middleware:**
- Purpose: Request/response transformation
- Examples: `HandleInertiaRequests`, `HandleAppearance`
- Pattern: Implement `handle(Request, Closure): Response`
- Configured in: `bootstrap/app.php` via `Application::configure()->withMiddleware()`

**Composable:**
- Purpose: Reusable Vue 3 logic for components
- Examples: `resources/js/composables/useAppearance.ts`, `resources/js/composables/useTwoFactorAuth.ts`
- Pattern: Export function returning reactive state and methods
- Used by: Page components and other composables

**Wayfinder Function:**
- Purpose: Type-safe client-side route and action generation
- Generated: Auto-generated from Laravel routes via wayfinder plugin
- Pattern: Import from `@/actions/` (controllers) or `@/routes/` (named routes)
- Example: `import StorePost from '@/actions/.../StorePostController'`

## Entry Points

**Web Route Entry:**
- Location: `routes/web.php`
- Triggers: Browser navigation to `/` or other web routes
- Responsibilities: Dispatch to Inertia page renders or controller actions

**Settings Route Entry:**
- Location: `routes/settings.php` (included from web.php)
- Triggers: User navigating to `/settings/*` paths
- Responsibilities: Route profile, password, appearance, two-factor settings

**App Entry Point (Frontend):**
- Location: `resources/js/app.ts`
- Triggers: After Vite/SSR loads initial HTML
- Responsibilities: Initialize Inertia app with Vue 3, set up theme, mount to DOM

**Console Entry:**
- Location: `routes/console.php`
- Triggers: `php artisan [command]`
- Responsibilities: Define artisan commands

## Error Handling

**Strategy:** Server-side exception handling with Fortify-provided auth exceptions

**Patterns:**
- Validation errors → Form Request fails → Session flash with error messages
- Authorization failures → Middleware redirects or gate fails
- Model not found → 404 response (HTTP 404)
- Authentication required → Redirect to login (Fortify)
- Unverified email → Redirect to verification screen (Fortify)

**Frontend:**
- Inertia catches redirects and handles as client navigation
- Form validation errors displayed via component state from shared props
- Session flash data (status, error) passed via Inertia props

## Cross-Cutting Concerns

**Logging:** Uses Laravel's default logging (config/logging.php) to `storage/logs/`

**Validation:**
- Centralized in Form Request classes with trait-based rule sharing
- Custom rules for email uniqueness with user ID handling
- Password validation with configurable strength (production vs local)

**Authentication:**
- Laravel Fortify provides built-in login, registration, password reset, 2FA
- Custom `CreateNewUser` action implements `CreatesNewUsers` contract
- Two-factor authentication uses `TwoFactorAuthenticatable` trait on User model

**Authorization:**
- Middleware guards on routes (auth, verified)
- No explicit policies currently; simple route-level checks suffice

**Appearance/Theme:**
- Cookie-based preference stored as `appearance` (system/light/dark)
- Middleware `HandleAppearance` shares with View
- JavaScript composable `useAppearance` syncs theme on page load and handles switches

**Sidebar State:**
- Cookie-based state stored as `sidebar_state` (true/false)
- Shared to frontend via Inertia props (`sidebarOpen`)
- Excluded from encryption in `bootstrap/app.php`

---

*Architecture analysis: 2026-03-12*
