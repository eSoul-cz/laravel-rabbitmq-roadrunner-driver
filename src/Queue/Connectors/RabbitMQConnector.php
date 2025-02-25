<?php

declare(strict_types=1);

namespace Esoul\LaravelRoadrunnerQueueDriver\Queue\Connectors;

use Esoul\LaravelRoadrunnerQueueDriver\Queue\Connection\ConnectionFactory;
use Esoul\LaravelRoadrunnerQueueDriver\Queue\QueueFactory;
use Esoul\LaravelRoadrunnerQueueDriver\Queue\RabbitMQQueue;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Queue;

class RabbitMQConnector implements ConnectorInterface
{
    protected Dispatcher $dispatcher;

    public function __construct(Dispatcher $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Establish a queue connection.
     *
     * @return RabbitMQQueue
     *
     * @throws Exception
     */
    public function connect(array $config): Queue {
        $connection = ConnectionFactory::make($config);

        $queue = QueueFactory::make($config)->setConnection($connection);

        $this->dispatcher->listen(
            WorkerStopping::class,
            static function () use ($queue): void {
                $queue->close();
            }
        );

        return $queue;
    }
}
