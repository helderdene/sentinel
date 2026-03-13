<?php

use App\Contracts\PnpBlotterServiceInterface;
use App\Services\StubPnpBlotterService;
use Illuminate\Support\Facades\Log;

it('implements PnpBlotterServiceInterface', function () {
    $service = new StubPnpBlotterService;

    expect($service)->toBeInstanceOf(PnpBlotterServiceInterface::class);
});

it('resolves PnpBlotterServiceInterface from container', function () {
    $service = app(PnpBlotterServiceInterface::class);

    expect($service)->toBeInstanceOf(PnpBlotterServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubPnpBlotterService::class);
});

it('createBlotterEntry returns array with required keys', function () {
    $service = new StubPnpBlotterService;
    $result = $service->createBlotterEntry(makeBlotterData());

    expect($result)->toHaveKeys(['status', 'blotter_no', 'payload']);
});

it('createBlotterEntry returns filed status', function () {
    $service = new StubPnpBlotterService;
    $result = $service->createBlotterEntry(makeBlotterData());

    expect($result['status'])->toBe('filed');
});

it('createBlotterEntry returns blotter_no with BLT-STUB prefix', function () {
    $service = new StubPnpBlotterService;
    $result = $service->createBlotterEntry(makeBlotterData());

    expect($result['blotter_no'])->toStartWith('BLT-STUB-');
});

it('createBlotterEntry payload contains 5W1H fields', function () {
    $service = new StubPnpBlotterService;
    $result = $service->createBlotterEntry(makeBlotterData());

    expect($result['payload'])->toHaveKeys(['who', 'what', 'when', 'where', 'why', 'how']);
});

it('createBlotterEntry payload where field contains Butuan City', function () {
    $service = new StubPnpBlotterService;
    $result = $service->createBlotterEntry(makeBlotterData());

    expect($result['payload']['where'])->toContain('Butuan City');
});

it('createBlotterEntry logs via Log::info', function () {
    Log::spy();

    $service = new StubPnpBlotterService;
    $service->createBlotterEntry(makeBlotterData());

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubPnpBlotterService::createBlotterEntry')
        ->once();
});

it('createBlotterEntry payload maps incident_type to what field', function () {
    $service = new StubPnpBlotterService;
    $data = makeBlotterData(['incident_type' => 'Robbery']);
    $result = $service->createBlotterEntry($data);

    expect($result['payload']['what'])->toBe('Robbery');
});

/**
 * Helper to create blotter entry data.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function makeBlotterData(array $overrides = []): array
{
    return array_merge([
        'incident_no' => 'INC-2026-00001',
        'incident_type' => 'Assault',
        'location_text' => 'Baan Km 3',
        'barangay' => 'Baan Km 3',
        'notes' => 'Stabbing incident near the market',
        'reporting_unit' => 'PATROL-01',
        'created_at' => '2026-03-13T08:00:00+08:00',
        'resolved_at' => '2026-03-13T09:00:00+08:00',
        'outcome' => 'treated_on_scene',
    ], $overrides);
}
