<?php

declare(strict_types=1);

namespace Esoul\LaravelRoadrunnerQueueDriver\Queue\Jobs;

use Esoul\LaravelRoadrunnerQueueDriver\Queue\RabbitMQQueue;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQJob extends Job implements JobContract
{
    /**
     * The JSON decoded version of "$message".
     */
    /**
     * @var array<string,mixed>
     */
    protected array $decoded;

    public function __construct(
        Container $container,
        protected RabbitMQQueue $rabbitmq,
        protected AMQPMessage $message,
        string $connectionName,
        string $queue,
    ) {
        /** @phpstan-ignore assign.propertyType */
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
        $this->decoded = $this->payload();
    }

    /**
     * {@inheritdoc}
     */
    public function getJobId() {
        return $this->getRabbitMQMessageHeaders()['rr_id'] ?? $this->decoded['id'] ?? null;
    }

    public function getName() {
        return $this->getRabbitMQMessageHeaders()['rr_job'] ?? $this->decoded['job'] ?? null;
    }

    /**
     * Get the headers from the rabbitMQ message.
     *
     * @return array<string,mixed>|null
     */
    protected function getRabbitMQMessageHeaders(): ?array {
        /** @var AMQPTable|null $headers */
        $headers = Arr::get($this->message->get_properties(), 'application_headers');

        return $headers?->getNativeData();

    }

    /**
     * {@inheritdoc}
     */
    public function getRawBody(): string {
        return $this->message->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function markAsFailed(): void {
        parent::markAsFailed();

        // We must tel rabbitMQ this Job is failed
        // The message must be rejected when the Job marked as failed, in case rabbitMQ wants to do some extra magic.
        // like: Death lettering the message to an other exchange/routing-key.
        $this->rabbitmq->reject($this);
    }

    /**
     * {@inheritdoc}
     *
     * @throws BindingResolutionException
     */
    public function delete(): void {
        parent::delete();

        // When delete is called and the Job was not failed, the message must be acknowledged.
        // This is because this is a controlled call by a developer. So the message was handled correct.
        if ( ! $this->failed) {
            $this->rabbitmq->ack($this);
        }
    }

    /**
     * Release the job back into the queue.
     *
     * @param  int  $delay
     *
     * @throws AMQPProtocolChannelException
     */
    public function release($delay = 0): void {
        parent::release();

        // Always create a new message when this Job is released
        $this->rabbitmq->laterRaw($delay, $this->message->getBody(), $this->queue, $this->attempts());

        // Releasing a Job means the message was failed to process.
        // Because this Job message is always recreated and pushed as new message -> message is correctly handled.
        // We must tell rabbitMQ this job message can be removed by acknowledging the message.
        $this->rabbitmq->ack($this);
    }

    /**
     * {@inheritdoc}
     */
    public function attempts(): int {
        if ( ! $data = $this->getRabbitMQMessageHeaders()) {
            return 1;
        }

        $laravelAttempts = (int) Arr::get($data, 'laravel.attempts', 0);

        return $laravelAttempts + 1;
    }

    /**
     * Get the underlying RabbitMQ connection.
     */
    public function getRabbitMQ(): RabbitMQQueue {
        return $this->rabbitmq;
    }

    /**
     * Get the underlying RabbitMQ message.
     */
    public function getRabbitMQMessage(): AMQPMessage {
        return $this->message;
    }

    public function fire(): void {
        $payload = $this->payload();
        $job = $this->getRabbitMQMessageHeaders()['rr_job'];

        $baseNamespace = '\\App\\';

        $parts = explode('.', $job);
        $class = $baseNamespace . implode('\\', array_map('ucfirst', $parts));

        /** @var class-string|null $payloadClass */
        $payloadClass = $payload['payloadClass'] ?? null;
        if (is_string($payloadClass) && class_exists($payloadClass) && isset($payload['data'])) {
            $payload = self::unserialize($payloadClass, $payload['data']);
        }

        ($this->instance = $this->resolve($class))->handle($this, $payload);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $payloadClass
     * @param  array<string,mixed>  $data
     * @return T|null
     */
    protected static function unserialize(string $payloadClass, array $data): ?object {
        return RabbitMQQueue::getSerializer()->denormalize($data, $payloadClass, 'json');
    }
}
