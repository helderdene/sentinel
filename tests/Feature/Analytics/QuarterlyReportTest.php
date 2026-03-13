<?php

use App\Events\IncidentCreated;
use App\Jobs\GenerateQuarterlyReport;
use App\Models\Barangay;
use App\Models\GeneratedReport;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
    ]);
    Storage::fake('local');
});

it('creates a GeneratedReport record with type quarterly and correct period', function () {
    $type = IncidentType::factory()->create();
    $barangay = Barangay::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'barangay_id' => $barangay->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => '2026-02-15',
        'resolved_at' => '2026-02-15 01:00:00',
    ]);

    $job = new GenerateQuarterlyReport('Q1-2026');
    app()->call([$job, 'handle']);

    $report = GeneratedReport::where('type', 'quarterly')
        ->where('period', 'Q1-2026')
        ->first();

    expect($report)->not->toBeNull();
    expect($report->status)->toBe('ready');
    expect($report->title)->toContain('Q1 2026');
});

it('generates a quarterly PDF file in storage', function () {
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => '2026-01-15',
        'resolved_at' => '2026-01-15 01:00:00',
    ]);

    $job = new GenerateQuarterlyReport('Q1-2026');
    app()->call([$job, 'handle']);

    Storage::disk('local')->assertExists('reports/quarterly/Q1-2026.pdf');
});

it('computes comparison to previous quarter', function () {
    $type = IncidentType::factory()->create();

    // Current quarter incidents (Q1 2026: Jan-Mar)
    Incident::factory()->count(5)->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'TREATED_ON_SCENE',
        'created_at' => '2026-02-10',
        'resolved_at' => '2026-02-10 01:00:00',
    ]);

    // Previous quarter incidents (Q4 2025: Oct-Dec)
    Incident::factory()->count(3)->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => '2025-11-10',
        'resolved_at' => '2025-11-10 01:00:00',
    ]);

    $job = new GenerateQuarterlyReport('Q1-2026');
    app()->call([$job, 'handle']);

    $report = GeneratedReport::where('type', 'quarterly')
        ->where('period', 'Q1-2026')
        ->first();

    expect($report)->not->toBeNull();
    expect($report->status)->toBe('ready');
});

it('skips generation if quarterly report already exists with status ready', function () {
    GeneratedReport::factory()->create([
        'type' => 'quarterly',
        'period' => 'Q1-2026',
        'status' => 'ready',
    ]);

    $job = new GenerateQuarterlyReport('Q1-2026');
    app()->call([$job, 'handle']);

    expect(GeneratedReport::where('type', 'quarterly')->where('period', 'Q1-2026')->count())->toBe(1);
});

it('dispatches quarterly report from generateReport endpoint', function () {
    Queue::fake();

    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->post(route('analytics.generate-report'), [
            'type' => 'quarterly',
            'period' => 'Q2-2026',
        ])
        ->assertRedirect();

    Queue::assertPushed(GenerateQuarterlyReport::class, function ($job) {
        return $job->period === 'Q2-2026';
    });
});

it('rejects generateReport if report is already generating', function () {
    $supervisor = User::factory()->supervisor()->create();

    GeneratedReport::factory()->generating()->create([
        'type' => 'quarterly',
        'period' => 'Q1-2026',
    ]);

    $this->actingAs($supervisor)
        ->post(route('analytics.generate-report'), [
            'type' => 'quarterly',
            'period' => 'Q1-2026',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');
});
