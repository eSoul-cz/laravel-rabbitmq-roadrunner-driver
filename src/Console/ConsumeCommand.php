<?php

declare(strict_types=1);

namespace Esoul\LaravelRoadrunnerQueueDriver\Console;

use Esoul\LaravelRoadrunnerQueueDriver\Consumer;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Support\Str;

class ConsumeCommand extends WorkCommand
{
    // phpcs:disable Generic.Files.LineLength.TooLong
    protected $signature = 'rabbitmq:consume
                            {connection? : The name of the queue connection to work}
                            {--name=default : The name of the consumer}
                            {--queue= : The name of the queue to work. Please notice that there is no support for multiple queues}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}
                            {--rest=0 : Number of seconds to rest between jobs}

                            {--max-priority=}
                            {--consumer-tag}
                            {--prefetch-size=0}
                            {--prefetch-count=1000}
                           ';
    // phpcs:enable Generic.Files.LineLength.TooLong

    protected $description = 'Consume messages';

    public function handle(): ?int {
        /** @var Consumer $consumer */
        $consumer = $this->worker;

        $consumer->setContainer($this->laravel);

        /** @var string $name */
        $name = $this->option('name');
        $consumer->setName($name);

        $consumer->setConsumerTag($this->consumerTag());
        $consumer->setMaxPriority((int) $this->option('max-priority'));
        $consumer->setPrefetchSize((int) $this->option('prefetch-size'));
        $consumer->setPrefetchCount((int) $this->option('prefetch-count'));

        return parent::handle();
    }

    protected function consumerTag(): string {
        /** @var string|null $consumerTag */
        $consumerTag = $this->option('consumer-tag');
        if ($consumerTag) {
            return $consumerTag;
        }

        /** @var string $name */
        $name = $this->option('name');
        $consumerTag = implode('_', [
            Str::slug(config('app.name', 'laravel')),
            Str::slug($name),
            md5(serialize($this->options()) . Str::random(16) . getmypid()),
        ]);

        return Str::substr($consumerTag, 0, 255);
    }
}
