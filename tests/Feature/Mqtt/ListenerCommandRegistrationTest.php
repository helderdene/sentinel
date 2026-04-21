<?php

use Illuminate\Support\Facades\Artisan;

pest()->group('mqtt');

it('registers irms:mqtt-listen with Artisan', function () {
    expect(array_keys(Artisan::all()))->toContain('irms:mqtt-listen');
});

it('registers irms:mqtt-listener-watchdog with Artisan', function () {
    expect(array_keys(Artisan::all()))->toContain('irms:mqtt-listener-watchdog');
});

it('lists both MQTT commands in `php artisan list` output', function () {
    $exitCode = Artisan::call('list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('irms:mqtt-listen');
    expect($output)->toContain('irms:mqtt-listener-watchdog');
});
