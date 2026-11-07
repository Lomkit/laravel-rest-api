<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Connection;
use Illuminate\Queue\Attributes\Delay;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\ReadsQueueAttributes;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Queue\Concerns\ResolvesQueueRoutes;

use function Illuminate\Support\enum_value;

final class QueueSettings
{
    use ReadsQueueAttributes;
    use ResolvesQueueRoutes;

    public function __construct(private readonly Action $action) {}

    public function configure(QueuedAction $job): QueuedAction
    {
        $job->connection = $this->connection();
        $job->queue = $this->queue();
        $job->delay = $this->attribute(Delay::class)?->delay;
        $job->tries = $this->attribute(Tries::class)?->tries;
        $job->timeout = $this->attribute(Timeout::class)?->timeout;
        $job->backoff = $this->attribute(Backoff::class)?->backoff;
        $job->maxExceptions = $this->attribute(MaxExceptions::class)?->maxExceptions;
        $job->failOnTimeout = $this->attribute(FailOnTimeout::class) !== null;
        $job->shouldBeEncrypted = $this->action instanceof ShouldBeEncrypted;

        return $job;
    }

    public function configureBatch(PendingBatch $batch): PendingBatch
    {
        $connection = $this->connection();
        $queue = $this->queue();

        if ($connection !== null) {
            $batch->onConnection($connection);
        }

        if ($queue !== null) {
            $batch->onQueue($queue);
        }

        return $batch;
    }

    private function connection(): ?string
    {
        $connection = $this->attribute(Connection::class)->connection ?? $this->resolveConnectionFromQueueRoute($this->action);

        return is_string($connection = enum_value($connection)) ? $connection : null;
    }

    private function queue(): ?string
    {
        $queue = $this->attribute(Queue::class)->queue ?? $this->resolveQueueFromQueueRoute($this->action);

        return is_string($queue = enum_value($queue)) ? $queue : null;
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $class
     * @return TAttribute|null
     */
    private function attribute(string $class): ?object
    {
        $attribute = $this->getAttributeInstance($this->action, $class);

        return $attribute instanceof $class ? $attribute : null;
    }
}
