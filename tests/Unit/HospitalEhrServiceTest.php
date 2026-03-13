<?php

use App\Contracts\HospitalEhrServiceInterface;
use App\Services\StubHospitalEhrService;
use Illuminate\Support\Facades\Log;

it('resolves HospitalEhrServiceInterface from container', function () {
    $service = app(HospitalEhrServiceInterface::class);

    expect($service)->toBeInstanceOf(HospitalEhrServiceInterface::class)
        ->and($service)->toBeInstanceOf(StubHospitalEhrService::class);
});

it('preNotify returns status reference_id and fhir_payload', function () {
    $service = app(HospitalEhrServiceInterface::class);
    $result = $service->preNotify(
        hospitalId: 'bcdh',
        incidentNo: 'INC-2026-00001',
        incidentType: 'Medical Emergency',
        vitals: ['heart_rate' => 110, 'spo2' => 94],
        assessmentTags: ['Conscious', 'Breathing'],
        etaMinutes: '8',
        unitCallsign: 'AMB-01',
    );

    expect($result)->toHaveKeys(['status', 'reference_id', 'fhir_payload'])
        ->and($result['status'])->toBe('accepted')
        ->and($result['reference_id'])->toStartWith('FHIR-STUB-')
        ->and($result['fhir_payload'])->toBeArray();
});

it('fhir payload contains Patient resource', function () {
    $service = app(HospitalEhrServiceInterface::class);
    $result = $service->preNotify('bcdh', 'INC-2026-00001', 'Medical Emergency', ['heart_rate' => 110], ['Conscious'], '8', 'AMB-01');

    $entries = collect($result['fhir_payload']['entry'] ?? []);
    $patient = $entries->firstWhere('resource.resourceType', 'Patient');

    expect($patient)->not->toBeNull()
        ->and($patient['resource'])->toHaveKeys(['resourceType', 'identifier', 'name'])
        ->and($patient['resource']['resourceType'])->toBe('Patient');
});

it('fhir payload contains Encounter resource', function () {
    $service = app(HospitalEhrServiceInterface::class);
    $result = $service->preNotify('bcdh', 'INC-2026-00001', 'Medical Emergency', ['heart_rate' => 110], ['Conscious'], '8', 'AMB-01');

    $entries = collect($result['fhir_payload']['entry'] ?? []);
    $encounter = $entries->firstWhere('resource.resourceType', 'Encounter');

    expect($encounter)->not->toBeNull()
        ->and($encounter['resource'])->toHaveKeys(['resourceType', 'status', 'class', 'subject', 'serviceProvider'])
        ->and($encounter['resource']['resourceType'])->toBe('Encounter')
        ->and($encounter['resource']['status'])->toBe('planned');
});

it('fhir payload contains Observation resources for vitals', function () {
    $service = app(HospitalEhrServiceInterface::class);
    $result = $service->preNotify(
        'bcdh', 'INC-2026-00001', 'Medical Emergency',
        ['blood_pressure' => '120/80', 'heart_rate' => 110, 'spo2' => 94, 'gcs' => 15],
        ['Conscious'], '8', 'AMB-01',
    );

    $entries = collect($result['fhir_payload']['entry'] ?? []);
    $observations = $entries->where('resource.resourceType', 'Observation');

    // BP, HR, SpO2, GCS = 4 observations
    expect($observations)->toHaveCount(4);
});

it('hospital name in payload comes from config/hospitals.php', function () {
    $service = app(HospitalEhrServiceInterface::class);
    $result = $service->preNotify('bcdh', 'INC-2026-00001', 'Medical Emergency', [], [], '8', 'AMB-01');

    $entries = collect($result['fhir_payload']['entry'] ?? []);
    $encounter = $entries->firstWhere('resource.resourceType', 'Encounter');

    expect($encounter['resource']['serviceProvider']['display'])
        ->toBe('Butuan City District Hospital (BCDH)');
});

it('ehr stub logs calls via Log::info', function () {
    Log::spy();

    $service = app(HospitalEhrServiceInterface::class);
    $service->preNotify('bcdh', 'INC-2026-00001', 'Medical Emergency', [], [], '8', 'AMB-01');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'StubHospitalEhrService::preNotify')
        ->once();
});

it('reference_id is deterministic for same incident number', function () {
    $service = app(HospitalEhrServiceInterface::class);
    $result1 = $service->preNotify('bcdh', 'INC-2026-00001', 'Medical Emergency', [], [], '8', 'AMB-01');
    $result2 = $service->preNotify('bcdh', 'INC-2026-00001', 'Medical Emergency', [], [], '8', 'AMB-01');

    expect($result1['reference_id'])->toBe($result2['reference_id']);
});

it('skips observation resources for null vitals', function () {
    $service = app(HospitalEhrServiceInterface::class);
    $result = $service->preNotify('bcdh', 'INC-2026-00001', 'Medical Emergency', ['heart_rate' => 110], [], '8', 'AMB-01');

    $entries = collect($result['fhir_payload']['entry'] ?? []);
    $observations = $entries->where('resource.resourceType', 'Observation');

    // Only heart_rate provided, so only 1 observation
    expect($observations)->toHaveCount(1);
});
