<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Query\QueryBuilderFactory;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;

class DispatchAction
{
    /**
     * The request instance.
     *
     * @var OperateRequest
     */
    protected $request;

    /**
     * The action instance.
     *
     * @var Action
     */
    protected $action;

    private readonly ?EngineTargets $engineTargets;

    private readonly ActionRunner $runner;

    /**
     * Create a new action dispatcher instance.
     *
     * @return void
     */
    public function __construct(OperateRequest $request, Action $action, ActionFields $fields)
    {
        $this->request = $request;
        $this->action = $action;
        $this->engineTargets = EngineTargets::of($request, $action);
        $this->runner = ActionRunner::for($request, $action, $fields, $this->engineTargets === null);
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
        $modelsImpacted = match ($this->action->target) {
            ActionTarget::Search => $this->handleClassic($chunkCount),
            ActionTarget::Resources => $this->handleTargeted($chunkCount),
            ActionTarget::None => $this->handleStandalone(),
        };

        $this->runner->finish();

        return $modelsImpacted;
    }

    /**
     * Processes models in chunks using classic mode and dispatches an action for each set.
     *
     * The impacted models are whatever the request's search resolves, which is every model when
     * the search is omitted.
     *
     * @param  int  $chunkCount  The number of models to process per chunk.
     * @return int The number of models dispatched.
     */
    public function handleClassic(int $chunkCount)
    {
        if ($this->engineTargets !== null) {
            return $this->engineTargets->dispatch($this->runner->run(...));
        }

        $query = $this->modelsQuery($this->request->payload()->search());

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
        $keys = $this->request->payload()->keys();

        return $this->dispatchInChunks($keys->constrain($this->modelsQuery(SearchNode::empty())), $chunkCount);
    }

    /** @return Builder<Model> */
    protected function modelsQuery(SearchNode $search): Builder
    {
        return QueryBuilderFactory::make($this->request->resource)->search(SearchPlanner::all($this->request->resource, $search, $this->request->context()->text($this->request->resource)));
    }

    /**
     * Processes the given query in chunks and dispatches an action for each set.
     *
     * The count is accumulated as the chunks are dispatched rather than re-queried afterwards,
     * because an action that mutates a column the query filters on would no longer match.
     *
     * @param  Builder  $query  The query resolving the models to act on.
     * @param  int  $chunkCount  The number of models to process per chunk.
     * @return int The number of models dispatched.
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
                    $collection = Collection::make($chunk);

                    // This is to remove for Laravel 12, chunking with limit does not work
                    // in Laravel 11
                    if (! is_null($limit) && $page * $chunkCount >= $limit) {
                        $impacted += $this->runner->run($collection->take($limit - ($page - 1) * $chunkCount));

                        return false;
                    }

                    $impacted += $this->runner->run($collection);
                }
            );

        return $impacted;
    }

    /**
     * Dispatch the given standalone action.
     *
     * @return int
     */
    public function handleStandalone()
    {
        return $this->runner->run(Collection::make());
    }
}
