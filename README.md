# Laravel Queue RoadRunner Driver

Driver for connecting [RabbitMQ](https://www.rabbitmq.com/) broker and [RoadRunner](https://roadrunner.dev) compatible applications with [Laravel Queue API](https://laravel.com/docs/queues).

Forked from the open-source RabbitMQ driver: [vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq).

## Installation

### Add a repository

Don't forget to add the private composer repository to your `composer.json` file:

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://packages.wboy.cz"
    }
  ]
}
```

Or add the git repository directly:


```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/eSoul-cz/laravel-rabbitmq-roadrunner-driver"
    }
  ]
}
```

### Install using composer:

```bash
composer require esoul/laravel-rabbitmq-roadrunner-driver
```

## Configuration

You need to register the service provider in your `config/app.php` file:

```php
'providers' => [
    // ...
    \eSoul\RabbitMQ\RoadRunner\ServiceProvider::class,
],
```

Then you can add the following configuration to your `config/queue.php` file:

```php
return [
    // ...
    'default' => 'rabbitmq',
    // ...
    'connections' => [
        // ...
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'queue' => env('RABBITMQ_QUEUE', 'default'),
            'connection' => 'default',
        
            'hosts' => [
                [
                    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
                    'port' => env('RABBITMQ_PORT', 5672),
                    'user' => env('RABBITMQ_USER', 'guest'),
                    'password' => env('RABBITMQ_PASSWORD', 'guest'),
                    'vhost' => env('RABBITMQ_VHOST', '/'),
                ],
            ],
        
            'options' => [
                'queue' => [
                    // RabbitMQ queue options
                ]
            ],
        
            'worker' => env('RABBITMQ_WORKER', 'default'),
        ],
        // ...
    ],
];
```

## Creating jobs

You can create jobs using the `php artisan make:job` command:

```bash
# Creates a new job class \App\Jobs\Test
php artisan make:job Test
```

```php
<?php

namespace App\Jobs\Tasks;

use App\Tasks\Payloads\TestPayload;
use Esoul\LaravelRoadrunnerQueueDriver\Queue\Jobs\RabbitMQJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class Test implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(RabbitMQJob $job, $payload): void
    {
        // Handle the job
    }
}
```

### Handling payloads

Payloads are data associated with the job. This works a little differently from the native Laravel way. 
Payloads are serialized in the RabbitMQ message as JSON and passed to the job as the second argument of the `handle()` method unserialized as an array.

```php
// Example payload data that roadrunner might generate.
// Exact format depends on the app. 
$payload = [
    'payloadClass' => '\App\Tasks\Payloads\TestPayload',
    'data' => [
        'name' => 'Test data',
    ],
];
```

#### Payload class (DTO)

You can define a payload DTO which the driver will attempt to deserialize the payload data into.
The important thing is that the payload class must have the same name (and namespace) between applications as defined in the `payloadClass` key.

```php
<?php
declare(strict_types=1);

namespace App\Tasks\Payloads;

readonly class TestPayload
{

    public function __construct(
        public string $name,
    ){}

}
```

It is recommended to use payload classes.

### Dispatching jobs

You can dispatch jobs using the `dispatch()` helper function:

```php
dispatch(new \App\Jobs\Test());
```

Or other methods - see the [official Laravel documentation](https://laravel.com/docs/queues).

#### Declaring queues for jobs

It could be useful do define a queue for a specific job class. You can do this by calling the `onQueue()` method in the class constructor:

```php
public function __construct()
{
    $this->onQueue('roadrunner');
}
```

#### Passing a payload to a job

Payload can be defined as a property of the job class. It must be public and be called `$payload`, otherwise the driver will not register it.

```php
<?php

namespace App\Jobs\Tasks;

use App\Tasks\Payloads\TestPayload;
use Esoul\LaravelRoadrunnerQueueDriver\Queue\Jobs\RabbitMQJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Tasks\Payloads\TestPayload;

class Test implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly ?TestPayload $payload = null,
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(RabbitMQJob $job, TestPayload $payload): void
    {
        // Handle the job
    }
}

// Dispatching the job with a payload
dispatch(new Test(new TestPayload('Test data')));
```

## Running the consumer

Consuming messages is done using a long-running artisan command.

There are 2 available options:

1. `artisan queue:work` - Built-in Laravel worker (slower, but supports consuming multiple queues).
2. `artisan rabbitmq:consume` - RabbitMQ worker (faster, but supports only one queue).

## Technical details

### Job serialization

The job name is derived from the class name and namespace. The root `\App` namespace is stripped from the job name.
For example the class `App\Jobs\Namespace1\Namespace2\JobName` will be transformed to `jobs.namespace1.namespace2.jobName`.

The payload is serialized and deserialized using the [Symfony Serializer](https://symfony.com/doc/current/components/serializer.html) component.

The job metadata is passed as RabbitMQ message applications headers using the `rr_*` keys.