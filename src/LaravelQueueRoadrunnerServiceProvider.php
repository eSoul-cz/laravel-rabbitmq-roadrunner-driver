<?php

declare(strict_types=1);

namespace Esoul\LaravelRoadrunnerQueueDriver;

use Esoul\LaravelRoadrunnerQueueDriver\Console\ConsumeCommand;
use Esoul\LaravelRoadrunnerQueueDriver\Queue\Connectors\RabbitMQConnector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\QueueManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelQueueRoadrunnerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void {
        $package
            ->name('laravel-rabbitmq-roadrunner-driver')
            ->hasConfigFile('rabbitmq')
            ->hasCommand(ConsumeCommand::class);
    }

    public function packageRegistered(): void {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(
                'rabbitmq.consumer',
                function () {
                    $isDownForMaintenance = function () {
                        return $this->app->isDownForMaintenance();
                    };

                    return new Consumer(
                        $this->app->get('queue'),
                        $this->app->get('events'),
                        $this->app->get(ExceptionHandler::class),
                        $isDownForMaintenance,
                    );
                },
            );

            $this->app->singleton(
                ConsumeCommand::class,
                static function ($app) {
                    return new ConsumeCommand(
                        $app->get('rabbitmq.consumer'),
                        $app->get('cache.store'),
                    );
                },
            );
        }
    }

    /**
     * Register the application's event listeners.
     */
    public function packageBooted(): void {
        /** @var QueueManager $queue */
        $queue = $this->app->get('queue');

        $queue->addConnector(
            'rabbitmq',
            function () {
                return new RabbitMQConnector($this->app->get('events'));
            },
        );
    }
}
