<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;

class DispatchAction
{
    /**
     * The request instance.
     *
     * @var RestRequest
     */
    protected $request;

    /**
     * The action instance.
     *
     * @var Action
     */
    protected $action;

    /**
     * The fields for the action.
     *
     * @var array
     */
    protected $fields;


    /**
     * Create a new action dispatcher instance.
     *
     * @param  OperateRequest  $request
     * @param  Action  $action
     * @param  array  $fields
     * @return void
     */
    public function __construct(OperateRequest $request, Action $action, array $fields)
    {
        $this->request = $request;
        $this->action = $action;
        $this->fields = $fields;
    }

    /**
     * Dispatch the action.
     *
     * @return $this
     *
     * @throws \Throwable
     */
    public function dispatch($chunkCount)
    {
        $searchQuery =
            app()->make(QueryBuilder::class, ['resource' => $this->request->resource, 'query' => null])
                ->search($this->request->input('search', []));

        $searchQuery
            ->clone()
            ->chunk(
                $chunkCount,
                function ($chunk) {
                    return $this->forModels(
                        \Illuminate\Database\Eloquent\Collection::make(
                            $chunk
                        )
                    );
                }
            );

        return $searchQuery->count();
    }

    /**
     * Dispatch the given action.
     *
     * @param Collection $models
     * @return mixed|void
     *
     * @throws \Throwable
     */
    public function forModels(Collection $models)
    {
        if ($models->isEmpty()) {
            return;
        }

        if ($this->action instanceof ShouldQueue) {
            $this->addQueuedActionJob($models);

            return;
        }

        return $this->dispatchSynchronouslyForCollection($models);
    }

    /**
     * Dispatch the given action synchronously for a model collection.
     *
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     *
     * @throws \Throwable
     */
    protected function dispatchSynchronouslyForCollection(Collection $models)
    {
        return DB::transaction(function () use ($models) {
            return $this->action->handle($this->fields, $models);
        });
    }

    /**
     * Dispatch the given action to the queue for a model collection.
     *
     * @param  string  $method
     * @param  \Illuminate\Support\Collection  $models
     * @return $this
     *
     * @throws \Throwable
     */
    protected function addQueuedActionJob( Collection $models)
    {
        $job = new CallRestApiAction(
            $this->action, $this->fields, $models
        );

        Queue::connection($this->connection())->pushOn(
            $this->queue(), $job
        );

        return $this;
    }

    /**
     * Extract the queue connection for the action.
     *
     * @return string|null
     */
    protected function connection()
    {
        return property_exists($this->action, 'connection') ? $this->action->connection : null;
    }

    /**
     * Extract the queue name for the action.
     *
     * @return string|null
     */
    protected function queue()
    {
        return property_exists($this->action, 'queue') ? $this->action->queue : null;
    }
}
