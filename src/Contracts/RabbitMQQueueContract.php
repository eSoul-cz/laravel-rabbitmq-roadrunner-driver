<?php

declare(strict_types=1);

namespace Esoul\LaravelRoadrunnerQueueDriver\Contracts;

use Esoul\LaravelRoadrunnerQueueDriver\Queue\QueueConfig;
use Esoul\LaravelRoadrunnerQueueDriver\Queue\RabbitMQQueue;
use PhpAmqpLib\Connection\AbstractConnection;

interface RabbitMQQueueContract
{
    public function __construct(QueueConfig $config);

    public function setConnection(AbstractConnection $connection): RabbitMQQueue;
}
