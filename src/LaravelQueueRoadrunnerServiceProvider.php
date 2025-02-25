<?php

declare(strict_types=1);

namespace Esoul\LaravelRoadrunnerQueueDriver;

use Esoul\LaravelRoadrunnerQueueDriver\Console\ConsumeCommand;
use Esoul\LaravelRoadrunnerQueueDriver\Queue\Connectors\RabbitMQConnector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

class LaravelQueueRoadrunnerServiceProvider extends ServiceProvider
{
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/rabbitmq.php', 'queue.connections.rabbitmq');

        if ($this->app->runningInConsole()) {
            $this->app->singleton(
                'rabbitmq.consumer',
                function () {
                    $isDownForMaintenance = function () {
                        return $this->app->isDownForMaintenance();
                    };

                    return new Consumer(
                        $this->app['queue'],
                        $this->app['events'],
                        $this->app[ExceptionHandler::class],
                        $isDownForMaintenance
                    );
                }
            );

            $this->app->singleton(
                ConsumeCommand::class,
                static function ($app) {
                    return new ConsumeCommand(
                        $app['rabbitmq.consumer'],
                        $app['cache.store']
                    );
                }
            );

            $this->commands(
                [
                    ConsumeCommand::class,
                ]
            );
        }

//        $this->commands(
//            [
//                Console\ExchangeDeclareCommand::class,
//                Console\ExchangeDeleteCommand::class,
//                Console\QueueBindCommand::class,
//                Console\QueueDeclareCommand::class,
//                Console\QueueDeleteCommand::class,
//                Console\QueuePurgeCommand::class,
//            ]
//        );
    }

    /**
     * Register the application's event listeners.
     */
    public function boot(): void {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector(
            'rabbitmq',
            function () {
                return new RabbitMQConnector($this->app['events']);
            }
        );
    }
}
