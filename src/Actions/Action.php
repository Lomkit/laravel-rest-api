<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Fieldable;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Metable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;

class Action implements \JsonSerializable
{
    use Makeable;
    use Metable;
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Fieldable;
    use Resourcable;

    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public $connection;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name;

    /**
     * Indicates if the action can be run with no model.
     *
     * @var bool
     */
    public $standalone = false;

    /**
     * The number of models that should be included in each chunk.
     *
     * @var int
     */
    public $chunkCount = 100;

    /**
     * Get the name of the action.
     *
     * @return string
     */
    public function name()
    {
        return $this->name ?: Str::of(class_basename(get_class($this)))->beforeLast('Action')->snake(' ')->title()->toString();
    }

    /**
     * Get the URI key for the action.
     *
     * @return string
     */
    public function uriKey()
    {
        return Str::slug($this->name(), '-', null);
    }

    /**
     * Mark the action as a standalone action.
     *
     * @return $this
     */
    public function standalone()
    {
        $this->standalone = true;

        return $this;
    }

    /**
     * Determine if the action is a standalone action.
     *
     * @return bool
     */
    public function isStandalone()
    {
        return $this->standalone;
    }

    /**
     * Prepare the action for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $request = app()->make(RestRequest::class);

        return [
            'name'       => $this->name(),
            'uriKey'     => $this->uriKey(),
            'fields'     => $this->fields($request),
            'meta'       => $this->meta(),
            'standalone' => $this->isStandalone(),
        ];
    }

    /**
     * Perform the action on the given models.
     *
     * @param array                          $fields
     * @param \Illuminate\Support\Collection $models
     *
     * @return mixed
     */
    public function handle(array $fields, \Illuminate\Support\Collection $models)
    {
        //
    }

    /**
     * Register callbacks on the pending batch.
     *
     * @param array                        $fields
     * @param \Illuminate\Bus\PendingBatch $batch
     *
     * @return void
     */
    public function withBatch(array $fields, PendingBatch $batch)
    {
        //
    }

    /**
     * Execute the action for the given request.
     *
     * @param OperateRequest $request
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function handleRequest(OperateRequest $request)
    {
        $fields = $request->resolveFields($this);

        $dispatcher = new DispatchAction($request, $this, $fields);

        $count = $dispatcher->dispatch($this->chunkCount);

        return $count;
    }
}
