<?php

use App\Mqtt\Handlers\AckHandler;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

pest()->group('mqtt');

it('logs ACK received on the mqtt channel with no state change', function () {
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('info')
        ->once()
        ->with('ACK received', Mockery::on(fn ($ctx) => ($ctx['topic'] ?? null) === 'mqtt/face/CAM01/Ack'));
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    app(AckHandler::class)->handle(
        'mqtt/face/CAM01/Ack',
        json_encode(['facesluiceId' => 'CAM01', 'messageId' => 'abc-123', 'status' => 'ok']),
    );
});

it('logs without error when fed the ack.json fixture', function () {
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    $json = file_get_contents(base_path('tests/fixtures/mqtt/ack.json'));

    app(AckHandler::class)->handle('mqtt/face/CAM01/Ack', $json);
});
