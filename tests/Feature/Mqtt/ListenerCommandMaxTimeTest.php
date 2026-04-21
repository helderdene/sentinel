<?php

use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Facades\MQTT;

pest()->group('mqtt');

it('exits cleanly within 2 seconds when --max-time=1 with a mocked MQTT connection (MQTT-04)', function () {
    $client = Mockery::mock(MqttClient::class);
    $client->shouldReceive('subscribe')->times(4)->andReturnNull();
    $client->shouldReceive('loop')->once()->andReturnNull();
    $client->shouldReceive('disconnect')->once()->andReturnNull();
    $client->shouldReceive('interrupt')->zeroOrMoreTimes()->andReturnNull();

    MQTT::shouldReceive('connection')->with('subscriber')->andReturn($client);

    $start = microtime(true);
    $exitCode = Artisan::call('irms:mqtt-listen', ['--max-time' => 1]);
    $elapsed = microtime(true) - $start;

    expect($exitCode)->toBe(0);
    expect($elapsed)->toBeLessThan(2.0);
});

it('subscribes to all 4 topic patterns before entering loop()', function () {
    $client = Mockery::mock(MqttClient::class);
    $client->shouldReceive('subscribe')->times(4);
    $client->shouldReceive('loop')->once();
    $client->shouldReceive('disconnect')->once();
    $client->shouldReceive('interrupt')->zeroOrMoreTimes();

    MQTT::shouldReceive('connection')->with('subscriber')->andReturn($client);

    $exitCode = Artisan::call('irms:mqtt-listen', ['--max-time' => 1]);

    expect($exitCode)->toBe(0);
});
