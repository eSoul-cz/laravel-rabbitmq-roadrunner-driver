<?php

declare(strict_types=1);

namespace Esoul\LaravelRoadrunnerQueueDriver\Queue;

use Illuminate\Support\Arr;

class QueueFactory
{
    /**
     * @param  array<string,mixed>  $config
     */
    public static function make(array $config = []): RabbitMQQueue
    {
        $queueConfig = QueueConfigFactory::make($config);
        /** @var class-string<RabbitMQQueue>|'default' $worker */
        $worker = Arr::get($config, 'worker', 'default');

        if (strtolower($worker) == 'default') {
            return new RabbitMQQueue($queueConfig);
        }

        return new $worker($queueConfig);
    }
}
