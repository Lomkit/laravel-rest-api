<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Actions\Rows\ChunkedRows;
use Lomkit\Rest\Actions\Rows\KeyChunks;
use Lomkit\Rest\Actions\Rows\NoRows;
use Lomkit\Rest\Actions\Rows\RowCap;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\OperateResponse;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\KeysGrammar;

final readonly class DispatchAction
{
    public function __construct(
        private OperateRequest $request,
        private Action $action,
        private ActionFields $fields,
    ) {}

    public function dispatch(): OperateResponse
    {
        return $this->request
            ->context()
            ->text($this->request->resource)
            ->run($this->run(...));
    }

    private function run(): OperateResponse
    {
        $rows = $this->rows();
        $checks = RowChecks::of($this->request, $this->action);

        $checks?->verify($rows->each(...));

        $runner = ActionRunner::for($this->request, $this->action, $this->fields, ! $rows instanceof EngineTargets, $checks);
        $impacted = $runner->through($rows);

        return new OperateResponse($impacted, null, $runner->finish());
    }

    private function rows(): ActionRows
    {
        $payload = $this->request->payload();

        return match ($this->action->target) {
            ActionTarget::Search => $this->search($payload->search()),
            ActionTarget::Resources => KeyChunks::of($this->query(SearchNode::empty()), $this->id(), $payload->keys()->keys, $this->action->chunkCount),
            ActionTarget::None => new NoRows,
        };
    }

    private function search(SearchNode $search): ActionRows
    {
        $this->request->resource->searching(SearchContext::of($search));

        $engine = EngineTargets::of($this->request, $this->action);

        if ($engine !== null) {
            return $engine;
        }

        $query = $this->query($search);
        $cap = RowCap::of($this->action->maxResources);

        if ($search->sorts === [] && $search->limit === null) {
            return ChunkedRows::of($query, $this->action->chunkCount, $cap);
        }

        return KeyChunks::read($query, $this->id(), $this->action->chunkCount, $search->limit, $cap);
    }

    /** @return Builder<Model> */
    private function query(SearchNode $search): Builder
    {
        $resource = $this->request->resource;

        return SearchPlanner::all($resource, $search, $this->request->context()->text($resource))->query();
    }

    private function id(): ID
    {
        return KeysGrammar::idOf($this->request->resource);
    }
}
