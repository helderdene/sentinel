<?php

use App\Events\IncidentCreated;
use App\Jobs\GenerateAnnualReport;
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

it('creates a GeneratedReport record with type annual and year as period', function () {
    $type = IncidentType::factory()->create();
    $barangay = Barangay::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'barangay_id' => $barangay->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => '2025-06-15',
        'resolved_at' => '2025-06-15 01:00:00',
    ]);

    $job = new GenerateAnnualReport(2025);
    app()->call([$job, 'handle']);

    $report = GeneratedReport::where('type', 'annual')
        ->where('period', '2025')
        ->first();

    expect($report)->not->toBeNull();
    expect($report->status)->toBe('ready');
    expect($report->title)->toContain('2025');
});

it('generates an annual PDF file in storage', function () {
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => '2025-03-15',
        'resolved_at' => '2025-03-15 01:00:00',
    ]);

    $job = new GenerateAnnualReport(2025);
    app()->call([$job, 'handle']);

    Storage::disk('local')->assertExists('reports/annual/2025.pdf');
});

it('computes year-over-year comparison', function () {
    $type = IncidentType::factory()->create();

    // Current year incidents
    Incident::factory()->count(10)->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'TREATED_ON_SCENE',
        'created_at' => '2025-06-15',
        'resolved_at' => '2025-06-15 01:00:00',
    ]);

    // Previous year incidents
    Incident::factory()->count(7)->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => '2024-06-15',
        'resolved_at' => '2024-06-15 01:00:00',
    ]);

    $job = new GenerateAnnualReport(2025);
    app()->call([$job, 'handle']);

    $report = GeneratedReport::where('type', 'annual')
        ->where('period', '2025')
        ->first();

    expect($report)->not->toBeNull();
    expect($report->status)->toBe('ready');
});

it('dispatches annual report from generateReport endpoint', function () {
    Queue::fake();

    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->post(route('analytics.generate-report'), [
            'type' => 'annual',
            'period' => '2025',
        ])
        ->assertRedirect();

    Queue::assertPushed(GenerateAnnualReport::class, function ($job) {
        return $job->year === 2025;
    });
});
