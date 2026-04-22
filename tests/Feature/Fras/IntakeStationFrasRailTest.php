<?php

use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

pest()->group('fras');

it('renders IntakeStation with recentFrasEvents prop carrying shaped payload', function () {
    $operator = User::factory()->operator()->create();

    $camera = Camera::factory()->create(['camera_id_display' => 'CAM-07']);
    $personnel = Personnel::factory()->create([
        'name' => 'Maria Santos',
        'category' => PersonnelCategory::Block,
    ]);

    // Eligible rail events (Critical + Warning).
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Critical,
        'similarity' => 0.88,
    ]);
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Warning,
        'similarity' => 0.60,
    ]);

    // Excluded: Info severity never reaches the rail.
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Info,
        'similarity' => 0.40,
    ]);

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/IntakeStation')
            ->has('recentFrasEvents', 2)
            ->has('recentFrasEvents.0.event_id')
            ->has('recentFrasEvents.0.severity')
            ->has('recentFrasEvents.0.camera_label')
            ->has('recentFrasEvents.0.personnel_name')
            ->has('recentFrasEvents.0.personnel_category')
            ->has('recentFrasEvents.0.confidence')
            ->has('recentFrasEvents.0.captured_at')
            ->has('recentFrasEvents.0.incident_id')
            ->has('recentFrasEvents.0.face_image_path')
        );
});

it('renders recentFrasEvents as empty array when no recognition events exist', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/IntakeStation')
            ->has('recentFrasEvents', 0)
            ->has('frasConfig.pulseDurationSeconds')
        );
});

it('shares frasConfig Inertia prop with pulseDurationSeconds', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('frasConfig.pulseDurationSeconds')
        );
});
