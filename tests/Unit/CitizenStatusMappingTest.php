<?php

use App\Enums\IncidentStatus;
use App\Http\Resources\V1\CitizenReportResource;

it('maps all IncidentStatus values to citizen-facing labels', function () {
    $validLabels = ['Received', 'Verified', 'Dispatched', 'Resolved'];

    foreach (IncidentStatus::cases() as $status) {
        $label = CitizenReportResource::CITIZEN_STATUS_MAP[$status->value] ?? null;

        expect($label)->not->toBeNull(
            "IncidentStatus::{$status->name} ({$status->value}) is not mapped in CITIZEN_STATUS_MAP"
        );
        expect($validLabels)->toContain($label);
    }
});

it('maps PENDING to Received', function () {
    expect(CitizenReportResource::CITIZEN_STATUS_MAP['PENDING'])->toBe('Received');
});

it('maps TRIAGED to Verified', function () {
    expect(CitizenReportResource::CITIZEN_STATUS_MAP['TRIAGED'])->toBe('Verified');
});

it('maps DISPATCHED through RESOLVING to Dispatched', function () {
    $dispatchedStatuses = ['DISPATCHED', 'ACKNOWLEDGED', 'EN_ROUTE', 'ON_SCENE', 'RESOLVING'];

    foreach ($dispatchedStatuses as $status) {
        expect(CitizenReportResource::CITIZEN_STATUS_MAP[$status])->toBe('Dispatched');
    }
});

it('maps RESOLVED to Resolved', function () {
    expect(CitizenReportResource::CITIZEN_STATUS_MAP['RESOLVED'])->toBe('Resolved');
});
