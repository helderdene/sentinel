<?php

use App\Models\IncidentOutcome;
use Database\Seeders\IncidentOutcomeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(IncidentOutcomeSeeder::class);
});

it('returns medical-only outcomes (plus False Alarm) for medical-adjacent categories', function (string $category) {
    $codes = IncidentOutcome::forCategory($category)->pluck('code')->all();

    expect($codes)->toContain(
        'TREATED_ON_SCENE',
        'TRANSPORTED_TO_HOSPITAL',
        'REFUSED_TREATMENT',
        'DECLARED_DOA',
        'FALSE_ALARM',
    );
    expect($codes)->not->toContain('SUBJECT_DETAINED', 'SITUATION_RESOLVED');
})->with(['Medical', 'Fire', 'Vehicular', 'Water Rescue', 'Hazmat']);

it('returns crime-specific outcomes (plus universal) for Crime / Security', function () {
    $codes = IncidentOutcome::forCategory('Crime / Security')->pluck('code')->all();

    expect($codes)->toContain(
        'SUBJECT_DETAINED',
        'SUBJECT_NOT_LOCATED',
        'SUBJECT_FLED',
        'MISMATCH',
        'HANDOFF_TO_AGENCY',
        'FALSE_ALARM',
    );
    expect($codes)->not->toContain('TREATED_ON_SCENE', 'TRANSPORTED_TO_HOSPITAL');
});

it('returns generic outcomes for non-medical, non-crime categories', function (string $category) {
    $codes = IncidentOutcome::forCategory($category)->pluck('code')->all();

    expect($codes)->toContain('SITUATION_RESOLVED', 'HANDOFF_TO_AGENCY', 'FALSE_ALARM');
    expect($codes)->not->toContain('TREATED_ON_SCENE', 'SUBJECT_DETAINED');
})->with(['Public Disturbance', 'Natural Disaster', 'Other']);

it('returns the full active set when category is unknown or null', function (?string $category) {
    $codes = IncidentOutcome::forCategory($category)->pluck('code')->all();

    expect($codes)->toContain(
        'TREATED_ON_SCENE',
        'SUBJECT_DETAINED',
        'SITUATION_RESOLVED',
        'FALSE_ALARM',
    );
})->with([null, '']);

it('always places universal outcomes (False Alarm) last in the option list', function () {
    foreach (['Medical', 'Crime / Security', 'Public Disturbance', null] as $category) {
        $outcomes = IncidentOutcome::forCategory($category);
        expect($outcomes->last()->code)->toBe('FALSE_ALARM');
    }
});

it('excludes inactive outcomes from forCategory', function () {
    IncidentOutcome::query()->where('code', 'SUBJECT_DETAINED')->update(['is_active' => false]);

    $codes = IncidentOutcome::forCategory('Crime / Security')->pluck('code')->all();

    expect($codes)->not->toContain('SUBJECT_DETAINED');
});

it('requiresVitals only flags TreatedOnScene and TransportedToHospital from seed data', function () {
    $treated = IncidentOutcome::query()->where('code', 'TREATED_ON_SCENE')->first();
    $transported = IncidentOutcome::query()->where('code', 'TRANSPORTED_TO_HOSPITAL')->first();
    $detained = IncidentOutcome::query()->where('code', 'SUBJECT_DETAINED')->first();
    $falseAlarm = IncidentOutcome::query()->where('code', 'FALSE_ALARM')->first();

    expect($treated->requiresVitals())->toBeTrue();
    expect($transported->requiresVitals())->toBeTrue();
    expect($detained->requiresVitals())->toBeFalse();
    expect($falseAlarm->requiresVitals())->toBeFalse();
});
