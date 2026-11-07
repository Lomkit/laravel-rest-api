<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Actions\Rows\NoRows;
use Lomkit\Rest\Actions\Rows\PagedRows;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Query\QueryBuilderFactory;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;

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
            ActionTarget::Search => EngineTargets::of($this->request, $this->action) ?? new PagedRows($this->query($payload->search()), $this->action->chunkCount),
            ActionTarget::Resources => new PagedRows($payload->keys()->constrain($this->query(SearchNode::empty())), $this->action->chunkCount),
            ActionTarget::None => new NoRows,
        };
    }

    /** @return Builder<Model> */
    private function query(SearchNode $search): Builder
    {
        $resource = $this->request->resource;

        return QueryBuilderFactory::make($resource)->search(SearchPlanner::all($resource, $search, $this->request->context()->text($resource)));
    }
}
