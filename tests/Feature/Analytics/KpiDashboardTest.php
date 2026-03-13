<?php

use App\Enums\IncidentOutcome;
use App\Enums\IncidentStatus;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;

it('dashboard page loads with kpis prop containing all 5 metric keys', function () {
    $supervisor = User::factory()->supervisor()->create();
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'dispatched_at' => now()->subMinutes(30),
        'on_scene_at' => now()->subMinutes(20),
        'resolved_at' => now(),
        'outcome' => IncidentOutcome::TreatedOnScene,
    ]);

    $this->actingAs($supervisor)
        ->get(route('analytics.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Dashboard')
            ->has('kpis')
            ->where('kpis.avg_response_time_min', fn ($v) => is_numeric($v) || $v === null)
            ->where('kpis.avg_scene_arrival_time_min', fn ($v) => is_numeric($v) || $v === null)
            ->where('kpis.resolution_rate', fn ($v) => is_numeric($v))
            ->where('kpis.unit_utilization', fn ($v) => is_numeric($v))
            ->where('kpis.false_alarm_rate', fn ($v) => is_numeric($v))
        );
});

it('dashboard page respects date range filter', function () {
    $supervisor = User::factory()->supervisor()->create();
    $type = IncidentType::factory()->create();

    // In-range incident
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'created_at' => now()->subDays(3),
        'outcome' => IncidentOutcome::TreatedOnScene,
    ]);

    // Out-of-range incident
    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Resolved,
        'created_at' => now()->subDays(60),
        'outcome' => IncidentOutcome::FalseAlarm,
    ]);

    $this->actingAs($supervisor)
        ->get(route('analytics.dashboard', ['preset' => '7d']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Dashboard')
            ->has('kpis')
            ->where('kpis.false_alarm_rate', fn ($v) => (float) $v === 0.0)
        );
});

it('dashboard page includes filterOptions with incident types and barangays', function () {
    $supervisor = User::factory()->supervisor()->create();
    IncidentType::factory()->create(['name' => 'Fire']);
    Barangay::factory()->create(['name' => 'Test Brgy']);

    $this->actingAs($supervisor)
        ->get(route('analytics.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Dashboard')
            ->has('filterOptions')
            ->has('filterOptions.incident_types', 1)
            ->has('filterOptions.barangays', 1)
        );
});
