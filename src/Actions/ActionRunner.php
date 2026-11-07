<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Contracts\BatchableAction;

final readonly class ActionRunner
{
    /** @param array<string, mixed> $fields */
    private function __construct(
        private Action $action,
        private array $fields,
        private ?PendingBatch $batch,
    ) {}

    /** @param array<string, mixed> $fields */
    public static function for(Action $action, array $fields): self
    {
        return new self($action, $fields, $action instanceof BatchableAction ? self::batch($action, $fields) : null);
    }

    /** @param Collection<array-key, Model> $models */
    public function run(Collection $models): void
    {
        if ($models->isEmpty() && ! $this->action->isStandalone()) {
            return;
        }

        if (! $this->action instanceof ShouldQueue) {
            DB::transaction(fn () => $this->action->handle($this->fields, $models));

            return;
        }

        $job = new CallRestApiAction($this->action, $this->fields, $models);

        if ($this->batch !== null) {
            $this->batch->add([$job]);

            return;
        }

        Queue::connection($this->action->connection)->push($job, '', $this->action->queue);
    }

    public function finish(): void
    {
        $this->batch?->dispatch();
    }

    /** @param array<string, mixed> $fields */
    private static function batch(Action $action, array $fields): PendingBatch
    {
        $batch = Bus::batch([])->name($action->uriKey());

        if ($action->connection !== null) {
            $batch->onConnection($action->connection);
        }

        if ($action->queue !== null) {
            $batch->onQueue($action->queue);
        }

        $action->withBatch($fields, $batch);

        return $batch;
    }
}
