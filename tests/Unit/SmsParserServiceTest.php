<?php

use App\Contracts\SmsParserServiceInterface;
use App\Services\SmsParserService;

it('implements SmsParserServiceInterface', function () {
    $service = new SmsParserService;

    expect($service)->toBeInstanceOf(SmsParserServiceInterface::class);
});

it('classify returns correct shape', function () {
    $service = new SmsParserService;
    $result = $service->classify('sunog sa libertad');

    expect($result)->toHaveKeys(['incident_type_code', 'matched_keyword']);
});

it('classify matches Filipino keyword sunog to FIR-001', function () {
    $service = new SmsParserService;
    $result = $service->classify('sunog sa merkado');

    expect($result['incident_type_code'])->toBe('FIR-001')
        ->and($result['matched_keyword'])->toBe('sunog');
});

it('classify matches Filipino keyword baha to NAT-002', function () {
    $service = new SmsParserService;
    $result = $service->classify('baha dito sa barangay');

    expect($result['incident_type_code'])->toBe('NAT-002')
        ->and($result['matched_keyword'])->toBe('baha');
});

it('classify returns default code for unrecognized messages', function () {
    $service = new SmsParserService;
    $result = $service->classify('tulungan nyo kami please');

    expect($result['incident_type_code'])->toBe('PUB-001')
        ->and($result['matched_keyword'])->toBeNull();
});

it('extractLocation finds "sa" pattern', function () {
    $service = new SmsParserService;
    $location = $service->extractLocation('sunog sa merkado');

    expect($location)->toBe('merkado');
});

it('extractLocation finds "at" pattern', function () {
    $service = new SmsParserService;
    $location = $service->extractLocation('fire at downtown');

    expect($location)->toBe('downtown');
});

it('extractLocation finds "dito sa" pattern', function () {
    $service = new SmsParserService;
    $location = $service->extractLocation('baha dito sa barangay');

    expect($location)->toBe('barangay');
});

it('extractLocation returns null for no location', function () {
    $service = new SmsParserService;
    $location = $service->extractLocation('emergency help needed');

    expect($location)->toBeNull();
});

it('parsePayload normalizes sender, message, timestamp', function () {
    $service = new SmsParserService;
    $result = $service->parsePayload([
        'sender' => '09171234567',
        'message' => 'sunog sa merkado',
        'timestamp' => '2026-03-13T08:00:00+08:00',
    ]);

    expect($result)->toHaveKeys(['sender', 'message', 'timestamp'])
        ->and($result['sender'])->toBe('09171234567')
        ->and($result['message'])->toBe('sunog sa merkado')
        ->and($result['timestamp'])->toBe('2026-03-13T08:00:00+08:00');
});

it('parsePayload handles alternative key names', function () {
    $service = new SmsParserService;
    $result = $service->parsePayload([
        'from' => '09181234567',
        'body' => 'help needed',
    ]);

    expect($result['sender'])->toBe('09181234567')
        ->and($result['message'])->toBe('help needed');
});
