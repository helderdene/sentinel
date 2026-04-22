<?php

use App\Enums\IncidentStatus;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

pest()->group('fras');

/**
 * Helper — build a fras_recognition-born incident assigned to the given
 * responder's unit. Returns the hydrated incident + RecognitionEvent pair so
 * individual tests can assert against specific fields.
 */
function seedFrasIncidentForResponder(User $responder, array $overrides = []): array
{
    $camera = Camera::factory()->create([
        'camera_id_display' => $overrides['camera_id_display'] ?? 'CAM-07',
        'name' => $overrides['camera_name'] ?? 'Rizal Park East',
    ]);

    $personnel = Personnel::factory()->create([
        'name' => $overrides['personnel_name'] ?? 'Juan Dela Cruz',
        'category' => $overrides['personnel_category'] ?? PersonnelCategory::Block,
    ]);

    $event = RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Critical,
        'similarity' => 0.88,
        'face_image_path' => 'face/test-'.uniqid().'.jpg',
        'scene_image_path' => 'scene/test-'.uniqid().'.jpg',
    ]);

    $type = IncidentType::factory()->create(['code' => 'person_of_interest']);

    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Dispatched,
    ]);

    IncidentTimeline::query()->create([
        'incident_id' => $incident->id,
        'event_type' => 'incident_created',
        'event_data' => [
            'source' => 'fras_recognition',
            'recognition_event_id' => $event->id,
            'camera_id' => $camera->id,
            'personnel_id' => $personnel->id,
            'personnel_category' => $personnel->category->value,
            'confidence' => 0.88,
            'captured_at' => $event->captured_at->toIso8601String(),
        ],
    ]);

    // Attach the incident to the responder's unit as active.
    $incident->assignedUnits()->attach($responder->unit_id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
        'acknowledged_at' => now(),
    ]);

    return [$incident->fresh(), $event->fresh()];
}

it('hydrates person_of_interest prop with signed face URL for fras_recognition-born incident', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    [$incident, $event] = seedFrasIncidentForResponder($responder);

    $this->actingAs($responder)
        ->get(route('responder.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('responder/Station')
            ->has('incident.person_of_interest', fn ($poi) => $poi
                ->where('personnel_name', 'Juan Dela Cruz')
                ->where('personnel_category', 'block')
                ->where('camera_label', 'CAM-07')
                ->where('camera_name', 'Rizal Park East')
                ->has('face_image_url')
                ->has('captured_at')
            )
        );
});

it('never exposes scene_image_url on the incident prop or inside person_of_interest', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    [$incident] = seedFrasIncidentForResponder($responder);

    $this->actingAs($responder)
        ->get(route('responder.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('responder/Station')
            ->missing('incident.scene_image_url')
            ->missing('incident.person_of_interest.scene_image_url')
        );
});

it('sets person_of_interest to null for non-fras incidents', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'status' => IncidentStatus::Dispatched,
    ]);

    // Non-fras timeline entry (simulates a manually-created or other-source incident).
    IncidentTimeline::query()->create([
        'incident_id' => $incident->id,
        'event_type' => 'incident_created',
        'event_data' => [
            'source' => 'manual',
            'created_by' => 'operator',
        ],
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
        'acknowledged_at' => now(),
    ]);

    $this->actingAs($responder)
        ->get(route('responder.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('responder/Station')
            ->where('incident.person_of_interest', null)
        );
});

it('denies responder fetch of scene signed URL (layer 1 defense via scene controller role gate)', function () {
    Storage::fake('fras_events');

    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/responder-denied.jpg',
    ]);
    Storage::disk('fras_events')->put('scene/responder-denied.jpg', 'fake');

    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($responder)->get($url)->assertForbidden();
});

it('denies responder fetch of face signed URL per Phase 21 D-27 role gate (UI-SPEC fallback contract)', function () {
    Storage::fake('fras_events');

    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/responder-denied.jpg',
    ]);
    Storage::disk('fras_events')->put('face/responder-denied.jpg', 'fake');

    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    // The responder's ResponderController prop hydrates this URL; when the browser
    // fetches, Phase 21 FrasEventFaceController gate [Operator, Supervisor, Admin]
    // returns 403. UI-SPEC lines 520/526 mandate the Vue template swap to a
    // UserRound icon on that denial.
    $url = URL::temporarySignedRoute(
        'fras.event.face',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($responder)->get($url)->assertForbidden();
});

it('preserves D-27 role gate lock: FrasEventFaceController references exactly [Operator, Supervisor, Admin]', function () {
    // Arch-style assertion: the Face controller source must contain the three
    // operator-tier roles and must NOT reference Responder. Phase 22 does NOT
    // extend the D-27 gate; the responder's fetch is denied and the UI falls
    // back to UserRound (UI-SPEC lines 520/526).
    $source = file_get_contents(base_path('app/Http/Controllers/FrasEventFaceController.php'));

    expect($source)->toContain('UserRole::Operator');
    expect($source)->toContain('UserRole::Supervisor');
    expect($source)->toContain('UserRole::Admin');
    expect($source)->not->toContain('UserRole::Responder');

    // Sanity: the role-allow-list array is present in the expected shape.
    expect($source)->toContain('[UserRole::Operator, UserRole::Supervisor, UserRole::Admin]');
});
