# Coding Conventions

**Analysis Date:** 2026-03-12

## Naming Patterns

**Files:**
- Controllers: PascalCase with `Controller` suffix - `ProfileController.php`, `PasswordController.php` (in `app/Http/Controllers/`)
- Models: PascalCase singular - `User.php` (in `app/Models/`)
- Requests: PascalCase with `Request` suffix - `ProfileUpdateRequest.php` (in `app/Http/Requests/`)
- Traits/Concerns: PascalCase - `ProfileValidationRules.php`, `PasswordValidationRules.php` (in `app/Concerns/`)
- Actions: PascalCase - `CreateNewUser.php` (in `app/Actions/`)
- Factories: PascalCase with `Factory` suffix - `UserFactory.php` (in `database/factories/`)
- Migrations: snake_case with timestamp prefix - `0001_01_01_000000_create_users_table.php`
- Test files: PascalCase with `Test` suffix - `ProfileUpdateTest.php`, `RegistrationTest.php` (in `tests/Feature/` or `tests/Unit/`)

**Functions:**
- camelCase for method names - `profileRules()`, `nameRules()`, `emailRules()`, `create()`, `show()`, `update()`, `destroy()`
- Private/protected methods use leading underscore convention implicitly through visibility modifiers: `protected function configureDefaults(): void`

**Variables:**
- camelCase for local variables and properties - `$user`, `$userId`, `$fillable`, `$hidden`
- Descriptive names: `$mustVerifyEmail`, `$email_verified_at`, `$two_factor_secret`

**Types:**
- TitleCase for custom types: `User`, `Controller`, `ProfileUpdateRequest`, `CreateNewUser`
- Enum keys would be TitleCase (per guidelines) though not heavily used yet

## Code Style

**Formatting:**
- Prettier: Configured in `.prettierrc` with:
  - Semicolons: enabled (`"semi": true`)
  - Single quotes: enabled (`"singleQuote": true`)
  - Print width: 80 characters
  - Tab width: 4 spaces (8 for YAML files at 2)
  - Plugin: prettier-plugin-tailwindcss for CSS class sorting

**Linting:**
- ESLint: Configured in `eslint.config.js` for TypeScript/Vue with:
  - Vue: Essential + Typescript recommended
  - Curly braces required for all control statements: `['error', 'all']`
  - 1TBS brace style enforced
  - Import ordering enforced: builtin → external → internal → parent → sibling → index
  - Type imports preferred as separate imports
  - @stylistic/padding-line-between-statements enforces blank lines around control statements (if, return, for, while, do, switch, try, throw)
- Pint: PHP formatter using `"preset": "laravel"` in `pint.json`

## Import Organization

**Order (TypeScript/JavaScript):**
1. Builtin modules
2. External packages
3. Internal modules (@/ aliases)
4. Parent directory imports
5. Sibling imports
6. Index imports

**Path Aliases:**
- `@/` prefix for internal imports (handled by Vite and Wayfinder)
- `@/actions/` for Laravel controller route functions (Wayfinder)
- `@/routes/` for named route functions (Wayfinder)

**PHP Import Order:**
- Namespace declaration at top
- Use statements grouped: framework classes, then application classes, organized alphabetically within groups
- Example from `ProfileController.php`:
  ```php
  use App\Http\Controllers\Controller;
  use App\Http\Requests\Settings\ProfileDeleteRequest;
  use App\Http\Requests\Settings\ProfileUpdateRequest;
  use Illuminate\Contracts\Auth\MustVerifyEmail;
  use Illuminate\Http\RedirectResponse;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\Auth;
  use Inertia\Inertia;
  use Inertia\Response;
  ```

## Error Handling

**Patterns:**
- Laravel validation: FormRequest classes with `rules()` method returning array of rules
- Example from `ProfileUpdateRequest.php`:
  ```php
  public function rules(): array
  {
      return $this->profileRules($this->user()->id);
  }
  ```
- Shared validation rules extracted to Traits in `app/Concerns/` for reusability
- Controllers delegate validation to FormRequest objects, receiving pre-validated data via `$request->validated()`
- Password validation uses Laravel's `Password::default()` rule which varies by environment (production requires min 12, mixed case, letters, numbers, symbols, uncompromised)

## Logging

**Framework:** No explicit logging patterns observed yet; follows Laravel defaults using `Illuminate\Support\Facades\Log` if needed

**Patterns:**
- Standard Laravel approach with `Log::info()`, `Log::error()` etc. (not yet implemented in codebase)

## Comments

**When to Comment:**
- PHPDoc blocks required above classes and public methods
- Prefer code clarity over comments; only comment exceptional or complex logic
- No inline comments within method bodies observed

**PHPDoc/TSDoc:**
- PHPDoc blocks for methods with `@param`, `@return` type declarations
- Example from `User.php`:
  ```php
  /**
   * The attributes that are mass assignable.
   *
   * @var list<string>
   */
  ```
- Generic type annotations in PHPDoc: `@var list<string>`, `@return array<string, mixed>`

## Function Design

**Size:**
- Methods are short and focused; largest ~40 lines in controllers
- Example: `ProfileController::update()` is 10 lines, `ProfileController::destroy()` is 14 lines

**Parameters:**
- Methods accept typed parameters - `ProfileController::update(ProfileUpdateRequest $request): RedirectResponse`
- Type hints on all parameters
- Request objects used instead of raw data

**Return Values:**
- Explicit return types declared: `: Response`, `: RedirectResponse`, `: array<string, mixed>`
- Response objects from Inertia render calls
- Redirect responses from update/delete operations

## Module Design

**Exports:**
- PHP classes follow PSR-4 autoloading; no explicit export statements
- Classes export their primary functionality through public methods
- Controllers export via routing; Models export via relations and factories

**Barrel Files:**
- Not used in PHP codebase (not applicable to Laravel structure)
- TypeScript components/utils would follow similar patterns in `resources/js/`

## PHPDoc Array Type Definitions

- Complex array shapes documented with precise type hints
- Example from `ProfileValidationRules.php`:
  ```php
  /**
   * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
   */
  protected function profileRules(?int $userId = null): array
  ```
- Array type definitions distinguish between associative arrays (shape arrays) and indexed arrays
- Union types used for validation rules: `Rule|array<mixed>|string`

## Constructor Patterns

- PHP 8 constructor property promotion used: `public function __construct(public GitHub $github) { }`
- Visible through service provider dependencies when used
- Currently minimal complex dependency injection; Services like User model rely on Eloquent

---

*Convention analysis: 2026-03-12*
