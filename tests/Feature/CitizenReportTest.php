<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentType;
use Illuminate\Support\Facades\Event;

it('creates an incident via POST /api/v1/citizen/reports with valid data', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create(['default_priority' => 'P2']);

    $response = $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => $type->id,
        'description' => 'There is a fire at the market area near the pier',
        'caller_contact' => '09171234567',
        'caller_name' => 'Juan Dela Cruz',
        'location_text' => 'Market Area, Pier',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'tracking_token',
                'type',
                'category',
                'priority',
                'status',
                'location_text',
                'description',
                'submitted_at',
            ],
        ]);

    expect($response->json('data.tracking_token'))->toHaveLength(8);
    expect($response->json('data.status'))->toBe('Received');
});

it('creates incident with channel=app and status=PENDING', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create(['default_priority' => 'P3']);

    $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => $type->id,
        'description' => 'Flooding at the intersection near the school',
        'caller_contact' => '09171234567',
    ]);

    $incident = Incident::query()->latest()->first();
    expect($incident->channel)->toBe(IncidentChannel::App);
    expect($incident->status)->toBe(IncidentStatus::Pending);
    expect($incident->tracking_token)->not->toBeNull();
    expect($incident->tracking_token)->toHaveLength(8);
});

it('fires IncidentCreated event on citizen report submission', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create(['default_priority' => 'P2']);

    $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => $type->id,
        'description' => 'Car accident at the national highway intersection',
        'caller_contact' => '09171234567',
    ]);

    Event::assertDispatched(IncidentCreated::class);
});

it('creates incident_created timeline entry with source citizen_app', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create(['default_priority' => 'P1']);

    $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => $type->id,
        'description' => 'Building collapse at the old town market area',
        'caller_contact' => '09171234567',
    ]);

    $incident = Incident::query()->latest()->first();
    $timeline = $incident->timeline()->first();
    expect($timeline->event_type)->toBe('incident_created');
    expect($timeline->event_data['source'])->toBe('citizen_app');
    expect($timeline->event_data['tracking_token'])->toBe($incident->tracking_token);
});

it('accepts barangay_id when no GPS coordinates provided', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create(['default_priority' => 'P3']);
    $barangay = Barangay::factory()->create();

    $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => $type->id,
        'description' => 'Suspicious activity near the barangay hall area',
        'caller_contact' => '09171234567',
        'barangay_id' => $barangay->id,
    ]);

    $incident = Incident::query()->latest()->first();
    expect($incident->barangay_id)->toBe($barangay->id);
});

it('requires caller_contact for citizen reports', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create();

    $response = $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => $type->id,
        'description' => 'Some emergency event happening right now',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('caller_contact');
});

it('validates incident_type_id exists', function () {
    Event::fake([IncidentCreated::class]);

    $response = $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => 99999,
        'description' => 'An emergency is happening near the city center',
        'caller_contact' => '09171234567',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('incident_type_id');
});

it('returns citizen-facing status via GET /api/v1/citizen/reports/{token}', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'tracking_token' => 'ABC12345',
        'status' => IncidentStatus::Dispatched,
    ]);

    $response = $this->getJson('/api/v1/citizen/reports/ABC12345');

    $response->assertSuccessful()
        ->assertJsonPath('data.tracking_token', 'ABC12345')
        ->assertJsonPath('data.status', 'Dispatched');

    expect($response->json('data'))->not->toHaveKey('incident_no');
});

it('returns 404 for invalid tracking token', function () {
    $this->getJson('/api/v1/citizen/reports/INVALID1')
        ->assertNotFound();
});

it('returns only public incident types plus Other Emergency', function () {
    IncidentType::factory()->create([
        'show_in_public_app' => true,
        'name' => 'Fire',
        'is_active' => true,
    ]);
    IncidentType::factory()->create([
        'show_in_public_app' => false,
        'name' => 'Earthquake',
        'is_active' => true,
    ]);
    IncidentType::factory()->create([
        'show_in_public_app' => true,
        'name' => 'Flood',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/citizen/incident-types');
    $response->assertSuccessful();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Fire', 'Flood');
    expect($names)->not->toContain('Earthquake');
});

it('always includes Other Emergency type even when show_in_public_app is false', function () {
    IncidentType::factory()->create([
        'code' => 'OTHER_EMERGENCY',
        'name' => 'Other Emergency',
        'show_in_public_app' => false,
        'is_active' => true,
    ]);
    IncidentType::factory()->create([
        'show_in_public_app' => true,
        'name' => 'Medical',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/citizen/incident-types');
    $response->assertSuccessful();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Other Emergency', 'Medical');
});

it('returns barangay id and name only without geometry', function () {
    Barangay::factory()->create(['name' => 'Agusan Pequeno']);

    $response = $this->getJson('/api/v1/citizen/barangays');
    $response->assertSuccessful();

    $barangay = $response->json('data.0');
    expect($barangay)->toHaveKeys(['id', 'name']);
    expect($barangay)->not->toHaveKey('boundary');
});

it('rate limits citizen report submissions', function () {
    Event::fake([IncidentCreated::class]);
    $type = IncidentType::factory()->create(['default_priority' => 'P3']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/citizen/reports', [
            'incident_type_id' => $type->id,
            'description' => "Emergency report number {$i} submitted for testing purposes",
            'caller_contact' => '09171234567',
        ])->assertCreated();
    }

    $this->postJson('/api/v1/citizen/reports', [
        'incident_type_id' => $type->id,
        'description' => 'This sixth report should be rate limited by the system',
        'caller_contact' => '09171234567',
    ])->assertStatus(429);
});
