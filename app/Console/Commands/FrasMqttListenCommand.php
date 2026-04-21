<?php

namespace App\Console\Commands;

use App\Mqtt\TopicRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

class FrasMqttListenCommand extends Command
{
    protected $signature = 'irms:mqtt-listen {--max-time=3600}';

    protected $description = 'Subscribe to camera MQTT topics and route messages to handlers';

    public function handle(TopicRouter $router): int
    {
        $mqtt = MQTT::connection('subscriber');
        $prefix = config('fras.mqtt.topic_prefix');

        $topics = [
            $prefix.'/+/Rec',
            $prefix.'/+/Ack',
            $prefix.'/basic',
            $prefix.'/heartbeat',
        ];

        foreach ($topics as $topic) {
            $mqtt->subscribe(
                $topic,
                fn (string $topic, string $message) => $router->dispatch($topic, $message),
                0,
            );
        }

        Log::channel('mqtt')->info('MQTT listener started', [
            'topics' => $topics,
            'max_time' => (int) $this->option('max-time'),
        ]);

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $mqtt->interrupt());
        pcntl_signal(SIGINT, fn () => $mqtt->interrupt());

        $maxTime = (int) $this->option('max-time');

        if ($maxTime > 0) {
            pcntl_signal(SIGALRM, fn () => $mqtt->interrupt());
            pcntl_alarm($maxTime);
        }

        $mqtt->loop(true);
        $mqtt->disconnect();

        Log::channel('mqtt')->info('MQTT listener stopped cleanly');

        return self::SUCCESS;
    }
}
