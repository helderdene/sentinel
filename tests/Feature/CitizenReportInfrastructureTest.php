<?php

use App\Models\Incident;
use App\Models\IncidentType;
use Illuminate\Database\QueryException;

it('can create an incident with a tracking_token', function () {
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'tracking_token' => 'A7F2B3K9',
    ]);

    expect($incident->tracking_token)->toBe('A7F2B3K9');
    $this->assertDatabaseHas('incidents', [
        'id' => $incident->id,
        'tracking_token' => 'A7F2B3K9',
    ]);
});

it('enforces unique tracking_token on incidents', function () {
    $type = IncidentType::factory()->create();

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'tracking_token' => 'UNIQUE01',
    ]);

    Incident::factory()->create([
        'incident_type_id' => $type->id,
        'tracking_token' => 'UNIQUE01',
    ]);
})->throws(QueryException::class);

it('can query incident types with show_in_public_app filter', function () {
    IncidentType::factory()->create(['show_in_public_app' => true, 'name' => 'Fire']);
    IncidentType::factory()->create(['show_in_public_app' => false, 'name' => 'Flood']);
    IncidentType::factory()->create(['show_in_public_app' => true, 'name' => 'Medical']);

    $publicTypes = IncidentType::query()->where('show_in_public_app', true)->get();

    expect($publicTypes)->toHaveCount(2);
    expect($publicTypes->pluck('name')->toArray())->toContain('Fire', 'Medical');
});

it('has citizen API routes registered', function () {
    $this->getJson('/api/v1/citizen/incident-types')->assertSuccessful();
    $this->getJson('/api/v1/citizen/barangays')->assertSuccessful();
});
