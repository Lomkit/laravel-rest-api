<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
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
     * The resolved field values, keyed by field name.
     *
     * @var array<string, mixed>
     */
    protected $fields;

    /**
     * The pending batch instance.
     *
     * @var PendingBatch|null
     */
    protected $batchJob;

    /**
     * Create a new action dispatcher instance.
     *
     * @param  array<string, mixed>  $fields
     * @return void
     */
    public function __construct(OperateRequest $request, Action $action, array $fields)
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
     * @param  array<string, mixed>  $fields
     * @return void
     */
    protected function configureBatchJob(Action $action, array $fields)
    {
        $batch = Bus::batch([]);
        $batch->name($action->uriKey());

        if (! is_null($connection = $this->connection())) {
            $batch->onConnection($connection);
        }
        if (! is_null($queue = $this->queue())) {
            $batch->onQueue($queue);
        }

        $action->withBatch($fields, $batch);
        $this->batchJob = $batch;
    }

    /**
     * Dispatch the action.
     *
     *
     * @return int The number of models impacted by the action.
     *
     * @throws \Throwable
     */
    public function dispatch($chunkCount)
    {
        if ($this->action->isStandalone()) {
            $modelsImpacted = $this->handleStandalone();
        } elseif ($this->action->isTargeted()) {
            $modelsImpacted = $this->handleTargeted($chunkCount);
        } else {
            $modelsImpacted = $this->handleClassic($chunkCount);
        }

        if (! is_null($this->batchJob)) {
            $this->batchJob->dispatch();
        }

        return $modelsImpacted;
    }

    /**
     * Processes models in chunks using classic mode and dispatches an action for each set.
     *
     * The impacted models are whatever the request's search resolves, which is every model when
     * the search is omitted.
     *
     * @param  int  $chunkCount  The number of models to process per chunk.
     * @return int The effective result limit if set, or the number of models dispatched.
     */
    public function handleClassic(int $chunkCount)
    {
        $query = $this->modelsQuery($this->request->input('search', []));

        return $this->request
            ->context()
            ->text($this->request->resource)
            ->run(fn (): int => $this->dispatchInChunks($query, $chunkCount));
    }

    /**
     * Processes the models explicitly targeted by the request and dispatches an action for each set.
     *
     * The caller names the models by id, so no search parameters are accepted. The query still goes
     * through the resource search pipeline, which is what applies the resource's authorization
     * perimeter and default ordering to the targeted ids.
     *
     * @param  int  $chunkCount  The number of models to process per chunk.
     * @return int The total count of impacted models.
     */
    public function handleTargeted(int $chunkCount)
    {
        return $this->dispatchInChunks(
            $this->modelsQuery()->whereKey($this->request->input('resources', [])),
            $chunkCount
        );
    }

    /**
     * Build the query resolving the models the action applies to.
     *
     * The resource search pipeline is how the authorization perimeter and the default ordering get
     * applied, so a targeted action goes through it too, passing no search parameters.
     *
     * @param  array  $searchParameters  The search parameters to apply, empty when the caller names the models.
     * @return Builder
     */
    protected function modelsQuery(array $searchParameters = [])
    {
        return app()->make(EloquentQueryBuilder::class, ['resource' => $this->request->resource, 'query' => null])
            ->disableDefaultLimit()
            ->search($searchParameters);
    }

    /**
     * Processes the given query in chunks and dispatches an action for each set.
     *
     * The count is accumulated as the chunks are dispatched rather than re-queried afterwards,
     * because an action that mutates a column the query filters on would no longer match.
     *
     * @param  Builder  $query  The query resolving the models to act on.
     * @param  int  $chunkCount  The number of models to process per chunk.
     * @return int The effective result limit if set, or the number of models dispatched.
     */
    protected function dispatchInChunks(Builder $query, int $chunkCount)
    {
        $limit = $query->toBase()->limit;
        $impacted = 0;

        $query
            ->clone()
            ->chunk(
                $chunkCount,
                function ($chunk, $page) use ($limit, $chunkCount, &$impacted) {
                    $collection = \Illuminate\Database\Eloquent\Collection::make($chunk);

                    // This is to remove for Laravel 12, chunking with limit does not work
                    // in Laravel 11
                    if (! is_null($limit) && $page * $chunkCount >= $limit) {
                        $collection = $collection->take($limit - ($page - 1) * $chunkCount);
                        $impacted += $collection->count();
                        $this->forModels($collection);

                        return false;
                    }

                    $impacted += $collection->count();

                    $this->forModels($collection);
                }
            );

        return $limit ?? $impacted;
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
     */
    public function forModels(Collection $models): void
    {
        if ($models->isEmpty() && ! $this->action->isStandalone()) {
            return;
        }

        if ($this->action instanceof ShouldQueue) {
            $this->addQueuedActionJob($models);

            return;
        }

        $this->dispatchSynchronouslyForCollection($models);
    }

    /**
     * Dispatch the given action synchronously for a model collection.
     *
     * @throws \Throwable
     */
    protected function dispatchSynchronouslyForCollection(Collection $models): void
    {
        DB::transaction(function () use ($models) {
            $this->action->handle($this->fields, $models);
        });
    }

    /**
     * Dispatch the given action to the queue for a model collection.
     *
     * @param  string  $method
     * @return $this
     *
     * @throws \Throwable
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
