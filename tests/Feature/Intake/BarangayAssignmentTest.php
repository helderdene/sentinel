<?php

use App\Events\IncidentCreated;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\BarangaySeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([IncidentCreated::class]);
});

it('auto-assigns barangay from coordinates via PostGIS ST_Contains', function () {
    $this->seed(BarangaySeeder::class);
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'phone',
            'location_text' => 'AgaoPoblacion Area',
            'latitude' => 8.9607,
            'longitude' => 125.5599,
        ])
        ->assertRedirect();

    $incident = Incident::first();
    expect($incident->barangay_id)->not->toBeNull();

    $barangay = Barangay::find($incident->barangay_id);
    expect($barangay->name)->toBe('AgaoPoblacion');
});

it('allows manual barangay_id to override auto-assignment', function () {
    $this->seed(BarangaySeeder::class);
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $manualBarangay = Barangay::where('name', '!=', 'AgaoPoblacion')->first();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'phone',
            'location_text' => 'Some location',
            'latitude' => 8.9607,
            'longitude' => 125.5599,
            'barangay_id' => $manualBarangay->id,
        ])
        ->assertRedirect();

    $incident = Incident::first();
    expect($incident->barangay_id)->toBe($manualBarangay->id);
});

it('leaves barangay null when coordinates do not match any barangay', function () {
    $this->seed(BarangaySeeder::class);
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P3',
            'channel' => 'radio',
            'location_text' => 'Middle of ocean',
            'latitude' => 0.0,
            'longitude' => 0.0,
        ])
        ->assertRedirect();

    $incident = Incident::first();
    expect($incident->barangay_id)->toBeNull();
});
