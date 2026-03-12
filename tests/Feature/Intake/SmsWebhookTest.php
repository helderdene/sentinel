<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentType;
use Illuminate\Support\Facades\Log;

it('creates Structure Fire incident from "sunog sa merkado"', function () {
    $fireType = IncidentType::factory()->create(['code' => 'FIR-001', 'default_priority' => 'P1']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09171234567',
        'message' => 'sunog sa merkado',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful()
        ->assertJsonStructure(['incident_no']);

    $incident = Incident::first();
    expect($incident->incident_type_id)->toBe($fireType->id);
    expect($incident->channel)->toBe(IncidentChannel::Sms);
    expect($incident->status)->toBe(IncidentStatus::Pending);
});

it('creates Flooding incident from "baha dito sa barangay"', function () {
    $floodType = IncidentType::factory()->create(['code' => 'NAT-002', 'default_priority' => 'P2']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09181234567',
        'message' => 'baha dito sa barangay',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->incident_type_id)->toBe($floodType->id);
});

it('creates Structure Fire incident from "fire near downtown"', function () {
    $fireType = IncidentType::factory()->create(['code' => 'FIR-001', 'default_priority' => 'P1']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09191234567',
        'message' => 'fire near downtown',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->incident_type_id)->toBe($fireType->id);
});

it('creates Medical Emergency incident from "ambulansya please"', function () {
    $medType = IncidentType::factory()->create(['code' => 'MED-001', 'default_priority' => 'P1']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09201234567',
        'message' => 'ambulansya please',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->incident_type_id)->toBe($medType->id);
});

it('creates General Emergency for unrecognized message', function () {
    $generalType = IncidentType::factory()->create(['code' => 'PUB-001', 'default_priority' => 'P3']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09211234567',
        'message' => 'tulungan nyo kami please',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->incident_type_id)->toBe($generalType->id);
});

it('preserves original SMS text in raw_message', function () {
    IncidentType::factory()->create(['code' => 'PUB-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09221234567',
        'message' => 'emergency help needed',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->raw_message)->toBe('emergency help needed');
});

it('includes sender and message in notes', function () {
    IncidentType::factory()->create(['code' => 'PUB-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09231234567',
        'message' => 'need help urgently',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->notes)->toContain('09231234567');
    expect($incident->notes)->toContain('need help urgently');
});

it('sets caller_contact to sender number', function () {
    IncidentType::factory()->create(['code' => 'PUB-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09241234567',
        'message' => 'help',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->caller_contact)->toBe('09241234567');
});

it('sends auto-reply via SMS service', function () {
    Log::spy();
    IncidentType::factory()->create(['code' => 'PUB-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09251234567',
        'message' => 'emergency',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return $message === 'StubSemaphoreSmsService::send'
                && $context['to'] === '09251234567'
                && str_contains($context['message'], 'Your emergency report has been received');
        })
        ->once();
});

it('extracts location from "sa merkado" pattern', function () {
    IncidentType::factory()->create(['code' => 'FIR-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09261234567',
        'message' => 'sunog sa merkado',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->location_text)->toBe('merkado');
});

it('returns 422 when message field is missing', function () {
    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09271234567',
        'timestamp' => now()->toIso8601String(),
    ])->assertUnprocessable();
});

it('creates incident timeline entry with SMS source', function () {
    IncidentType::factory()->create(['code' => 'FIR-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09281234567',
        'message' => 'sunog sa merkado',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    $timeline = $incident->timeline()->first();
    expect($timeline)->not->toBeNull();
    expect($timeline->event_type)->toBe('incident_created');
    expect($timeline->event_data['source'])->toBe('sms');
    expect($timeline->event_data['sender'])->toBe('09281234567');
});

it('accepts payload with "from" and "body" keys as alternatives', function () {
    IncidentType::factory()->create(['code' => 'PUB-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'from' => '09291234567',
        'body' => 'help needed',
        'timestamp' => now()->toIso8601String(),
    ])->assertSuccessful();

    $incident = Incident::first();
    expect($incident->caller_contact)->toBe('09291234567');
    expect($incident->raw_message)->toBe('help needed');
});
