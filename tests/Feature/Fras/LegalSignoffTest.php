<?php

use App\Models\FrasLegalSignoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

pest()->group('fras');

/**
 * Tests redirect the VALIDATION.md append target to a sandbox file via
 * `config('fras.signoff.validation_path')` so the real phase artifact is
 * untouched by the test suite. The command resolves the path via
 * config() with a base_path() fallback so production behavior matches the
 * plan spec ("hardcoded base_path", which the fallback provides) while
 * tests stay hermetic.
 */
beforeEach(function () {
    $sandbox = storage_path('framework/testing/22-VALIDATION.sandbox.md');
    File::ensureDirectoryExists(dirname($sandbox));
    File::put($sandbox, "# Phase 22 Validation (sandbox)\n\n## Validation Sign-Off\n\n- [ ] pending\n");

    config(['fras.signoff.validation_path' => $sandbox]);
});

afterEach(function () {
    $sandbox = storage_path('framework/testing/22-VALIDATION.sandbox.md');
    if (file_exists($sandbox)) {
        @unlink($sandbox);
    }
});

it('records a legal signoff row with the given signer and contact', function () {
    $exit = Artisan::call('fras:legal-signoff', [
        '--signed-by' => 'Atty. Cruz',
        '--contact' => 'cruz@butuan.gov.ph',
    ]);

    expect($exit)->toBe(0);

    $signoff = FrasLegalSignoff::first();

    expect($signoff)->not()->toBeNull();
    expect($signoff->signed_by_name)->toBe('Atty. Cruz');
    expect($signoff->contact)->toBe('cruz@butuan.gov.ph');
    expect($signoff->signed_at)->not()->toBeNull();
});

it('aborts non-zero when --signed-by is missing', function () {
    $exit = Artisan::call('fras:legal-signoff', [
        '--contact' => 'cruz@butuan.gov.ph',
    ]);

    expect($exit)->not()->toBe(0);
    expect(FrasLegalSignoff::count())->toBe(0);
});

it('aborts non-zero when --contact is missing', function () {
    $exit = Artisan::call('fras:legal-signoff', [
        '--signed-by' => 'Atty. Cruz',
    ]);

    expect($exit)->not()->toBe(0);
    expect(FrasLegalSignoff::count())->toBe(0);
});

it('appends a sign-off line to VALIDATION.md', function () {
    Artisan::call('fras:legal-signoff', [
        '--signed-by' => 'Atty. Cruz',
        '--contact' => 'cruz@butuan.gov.ph',
    ]);

    $content = file_get_contents(config('fras.signoff.validation_path'));

    expect($content)->toContain('CDRRMO legal sign-off recorded');
    expect($content)->toContain('DPA-07');
    expect($content)->toContain('Atty. Cruz');
});

it('persists notes when provided', function () {
    Artisan::call('fras:legal-signoff', [
        '--signed-by' => 'Atty. Cruz',
        '--contact' => 'cruz@butuan.gov.ph',
        '--notes' => 'Reviewed all 10 PIA sections',
    ]);

    $signoff = FrasLegalSignoff::first();

    expect($signoff->notes)->toBe('Reviewed all 10 PIA sections');
});
