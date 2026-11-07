<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Actions\Rows\ChunkedRows;
use Lomkit\Rest\Actions\Rows\KeyChunks;
use Lomkit\Rest\Actions\Rows\NoRows;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Query\QueryBuilderFactory;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\KeysGrammar;

final readonly class DispatchAction
{
    public function __construct(
        private OperateRequest $request,
        private Action $action,
        private ActionFields $fields,
    ) {}

    public function dispatch(): int
    {
        return $this->request
            ->context()
            ->text($this->request->resource)
            ->run($this->run(...));
    }

    private function run(): int
    {
        $rows = $this->rows();
        $checks = RowChecks::of($this->request, $this->action);

        $checks?->verify($rows->each(...));

        $runner = ActionRunner::for($this->request, $this->action, $this->fields, ! $rows instanceof EngineTargets, $checks);
        $impacted = $rows->each($runner->run(...));

        $runner->finish();

        return $impacted;
    }

    private function rows(): ActionRows
    {
        $payload = $this->request->payload();

        return match ($this->action->target) {
            ActionTarget::Search => EngineTargets::of($this->request, $this->action) ?? $this->search($payload->search()),
            ActionTarget::Resources => KeyChunks::of($this->query(SearchNode::empty()), $this->id(), $payload->keys()->keys, $this->action->chunkCount),
            ActionTarget::None => new NoRows,
        };
    }

    private function search(SearchNode $search): ActionRows
    {
        $query = $this->query($search);

        if ($search->sorts === [] && $search->limit === null) {
            return new ChunkedRows($query->reorder(), $this->action->chunkCount);
        }

        return KeyChunks::read($query, $this->id(), $this->action->chunkCount);
    }

    /** @return Builder<Model> */
    private function query(SearchNode $search): Builder
    {
        $resource = $this->request->resource;

        return QueryBuilderFactory::make($resource)->search(SearchPlanner::all($resource, $search, $this->request->context()->text($resource)));
    }

    private function id(): ID
    {
        return KeysGrammar::idOf($this->request->resource);
    }
}
