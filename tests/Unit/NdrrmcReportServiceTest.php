<?php

use App\Contracts\NdrrmcReportServiceInterface;
use App\Services\StubNdrrmcReportService;
use Illuminate\Support\Facades\Log;

it('implements NdrrmcReportServiceInterface', function () {
    $service = new StubNdrrmcReportService;

    expect($service)->toBeInstanceOf(NdrrmcReportServiceInterface::class);
});

it('resolves NdrrmcReportServiceInterface from container', function () {
    $service = app(NdrrmcReportServiceInterface::class);

    expect($service)->toBeInstanceOf(NdrrmcReportServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubNdrrmcReportService::class);
});

it('submitSitRep returns array with required keys', function () {
    $service = new StubNdrrmcReportService;
    $result = $service->submitSitRep(makeSitRepData());

    expect($result)->toHaveKeys(['status', 'reference_id', 'xml_payload']);
});

it('submitSitRep returns submitted status', function () {
    $service = new StubNdrrmcReportService;
    $result = $service->submitSitRep(makeSitRepData());

    expect($result['status'])->toBe('submitted');
});

it('submitSitRep returns reference_id with SITREP-STUB prefix', function () {
    $service = new StubNdrrmcReportService;
    $result = $service->submitSitRep(makeSitRepData());

    expect($result['reference_id'])->toStartWith('SITREP-STUB-');
});

it('submitSitRep generates valid XML payload', function () {
    $service = new StubNdrrmcReportService;
    $result = $service->submitSitRep(makeSitRepData());

    $xml = new SimpleXMLElement($result['xml_payload']);

    expect($xml->getName())->toBe('SituationReport');
});

it('submitSitRep XML contains SitRep fields', function () {
    $service = new StubNdrrmcReportService;
    $result = $service->submitSitRep(makeSitRepData());

    $xml = new SimpleXMLElement($result['xml_payload']);

    expect(isset($xml->report_number))->toBeTrue()
        ->and(isset($xml->type_of_incident))->toBeTrue()
        ->and(isset($xml->location))->toBeTrue()
        ->and(isset($xml->response_actions))->toBeTrue()
        ->and(isset($xml->resources_deployed))->toBeTrue();
});

it('submitSitRep XML contains Butuan City-specific location data', function () {
    $service = new StubNdrrmcReportService;
    $result = $service->submitSitRep(makeSitRepData());

    $xml = new SimpleXMLElement($result['xml_payload']);

    expect((string) $xml->location->municipality)->toBe('Butuan City')
        ->and((string) $xml->location->region)->toBe('Caraga (Region XIII)');
});

it('submitSitRep XML contains barangay from input', function () {
    $service = new StubNdrrmcReportService;
    $data = makeSitRepData(['barangay' => 'Libertad']);
    $result = $service->submitSitRep($data);

    $xml = new SimpleXMLElement($result['xml_payload']);

    expect((string) $xml->location->barangay)->toBe('Libertad');
});

it('submitSitRep logs via Log::info', function () {
    Log::spy();

    $service = new StubNdrrmcReportService;
    $service->submitSitRep(makeSitRepData());

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubNdrrmcReportService::submitSitRep')
        ->once();
});

it('submitSitRep XML shows Resolved status when resolved_at is set', function () {
    $service = new StubNdrrmcReportService;
    $data = makeSitRepData(['resolved_at' => '2026-03-13T10:00:00+08:00']);
    $result = $service->submitSitRep($data);

    $xml = new SimpleXMLElement($result['xml_payload']);

    expect((string) $xml->status_of_operations)->toBe('Resolved');
});

it('submitSitRep XML shows Ongoing status when resolved_at is null', function () {
    $service = new StubNdrrmcReportService;
    $data = makeSitRepData(['resolved_at' => null]);
    $result = $service->submitSitRep($data);

    $xml = new SimpleXMLElement($result['xml_payload']);

    expect((string) $xml->status_of_operations)->toBe('Ongoing');
});

/**
 * Helper to create SitRep input data.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function makeSitRepData(array $overrides = []): array
{
    return array_merge([
        'incident_no' => 'INC-2026-00001',
        'incident_type' => 'Structure Fire',
        'priority' => 'P1',
        'location_text' => 'Libertad, Butuan City',
        'barangay' => 'Libertad',
        'coordinates' => null,
        'outcome' => 'treated_on_scene',
        'units_deployed' => 3,
        'timeline_summary' => 'Fire contained within 45 minutes',
        'created_at' => '2026-03-13T08:00:00+08:00',
        'resolved_at' => '2026-03-13T09:00:00+08:00',
    ], $overrides);
}
