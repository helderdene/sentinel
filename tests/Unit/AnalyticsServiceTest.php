<?php

use App\Enums\IncidentOutcome;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('computeKpis returns correct structure with all 5 keys', function () {
    $service = new AnalyticsService;
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'dispatched_at' => now()->subMinutes(50),
        'on_scene_at' => now()->subMinutes(40),
        'resolved_at' => now(),
        'outcome' => IncidentOutcome::TreatedOnScene,
    ]);

    $result = $service->computeKpis([
        'start_date' => now()->subDay()->toDateTimeString(),
        'end_date' => now()->addDay()->toDateTimeString(),
    ]);

    expect($result)->toHaveKeys([
        'avg_response_time_min',
        'avg_scene_arrival_time_min',
        'resolution_rate',
        'unit_utilization',
        'false_alarm_rate',
    ]);
});

it('computeKpis with date range filter excludes out-of-range incidents', function () {
    $service = new AnalyticsService;
    $type = IncidentType::factory()->create();

    // In-range: resolved
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'created_at' => now()->subDays(5),
        'dispatched_at' => now()->subDays(5)->addMinutes(10),
        'on_scene_at' => now()->subDays(5)->addMinutes(20),
        'resolved_at' => now()->subDays(5)->addMinutes(60),
        'outcome' => IncidentOutcome::TreatedOnScene,
    ]);

    // Out-of-range: 30 days ago
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'created_at' => now()->subDays(30),
        'dispatched_at' => now()->subDays(30)->addMinutes(5),
        'on_scene_at' => now()->subDays(30)->addMinutes(15),
        'resolved_at' => now()->subDays(30)->addHour(),
        'outcome' => IncidentOutcome::FalseAlarm,
    ]);

    $result = $service->computeKpis([
        'start_date' => now()->subDays(7)->toDateTimeString(),
        'end_date' => now()->toDateTimeString(),
    ]);

    // Only the in-range incident should count
    expect($result['resolution_rate'])->toBe(100.0);
    expect($result['false_alarm_rate'])->toBe(0.0);
});

it('computeKpis computes correct false alarm rate', function () {
    $service = new AnalyticsService;
    $type = IncidentType::factory()->create();

    // 2 resolved: 1 false alarm, 1 treated on scene
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'outcome' => IncidentOutcome::FalseAlarm,
    ]);

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'outcome' => IncidentOutcome::TreatedOnScene,
    ]);

    $result = $service->computeKpis([
        'start_date' => now()->subDay()->toDateTimeString(),
        'end_date' => now()->addDay()->toDateTimeString(),
    ]);

    expect($result['false_alarm_rate'])->toBe(50.0);
});

it('kpiTimeSeries returns daily grouped data', function () {
    $service = new AnalyticsService;
    $type = IncidentType::factory()->create();

    // Create incidents across 2 different days
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'created_at' => now()->subDays(2),
        'dispatched_at' => now()->subDays(2)->addMinutes(10),
    ]);

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'created_at' => now()->subDay(),
        'dispatched_at' => now()->subDay()->addMinutes(5),
    ]);

    $result = $service->kpiTimeSeries('avg_response_time_min', [
        'start_date' => now()->subDays(3)->toDateTimeString(),
        'end_date' => now()->addDay()->toDateTimeString(),
    ]);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(2);

    foreach ($result as $point) {
        expect($point)->toHaveKeys(['date', 'value']);
    }
});

it('incidentDensityByBarangay includes barangays with zero incidents', function () {
    $service = new AnalyticsService;

    $brgy1 = Barangay::factory()->create();
    $brgy2 = Barangay::factory()->create();

    $type = IncidentType::factory()->create();

    // Only brgy1 has an incident
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'barangay_id' => $brgy1->id,
    ]);

    $result = $service->incidentDensityByBarangay([
        'start_date' => now()->subDay()->toDateTimeString(),
        'end_date' => now()->addDay()->toDateTimeString(),
    ]);

    expect(count($result))->toBe(2);

    $brgy1Data = collect($result)->firstWhere('barangay_id', $brgy1->id);
    $brgy2Data = collect($result)->firstWhere('barangay_id', $brgy2->id);

    expect($brgy1Data['incident_count'])->toBe(1);
    expect($brgy2Data['incident_count'])->toBe(0);
});

it('barangayDetail returns top types sorted by count', function () {
    $service = new AnalyticsService;

    $brgy = Barangay::factory()->create();
    $typeA = IncidentType::factory()->create(['name' => 'Fire']);
    $typeB = IncidentType::factory()->create(['name' => 'Medical']);

    // 3 Fire, 1 Medical
    Incident::factory()->count(3)->create([
        'incident_type_id' => $typeA->id,
        'barangay_id' => $brgy->id,
        'priority' => IncidentPriority::P1,
    ]);

    Incident::factory()->create([
        'incident_type_id' => $typeB->id,
        'barangay_id' => $brgy->id,
        'priority' => IncidentPriority::P2,
    ]);

    $result = $service->barangayDetail($brgy->id, [
        'start_date' => now()->subDay()->toDateTimeString(),
        'end_date' => now()->addDay()->toDateTimeString(),
    ]);

    expect($result)->toHaveKeys(['top_types', 'priority_breakdown']);
    expect($result['top_types'][0]['name'])->toBe('Fire');
    expect($result['top_types'][0]['count'])->toBe(3);
    expect($result['priority_breakdown']['P1'])->toBe(3);
    expect($result['priority_breakdown']['P2'])->toBe(1);
});
