<?php

use App\Enums\IncidentStatus;

it('has a Triaged case with value TRIAGED', function () {
    $triaged = IncidentStatus::Triaged;

    expect($triaged->value)->toBe('TRIAGED');
});

it('orders Triaged after Pending and before Dispatched', function () {
    $cases = array_map(fn (IncidentStatus $status) => $status->name, IncidentStatus::cases());

    $pendingIndex = array_search('Pending', $cases);
    $triagedIndex = array_search('Triaged', $cases);
    $dispatchedIndex = array_search('Dispatched', $cases);

    expect($triagedIndex)->toBeGreaterThan($pendingIndex)
        ->and($triagedIndex)->toBeLessThan($dispatchedIndex);
});
