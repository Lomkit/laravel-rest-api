<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Metable;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;

class Action implements \JsonSerializable
{
    use Makeable, Metable;

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
        return $this->name ?: Str::of(class_basename(get_class($this)))->beforeLast('Action')->snake(' ')->title();
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
            'meta' => $this->meta()
        ];
    }

    /**
     * Perform the action on the given models.
     *
     * @param  array  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(array $fields, \Illuminate\Support\Collection $models)
    {
        //
    }

    /**
     * Called in an action failed.
     *
     * @param  \Exception $exception
     * @param  array  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function failed(array $fields, Collection $models, $exception)
    {
        //
    }

    /**
     * Called in an action failed.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest $request
     * @return array
     */
    public function fields(\Lomkit\Rest\Http\Requests\RestRequest $request)
    {
        return [];
    }

    /**
     * Execute the action for the given request.
     *
     * @param  OperateRequest  $request
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