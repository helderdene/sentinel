<?php

use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentMessage;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Str;

it('creates incident with UUID primary key', function () {
    $incident = Incident::factory()->create();

    expect(Str::isUuid($incident->id))->toBeTrue();
});

it('auto-generates incident number in INC-YYYY-NNNNN format', function () {
    $incident = Incident::factory()->create();

    expect($incident->incident_no)->toMatch('/^INC-\d{4}-\d{5}$/');
});

it('stores geography coordinates via Magellan Point cast', function () {
    $incident = Incident::factory()->create();

    $fresh = Incident::find($incident->id);
    expect($fresh->coordinates)->toBeInstanceOf(Point::class);
});

it('stores JSONB vitals', function () {
    $vitals = ['bp' => '120/80', 'hr' => 72, 'spo2' => 98, 'gcs' => 15];
    $incident = Incident::factory()->create(['vitals' => $vitals]);

    $fresh = Incident::find($incident->id);
    expect($fresh->vitals)->toEqual($vitals);
});

it('belongs to incident type', function () {
    $incident = Incident::factory()->create();

    expect($incident->incidentType)->toBeInstanceOf(IncidentType::class);
});

it('belongs to barangay when set', function () {
    $barangay = Barangay::factory()->create();
    $incident = Incident::factory()->create(['barangay_id' => $barangay->id]);

    expect($incident->barangay)->toBeInstanceOf(Barangay::class);
});

it('belongs to created_by user', function () {
    $user = User::factory()->dispatcher()->create();
    $incident = Incident::factory()->create(['created_by' => $user->id]);

    expect($incident->createdBy)->toBeInstanceOf(User::class);
    expect($incident->createdBy->id)->toBe($user->id);
});

it('has many timeline entries', function () {
    $incident = Incident::factory()->create();
    IncidentTimeline::factory()->count(3)->create(['incident_id' => $incident->id]);

    expect($incident->timeline)->toHaveCount(3);
});

it('has many messages', function () {
    $incident = Incident::factory()->create();
    IncidentMessage::factory()->count(2)->create(['incident_id' => $incident->id]);

    expect($incident->messages)->toHaveCount(2);
});
