<?php

pest()->group('mqtt');

it('composer.json parses as valid JSON', function () {
    $raw = file_get_contents(base_path('composer.json'));
    $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    expect($parsed)->toBeArray();
});

it('scripts.dev wires irms:mqtt-listen as the 6th concurrently process per D-16', function () {
    $raw = file_get_contents(base_path('composer.json'));
    expect($raw)->toContain('irms:mqtt-listen');
});

it('scripts.dev uses #f59e0b as the mqtt process color per D-16', function () {
    $raw = file_get_contents(base_path('composer.json'));
    expect($raw)->toContain('#f59e0b');
});

it('scripts.dev names list ends with ,mqtt per D-16', function () {
    $raw = file_get_contents(base_path('composer.json'));
    expect($raw)->toContain(',mqtt --kill-others');
});
