# Testing Patterns

**Analysis Date:** 2026-03-12

## Test Framework

**Runner:**
- Pest 4 (v4.4+)
- Config: `phpunit.xml` (PHPUnit configuration with Pest integration)
- Pest extends TestCase to provide fluent assertion API

**Assertion Library:**
- Pest's built-in `expect()` function for assertions
- Laravel testing assertions via TestCase: `$response->assertOk()`, `$response->assertRedirect()`, etc.
- Custom extension example in `tests/Pest.php`:
  ```php
  expect()->extend('toBeOne', function () {
      return $this->toBe(1);
  });
  ```

**Run Commands:**
```bash
php artisan test                    # Run all tests
php artisan test --compact          # Run with compact output
php artisan test --filter=testName  # Run specific test by name
php artisan test --coverage         # Run with code coverage
```

## Test File Organization

**Location:**
- Feature tests: `tests/Feature/` - HTTP layer tests, database interactions
- Unit tests: `tests/Unit/` - Business logic, isolated unit tests
- Organized by domain: `tests/Feature/Auth/`, `tests/Feature/Settings/`, etc.

**Naming:**
- Feature tests: `*Test.php` suffix - `AuthenticationTest.php`, `ProfileUpdateTest.php`
- Test functions: snake_case with descriptive names - `test('users can authenticate using the login screen', function () { ... })`

**Structure:**
```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── AuthenticationTest.php
│   │   ├── RegistrationTest.php
│   │   ├── PasswordResetTest.php
│   │   ├── EmailVerificationTest.php
│   │   ├── TwoFactorChallengeTest.php
│   │   ├── PasswordConfirmationTest.php
│   │   └── VerificationNotificationTest.php
│   ├── Settings/
│   │   ├── ProfileUpdateTest.php
│   │   ├── PasswordUpdateTest.php
│   │   └── TwoFactorAuthenticationTest.php
│   ├── DashboardTest.php
│   └── ExampleTest.php
├── Unit/
│   └── ExampleTest.php
├── TestCase.php
└── Pest.php
```

## Test Structure

**Suite Organization:**

Tests use Pest's closure-based syntax with automatic database refresh:

```php
<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});
```

From `tests/Pest.php`:
```php
pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
```

**Patterns:**

**Setup pattern (beforeEach):**
```php
// tests/Feature/Auth/EmailVerificationTest.php
beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::emailVerification());
});
```

Used to:
- Skip tests if feature flags disabled (Laravel Fortify)
- Configure test environment before each test runs
- Set up shared state (Fortify features, session state)

**Assertion pattern:**
- HTTP assertions: `$response->assertOk()`, `$response->assertRedirect()`, `$response->assertSessionHasNoErrors()`
- Authentication assertions: `$this->assertAuthenticated()`, `$this->assertGuest()`
- Model assertions: `expect($user->name)->toBe('Test User')`, `expect($user->fresh())->toBeNull()`
- Chaining: `$response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'))`

**Teardown pattern:**
- RefreshDatabase trait automatically rolls back after each test
- No explicit teardown needed; database state isolated per test

## Mocking

**Framework:**
- Pest/PHPUnit uses Laravel's Facades for faking:
  - `Notification::fake()` - captures and asserts sent notifications
  - `Event::fake()` - captures and asserts dispatched events
  - `RateLimiter` manipulation for testing rate limits

**Patterns:**

**Notification faking:**
```php
// tests/Feature/Auth/PasswordResetTest.php
test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});
```

**Event faking:**
```php
// tests/Feature/Auth/EmailVerificationTest.php
test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    // ... perform verification ...

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
```

**Rate limiter manipulation:**
```php
// tests/Feature/Auth/AuthenticationTest.php
test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
```

**What to Mock:**
- External notifications (email verification, password reset)
- Events (user verified events)
- Rate limiting state
- Feature flags via `skipUnlessFortifyFeature()` helper

**What NOT to Mock:**
- Database models - use factories instead
- HTTP responses - test actual responses
- Request validation - test actual validation logic
- Authentication/authorization - use `actingAs()` for logged-in users

## Fixtures and Factories

**Test Data:**

Factories defined in `database/factories/`:
```php
// database/factories/UserFactory.php
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }
}
```

**Factory States:**
```php
// Create unverified user
$user = User::factory()->unverified()->create();

// Create user with two-factor auth
$user = User::factory()->withTwoFactor()->create();

// Create with custom attributes
$user = User::factory()->create([
    'name' => 'Custom Name',
    'email' => 'custom@example.com',
]);
```

**Location:**
- Factories: `database/factories/`
- Seeders: `database/seeders/`
- Test-specific fixtures: Not used; factories preferred

## Coverage

**Requirements:** Not enforced yet (no minimum coverage set)

**View Coverage:**
```bash
php artisan test --coverage
```

Configuration in `phpunit.xml`:
```xml
<source>
    <include>
        <directory>app</directory>
    </include>
</source>
```

## Test Types

**Feature Tests (primary focus):**
- Location: `tests/Feature/`
- Database access: Enabled (RefreshDatabase trait)
- Scope: HTTP requests, controllers, form requests, authentication, database interactions
- Example: `tests/Feature/Auth/AuthenticationTest.php` tests login workflow end-to-end
- Typical structure:
  1. Set up user with factory
  2. Make HTTP request (POST, GET, PATCH, DELETE via test client)
  3. Assert response status, redirects, session state
  4. Assert database state (model attributes, model existence)

**Unit Tests (minimal so far):**
- Location: `tests/Unit/`
- Database access: Disabled by default
- Scope: Isolated business logic, helpers
- Currently minimal usage; examples exist but mostly feature tests

**E2E Tests:**
- Not used; feature tests provide sufficient coverage for HTTP layer

## Common Patterns

**Async Testing:**
- Not heavily used; synchronous request testing via test client
- Queue testing possible via `Queue::fake()` if async jobs needed

**Error Testing:**
```php
// tests/Feature/Settings/ProfileUpdateTest.php
test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
```

**Authentication in Tests:**
```php
// Use actingAs() for logged-in context
$user = User::factory()->create();

$response = $this
    ->actingAs($user)
    ->get(route('profile.edit'));

// Check authentication state
$this->assertAuthenticated();
$this->assertGuest();
```

**Session Testing:**
```php
// Test with pre-set session
$this->actingAs($user)
    ->withSession(['auth.password_confirmed_at' => time()])
    ->get(route('two-factor.show'))
    ->assertOk();

// Assert session values after request
$response->assertSessionHasNoErrors();
```

**Inertia Component Testing:**
```php
// tests/Feature/Settings/TwoFactorAuthenticationTest.php
$this->actingAs($user)
    ->withSession(['auth.password_confirmed_at' => time()])
    ->get(route('two-factor.show'))
    ->assertInertia(fn (Assert $page) => $page
        ->component('settings/TwoFactor')
        ->where('twoFactorEnabled', false),
    );
```

## Test Traits and Helpers

**Custom TestCase:**
- `Tests\TestCase` extends Laravel's `TestCase`
- Helper method: `skipUnlessFortifyFeature()` - skips tests if Laravel Fortify feature disabled
  ```php
  protected function skipUnlessFortifyFeature(string $feature, ?string $message = null): void
  {
      if (! Features::enabled($feature)) {
          $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
      }
  }
  ```

**Pest Configuration:**
- `tests/Pest.php` extends all Feature tests with `TestCase` and `RefreshDatabase`
- Custom expectations can be added via `expect()->extend()`

---

*Testing analysis: 2026-03-12*
