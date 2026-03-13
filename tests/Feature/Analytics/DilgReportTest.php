<?php

use App\Events\IncidentCreated;
use App\Jobs\GenerateDilgMonthlyReport;
use App\Models\Barangay;
use App\Models\GeneratedReport;
use App\Models\Incident;
use App\Models\IncidentType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
    ]);
    Storage::fake('local');
});

it('creates a GeneratedReport record with type dilg_monthly and correct period', function () {
    $month = CarbonImmutable::create(2026, 2, 1);
    $type = IncidentType::factory()->create();
    $barangay = Barangay::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'barangay_id' => $barangay->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => $month->copy()->addDays(5),
        'resolved_at' => $month->copy()->addDays(5)->addHour(),
    ]);

    $job = new GenerateDilgMonthlyReport($month);
    $job->handle();

    $report = GeneratedReport::where('type', 'dilg_monthly')
        ->where('period', '2026-02')
        ->first();

    expect($report)->not->toBeNull();
    expect($report->status)->toBe('ready');
    expect($report->title)->toContain('February 2026');
});

it('generates a PDF file in storage', function () {
    $month = CarbonImmutable::create(2026, 1, 1);
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => $month->copy()->addDays(3),
        'resolved_at' => $month->copy()->addDays(3)->addHour(),
    ]);

    $job = new GenerateDilgMonthlyReport($month);
    $job->handle();

    Storage::disk('local')->assertExists('reports/dilg/2026-01.pdf');
});

it('generates a CSV file in storage', function () {
    $month = CarbonImmutable::create(2026, 1, 1);
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => $month->copy()->addDays(3),
        'resolved_at' => $month->copy()->addDays(3)->addHour(),
    ]);

    $job = new GenerateDilgMonthlyReport($month);
    $job->handle();

    Storage::disk('local')->assertExists('reports/dilg/2026-01.csv');
});

it('skips generation if report for same period already exists with status ready', function () {
    $month = CarbonImmutable::create(2026, 3, 1);

    GeneratedReport::factory()->create([
        'type' => 'dilg_monthly',
        'period' => '2026-03',
        'status' => 'ready',
    ]);

    $job = new GenerateDilgMonthlyReport($month);
    $job->handle();

    expect(GeneratedReport::where('type', 'dilg_monthly')->where('period', '2026-03')->count())->toBe(1);
});

it('aggregates incidents correctly by type and priority', function () {
    $month = CarbonImmutable::create(2026, 2, 1);
    $typeA = IncidentType::factory()->create(['name' => 'Fire']);
    $typeB = IncidentType::factory()->create(['name' => 'Flood']);

    Incident::factory()->count(3)->create([
        'incident_type_id' => $typeA->id,
        'priority' => 'P1',
        'status' => 'RESOLVED',
        'outcome' => 'TREATED_ON_SCENE',
        'created_at' => $month->copy()->addDays(2),
        'resolved_at' => $month->copy()->addDays(2)->addHour(),
    ]);

    Incident::factory()->count(2)->create([
        'incident_type_id' => $typeB->id,
        'priority' => 'P2',
        'status' => 'RESOLVED',
        'outcome' => 'FALSE_ALARM',
        'created_at' => $month->copy()->addDays(5),
        'resolved_at' => $month->copy()->addDays(5)->addHour(),
    ]);

    // Create one outside the month range -- should NOT be included
    Incident::factory()->create([
        'incident_type_id' => $typeA->id,
        'created_at' => $month->copy()->subMonth()->addDays(5),
    ]);

    $job = new GenerateDilgMonthlyReport($month);
    $job->handle();

    $report = GeneratedReport::where('type', 'dilg_monthly')
        ->where('period', '2026-02')
        ->first();

    expect($report)->not->toBeNull();
    expect($report->status)->toBe('ready');

    // Verify CSV content has correct row count (5 incidents in-range)
    $csvContent = Storage::disk('local')->get($report->csv_path);
    $lines = array_filter(explode("\n", trim($csvContent)));
    // Header + 5 data rows
    expect(count($lines))->toBe(6);
});
