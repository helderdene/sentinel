<?php

use App\Contracts\BfpSyncServiceInterface;
use App\Services\StubBfpSyncService;
use Illuminate\Support\Facades\Log;

it('implements BfpSyncServiceInterface', function () {
    $service = new StubBfpSyncService;

    expect($service)->toBeInstanceOf(BfpSyncServiceInterface::class);
});

it('resolves BfpSyncServiceInterface from container', function () {
    $service = app(BfpSyncServiceInterface::class);

    expect($service)->toBeInstanceOf(BfpSyncServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubBfpSyncService::class);
});

it('pushFireIncident returns array with required keys', function () {
    $service = new StubBfpSyncService;
    $result = $service->pushFireIncident(makeFireIncidentData());

    expect($result)->toHaveKeys(['status', 'reference_id', 'payload']);
});

it('pushFireIncident returns synced status', function () {
    $service = new StubBfpSyncService;
    $result = $service->pushFireIncident(makeFireIncidentData());

    expect($result['status'])->toBe('synced');
});

it('pushFireIncident returns reference_id with BFP-STUB prefix', function () {
    $service = new StubBfpSyncService;
    $result = $service->pushFireIncident(makeFireIncidentData());

    expect($result['reference_id'])->toStartWith('BFP-STUB-');
});

it('pushFireIncident payload contains fire_alarm_level', function () {
    $service = new StubBfpSyncService;
    $result = $service->pushFireIncident(makeFireIncidentData());

    expect($result['payload'])->toHaveKey('fire_alarm_level');
});

it('pushFireIncident logs via Log::info', function () {
    Log::spy();

    $service = new StubBfpSyncService;
    $service->pushFireIncident(makeFireIncidentData());

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubBfpSyncService::pushFireIncident')
        ->once();
});

it('parseInboundFireIncident returns normalized array with required keys', function () {
    $service = new StubBfpSyncService;
    $result = $service->parseInboundFireIncident(makeBfpWebhookPayload());

    expect($result)->toHaveKeys(['type', 'location', 'severity', 'source_reference', 'description', 'coordinates']);
});

it('parseInboundFireIncident logs via Log::info', function () {
    Log::spy();

    $service = new StubBfpSyncService;
    $service->parseInboundFireIncident(makeBfpWebhookPayload());

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubBfpSyncService::parseInboundFireIncident')
        ->once();
});

it('parseInboundFireIncident maps BFP alarm level to severity', function () {
    $service = new StubBfpSyncService;
    $result = $service->parseInboundFireIncident(makeBfpWebhookPayload(['alarm_level' => 3]));

    expect($result['severity'])->toBeString()
        ->and($result['severity'])->not->toBeEmpty();
});

/**
 * Helper to create fire incident data for push.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function makeFireIncidentData(array $overrides = []): array
{
    return array_merge([
        'incident_no' => 'INC-2026-00001',
        'incident_type' => 'Structure Fire',
        'location_text' => 'Libertad, Butuan City',
        'barangay' => 'Libertad',
        'coordinates' => null,
        'priority' => 'P1',
        'status' => 'resolved',
        'created_at' => '2026-03-13T08:00:00+08:00',
    ], $overrides);
}

/**
 * Helper to create BFP inbound webhook payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function makeBfpWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'reference_no' => 'BFP-2026-12345',
        'alarm_level' => 2,
        'location' => 'Barangay Baan, Butuan City',
        'description' => 'Residential fire reported',
        'latitude' => 8.9475,
        'longitude' => 125.5406,
    ], $overrides);
}
