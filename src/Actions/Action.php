<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Fieldable;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Metable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;

class Action implements \JsonSerializable
{
    use Batchable;
    use Dispatchable;
    use Fieldable;
    use InteractsWithQueue;
    use Makeable;
    use Metable;
    use Queueable;
    use Resourcable;
    use SerializesModels;

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
     * Indicates if the action requires an explicit list of resource ids and prohibits a search.
     *
     * @var bool
     */
    public $targeted = false;

    /**
     * The number of models that should be included in each chunk.
     *
     * @var int
     */
    public $chunkCount = 100;

    /**
     * Each id is validated with its own existence query, so this bounds the work a single request
     * can ask for. Lower it on an action whose blast radius should stay small.
     */
    public int $maxResources = 1000;

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
     *
     * @return $this
     *
     * @throws InvalidActionStateException
     */
    public function standalone()
    {
        if ($this->targeted) {
            throw new InvalidActionStateException('An action cannot be both standalone and targeted.');
        }

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
     * Mark the action as a targeted action, requiring an explicit list of resource ids.
     *
     * The caller names the models to act on, so a search is prohibited and the action can never
     * impact every model the way an unscoped classic action does.
     *
     *
     * @return $this
     *
     * @throws InvalidActionStateException
     */
    public function targeted()
    {
        if ($this->standalone) {
            throw new InvalidActionStateException('An action cannot be both standalone and targeted.');
        }

        $this->targeted = true;

        return $this;
    }

    /**
     * Determine if the action is a targeted action.
     *
     * @return bool
     */
    public function isTargeted()
    {
        return $this->targeted;
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
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'fields' => $this->fields($request),
            'meta' => $this->meta(),
            'standalone' => $this->isStandalone(),
            'targeted' => $this->isTargeted(),
        ];
    }

    /**
     * Perform the action on the given models.
     *
     *
     * @return mixed
     */
    public function handle(array $fields, Collection $models)
    {
        //
    }

    /**
     * Register callbacks on the pending batch.
     *
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
     *
     *
     * @return int
     *
     * @throws \Throwable
     */
    public function handleRequest(OperateRequest $request)
    {
        $fields = $request->resolveFields($this);

        $dispatcher = new DispatchAction($request, $this, $fields);

        $count = $dispatcher->dispatch($this->chunkCount);

        return $count;
    }
}
