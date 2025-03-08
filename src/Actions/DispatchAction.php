<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Contracts\BatchableAction;
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
     * The pending batch instance.
     *
     * @var \Illuminate\Bus\PendingBatch|null
     */
    protected $batchJob;

    /**
     * Create a new action dispatcher instance.
     *
     * @param OperateRequest $request
     * @param Action         $action
     * @param array          $fields
     *
     * @return void
     */
    public function __construct(OperateRequest $request, \Lomkit\Rest\Actions\Action $action, array $fields)
    {
        $this->request = $request;
        $this->action = $action;
        $this->fields = $fields;

        if ($action instanceof BatchableAction) {
            $this->configureBatchJob($action, $fields);
        }
    }

    /**
     * Configure the batch job for the action.
     *
     * @param \Lomkit\Rest\Actions\Action $action
     * @param array                       $fields
     *
     * @return void
     */
    protected function configureBatchJob(\Lomkit\Rest\Actions\Action $action, array $fields)
    {
        $batch = Bus::batch([]);
        $batch->name($action->uriKey());

        if (!is_null($connection = $this->connection())) {
            $batch->onConnection($connection);
        }
        if (!is_null($queue = $this->queue())) {
            $batch->onQueue($queue);
        }

        $action->withBatch($fields, $batch);
        $this->batchJob = $batch;
    }

    /**
     * Dispatch the action.
     *
     * @throws \Throwable
     *
     * @return $this
     */
    public function dispatch($chunkCount)
    {
        if ($this->action->isStandalone()) {
            $modelsImpacted = $this->handleStandalone();
        } else {
            $modelsImpacted = $this->handleClassic($chunkCount);
        }

        if (!is_null($this->batchJob)) {
            $this->batchJob->dispatch();
        }

        return $modelsImpacted;
    }

    /**
     * Processes models in chunks using classic mode and dispatches an action for each set.
     *
     * The method builds a search query for the resource associated with the current request, applying
     * search criteria from the request input and removing any default result limits. It then processes
     * the query results in chunks (of size $chunkCount) by invoking the forModels method on each chunk.
     * Finally, it returns the query limit if one is set; otherwise, it returns the total count of models.
     *
     * @param int $chunkCount The number of models to process per chunk.
     *
     * @return int The effective result limit if set, or the total count of models.
     */
    public function handleClassic(int $chunkCount)
    {
        /**
         * @var Builder $searchQuery
         */
        $searchQuery =
            app()->make(QueryBuilder::class, ['resource' => $this->request->resource, 'query' => null])
                ->disableDefaultLimit()
                ->search($this->request->input('search', []));

        $limit = $searchQuery->toBase()->limit;

        $searchQuery
            ->clone()
            ->chunk(
                $chunkCount,
                function ($chunk, $page) use ($limit, $chunkCount) {
                    $collection = \Illuminate\Database\Eloquent\Collection::make($chunk);

                    // This is to remove for Laravel 12, chunking with limit does not work
                    // in Laravel 11
                    if ($page * $chunkCount >= $limit) {
                        $collection = $collection->take($limit - ($page - 1) * $chunkCount);
                        $this->forModels($collection);
                        return false;
                    }

                    return $this->forModels($collection);
                }
            );

        return $limit ?? $searchQuery->count();
    }

    /**
     * Dispatch the given standalone action.
     *
     * @return int
     */
    public function handleStandalone()
    {
        $this->forModels(
            \Illuminate\Database\Eloquent\Collection::make()
        );

        return 0;
    }

    /**
     * Dispatch the given action.
     *
     * @param Collection $models
     *
     * @return mixed|void
     */
    public function forModels(Collection $models)
    {
        if ($models->isEmpty() && !$this->action->isStandalone()) {
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
     * @param \Illuminate\Support\Collection $models
     *
     * @throws \Throwable
     *
     * @return mixed
     */
    protected function dispatchSynchronouslyForCollection(Collection $models)
    {
        return DB::transaction(function () use ($models) {
            return $this->action->handle(
                collect($this->fields)->mapWithKeys(function ($field) {return [$field['name'] => $field['value']]; })->toArray(),
                $models
            );
        });
    }

    /**
     * Dispatch the given action to the queue for a model collection.
     *
     * @param string                         $method
     * @param \Illuminate\Support\Collection $models
     *
     * @throws \Throwable
     *
     * @return $this
     */
    protected function addQueuedActionJob(Collection $models): self
    {
        $job = new CallRestApiAction($this->action, $this->fields, $models);

        if ($this->action instanceof BatchableAction) {
            $this->batchJob->add([$job]);
        } else {
            Queue::connection($this->connection())->pushOn(
                $this->queue(),
                $job
            );
        }

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
