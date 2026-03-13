<?php

use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;

it('heatmap page loads with density and geojson props', function () {
    $supervisor = User::factory()->supervisor()->create();
    $brgy = Barangay::factory()->create();
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'barangay_id' => $brgy->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('analytics.heatmap'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Heatmap')
            ->has('density')
            ->has('geojson')
        );
});

it('barangay detail endpoint returns JSON with top_types and priority_breakdown', function () {
    $supervisor = User::factory()->supervisor()->create();
    $brgy = Barangay::factory()->create();
    $type = IncidentType::factory()->create(['name' => 'Fire']);

    Incident::factory()->count(3)->create([
        'incident_type_id' => $type->id,
        'barangay_id' => $brgy->id,
    ]);

    $this->actingAs($supervisor)
        ->getJson(route('analytics.barangay-detail', ['barangay' => $brgy->id]))
        ->assertOk()
        ->assertJsonStructure([
            'top_types' => [
                '*' => ['name', 'count'],
            ],
            'priority_breakdown' => ['P1', 'P2', 'P3', 'P4'],
        ]);
});

it('geojson contains features for barangays with boundaries', function () {
    $supervisor = User::factory()->supervisor()->create();
    Barangay::factory()->create();

    $this->actingAs($supervisor)
        ->get(route('analytics.heatmap'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Heatmap')
            ->where('geojson.type', 'FeatureCollection')
            ->has('geojson.features', 1)
        );
});
