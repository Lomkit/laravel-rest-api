<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Actions\Rows\ChunkedRows;
use Lomkit\Rest\Actions\Rows\ChunkSize;
use Lomkit\Rest\Actions\Rows\KeyChunks;
use Lomkit\Rest\Actions\Rows\NoRows;
use Lomkit\Rest\Actions\Rows\RowCap;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Confirmation\ConfirmationGate;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\OperateResponse;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\KeysGrammar;

final readonly class DispatchAction
{
    private const array BODY = ['search', 'resources', 'fields'];

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
        $rows = $checks?->verify($rows) ?? $rows;

        $this->confirm($rows);

        $runner = ActionRunner::for($this->request, $this->action, $this->fields, ! $rows instanceof EngineTargets, $checks);
        $impacted = $runner->through($rows);
        $batch = $runner->finish();

        $this->request->context()->events()->dispatch(ActionOperated::of($this->request->resource, $this->action, $this->fields, $impacted, $batch));

        return new OperateResponse($impacted, null, $batch);
    }

    private function confirm(ActionRows $rows): void
    {
        if (! $this->action->requiresConfirmation()) {
            return;
        }

        $count = $rows->count();

        ConfirmationGate::of($this->request, [Endpoint::Operate->value, $this->action->uriKey(), $count], self::BODY)
            ->pass(fn (): ?Confirmation => $this->action->confirmation($this->fields, $count));
    }

    private function rows(): ActionRows
    {
        $payload = $this->request->payload();

        return match ($this->action->target) {
            ActionTarget::Search => $this->search($payload->search()),
            ActionTarget::Resources => KeyChunks::of($this->query(SearchNode::empty()), $this->id(), $payload->keys()->keys, $this->size()),
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
            return ChunkedRows::of($query, $this->size(), $cap);
        }

        return KeyChunks::read($query, $this->id(), $this->size(), $search->limit, $cap);
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

    private function size(): ChunkSize
    {
        return ChunkSize::of($this->action, $this->request->context()->limits());
    }
}
