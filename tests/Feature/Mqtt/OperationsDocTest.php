<?php

pest()->group('mqtt');

beforeEach(function () {
    $this->doc = base_path('docs/operations/irms-mqtt.md');
});

it('docs/operations/irms-mqtt.md exists', function () {
    expect(file_exists($this->doc))->toBeTrue();
});

it('documents the [program:irms-mqtt] Supervisor block (Pitfall 6)', function () {
    expect(file_get_contents($this->doc))->toContain('[program:irms-mqtt]');
});

it('documents supervisorctl restart irms-mqtt:* in the deploy protocol (Pitfall 7)', function () {
    expect(file_get_contents($this->doc))->toContain('supervisorctl restart irms-mqtt:*');
});

it('includes a smoke test runbook section', function () {
    expect(file_get_contents($this->doc))->toContain('smoke test runbook');
});

it('documents a mosquitto_pub one-liner for smoke testing', function () {
    expect(file_get_contents($this->doc))->toContain('mosquitto_pub');
});

it('includes a Mosquitto install prerequisite for macOS or Linux', function () {
    $contents = file_get_contents($this->doc);
    $hasBrew = str_contains($contents, 'brew install mosquitto');
    $hasApt = str_contains($contents, 'apt-get install') && str_contains($contents, 'mosquitto');

    expect($hasBrew || $hasApt)->toBeTrue();
});
