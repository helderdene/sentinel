<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentType;

/**
 * Generate a valid HMAC signature for a given payload and timestamp.
 */
function generateIoTSignature(array $payload, int $timestamp, string $secret = 'test-iot-secret-key'): string
{
    $body = json_encode($payload);

    return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
}

/**
 * Build a standard IoT sensor payload.
 *
 * @return array{sensor_type: string, sensor_id: string, value: float, threshold: float, location_text: string|null, latitude: float|null, longitude: float|null}
 */
function iotPayload(string $sensorType = 'flood_gauge', array $overrides = []): array
{
    return array_merge([
        'sensor_type' => $sensorType,
        'sensor_id' => 'SENSOR-001',
        'value' => 5.2,
        'threshold' => 4.0,
        'location_text' => 'Agusan River, Butuan City',
        'latitude' => 8.9475,
        'longitude' => 125.5406,
    ], $overrides);
}

it('creates PENDING incident from valid flood_gauge payload with P2 priority', function () {
    $floodType = IncidentType::factory()->create(['code' => 'NAT-002', 'default_priority' => 'P2']);
    $payload = iotPayload('flood_gauge');
    $timestamp = time();
    $signature = generateIoTSignature($payload, $timestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $timestamp,
    ])
        ->assertCreated()
        ->assertJsonStructure(['incident_no', 'incident_id']);

    $incident = Incident::first();
    expect($incident->status)->toBe(IncidentStatus::Pending);
    expect($incident->channel)->toBe(IncidentChannel::IoT);
    expect($incident->priority->value)->toBe('P2');
    expect($incident->incident_type_id)->toBe($floodType->id);
});

it('creates P1 incident from valid fire_alarm payload', function () {
    $fireType = IncidentType::factory()->create(['code' => 'FIR-001', 'default_priority' => 'P1']);
    $payload = iotPayload('fire_alarm');
    $timestamp = time();
    $signature = generateIoTSignature($payload, $timestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $timestamp,
    ])->assertCreated();

    $incident = Incident::first();
    expect($incident->priority->value)->toBe('P1');
    expect($incident->incident_type_id)->toBe($fireType->id);
});

it('returns 401 when HMAC signature header is missing', function () {
    $payload = iotPayload();
    $timestamp = time();

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Timestamp' => (string) $timestamp,
    ])->assertUnauthorized();
});

it('returns 401 when HMAC signature is invalid', function () {
    $payload = iotPayload();
    $timestamp = time();

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => 'sha256=invalidsignature',
        'X-Timestamp' => (string) $timestamp,
    ])->assertUnauthorized();
});

it('returns 401 when timestamp is stale (>5 minutes)', function () {
    $payload = iotPayload();
    $staleTimestamp = time() - 301;
    $signature = generateIoTSignature($payload, $staleTimestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $staleTimestamp,
    ])->assertUnauthorized();
});

it('returns 422 for unknown sensor_type', function () {
    $payload = iotPayload('unknown_sensor');
    $timestamp = time();
    $signature = generateIoTSignature($payload, $timestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $timestamp,
    ])->assertUnprocessable();
});

it('includes sensor details in incident notes', function () {
    IncidentType::factory()->create(['code' => 'NAT-002']);
    $payload = iotPayload('flood_gauge', [
        'sensor_id' => 'FLOOD-42',
        'value' => 6.8,
        'threshold' => 4.0,
    ]);
    $timestamp = time();
    $signature = generateIoTSignature($payload, $timestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $timestamp,
    ])->assertCreated();

    $incident = Incident::first();
    expect($incident->notes)->toContain('IoT Alert');
    expect($incident->notes)->toContain('flood_gauge');
    expect($incident->notes)->toContain('FLOOD-42');
    expect($incident->notes)->toContain('6.8');
    expect($incident->notes)->toContain('4');
});

it('preserves original JSON payload in raw_message', function () {
    IncidentType::factory()->create(['code' => 'NAT-002']);
    $payload = iotPayload('flood_gauge');
    $timestamp = time();
    $signature = generateIoTSignature($payload, $timestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $timestamp,
    ])->assertCreated();

    $incident = Incident::first();
    $decoded = json_decode($incident->raw_message, true);
    expect($decoded['sensor_type'])->toBe('flood_gauge');
    expect($decoded['sensor_id'])->toBe('SENSOR-001');
});

it('creates incident timeline entry with IoT source', function () {
    IncidentType::factory()->create(['code' => 'NAT-002']);
    $payload = iotPayload('flood_gauge');
    $timestamp = time();
    $signature = generateIoTSignature($payload, $timestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $timestamp,
    ])->assertCreated();

    $incident = Incident::first();
    $timeline = $incident->timeline()->first();
    expect($timeline)->not->toBeNull();
    expect($timeline->event_type)->toBe('incident_created');
    expect($timeline->event_data['source'])->toBe('iot_sensor');
    expect($timeline->event_data['sensor_type'])->toBe('flood_gauge');
});

it('sets location_text from payload', function () {
    IncidentType::factory()->create(['code' => 'NAT-002']);
    $payload = iotPayload('flood_gauge', ['location_text' => 'Agusan River Bridge']);
    $timestamp = time();
    $signature = generateIoTSignature($payload, $timestamp);

    $this->postJson('/webhooks/iot-sensor', $payload, [
        'X-Signature-256' => $signature,
        'X-Timestamp' => (string) $timestamp,
    ])->assertCreated();

    $incident = Incident::first();
    expect($incident->location_text)->toBe('Agusan River Bridge');
});
