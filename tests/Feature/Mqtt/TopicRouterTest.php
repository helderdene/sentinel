<?php

use App\Mqtt\Handlers\AckHandler;
use App\Mqtt\Handlers\HeartbeatHandler;
use App\Mqtt\Handlers\OnlineOfflineHandler;
use App\Mqtt\Handlers\RecognitionHandler;
use App\Mqtt\TopicRouter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

pest()->group('mqtt');

it('routes Rec topic to RecognitionHandler', function () {
    $mock = $this->mock(RecognitionHandler::class, fn (MockInterface $m) => $m->shouldReceive('handle')->once());
    app()->instance(RecognitionHandler::class, $mock);

    app(TopicRouter::class)->dispatch('mqtt/face/CAM01/Rec', '{}');
});

it('routes Ack topic to AckHandler', function () {
    $mock = $this->mock(AckHandler::class, fn (MockInterface $m) => $m->shouldReceive('handle')->once());
    app()->instance(AckHandler::class, $mock);

    app(TopicRouter::class)->dispatch('mqtt/face/CAM01/Ack', '{}');
});

it('routes basic topic to OnlineOfflineHandler', function () {
    $mock = $this->mock(OnlineOfflineHandler::class, fn (MockInterface $m) => $m->shouldReceive('handle')->once());
    app()->instance(OnlineOfflineHandler::class, $mock);

    app(TopicRouter::class)->dispatch('mqtt/face/basic', '{}');
});

it('routes heartbeat topic to HeartbeatHandler', function () {
    $mock = $this->mock(HeartbeatHandler::class, fn (MockInterface $m) => $m->shouldReceive('handle')->once());
    app()->instance(HeartbeatHandler::class, $mock);

    app(TopicRouter::class)->dispatch('mqtt/face/heartbeat', '{}');
});

it('logs a warning for unmatched topics on the mqtt channel', function () {
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('warning')
        ->once()
        ->with('Unmatched MQTT topic', Mockery::on(fn ($ctx) => $ctx['topic'] === 'mqtt/face/CAM01/Unknown'));
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    app(TopicRouter::class)->dispatch('mqtt/face/CAM01/Unknown', '{}');
});

it('bumps the liveness cache key on any matched dispatch', function () {
    $mock = $this->mock(RecognitionHandler::class, fn (MockInterface $m) => $m->shouldReceive('handle')->once());
    app()->instance(RecognitionHandler::class, $mock);

    Cache::forget('mqtt:listener:last_message_received_at');

    app(TopicRouter::class)->dispatch('mqtt/face/CAM01/Rec', '{}');

    expect(Cache::has('mqtt:listener:last_message_received_at'))->toBeTrue();
});

it('bumps the liveness cache key even on unmatched dispatch (D-05 intent)', function () {
    // Any arriving message proves broker connectivity — even unknown topics
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('warning')->once();
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    Cache::forget('mqtt:listener:last_message_received_at');

    app(TopicRouter::class)->dispatch('mqtt/face/CAM01/Unknown', '{}');

    expect(Cache::has('mqtt:listener:last_message_received_at'))->toBeTrue();
});
