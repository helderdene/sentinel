<?php

pest()->group('mqtt');

it('enables auto_reconnect on the subscriber connection per MQTT-04', function () {
    expect(config('mqtt-client.connections.subscriber.auto_reconnect'))->toBeTrue();
});

it('disables auto_reconnect on the publisher connection (clean_session forbids it)', function () {
    expect(config('mqtt-client.connections.publisher.auto_reconnect'))->toBeFalse();
    expect(config('mqtt-client.connections.publisher.connection_settings.auto_reconnect.enabled'))->toBeFalse();
});

it('declares subscriber and publisher as SEPARATE connection entries per MQTT-06', function () {
    $connections = config('mqtt-client.connections');

    expect($connections)->toHaveKey('subscriber');
    expect($connections)->toHaveKey('publisher');
});

it('gives subscriber and publisher distinct client ids (not shared alias)', function () {
    $subId = config('mqtt-client.connections.subscriber.client_id');
    $pubId = config('mqtt-client.connections.publisher.client_id');

    expect($subId)->not->toBe($pubId);
});

it('defaults to the subscriber connection', function () {
    expect(config('mqtt-client.default'))->toBe('subscriber');
});
