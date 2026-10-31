<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Query\EngineSearch;

final readonly class EngineTargets
{
    /** @param list<list<int|string>> $chunks */
    private function __construct(
        private EngineSearch $search,
        private OperateRequest $request,
        private array $chunks,
    ) {}

    public static function of(OperateRequest $request, Action $action): ?self
    {
        if ($action->isStandalone() || $action->isTargeted()) {
            return null;
        }

        $text = $request->context()->text($request->resource);

        if (! $text->usesEngine()) {
            return null;
        }

        $search = new EngineSearch($request->resource, $text);
        $limit = $request->input('search.limit');
        $keys = (new EngineTargetKeys($search->query($request), $action->chunkCount, $action->maxResources, is_numeric($limit) ? (int) $limit : null))->resolve();

        return new self($search, $request, array_chunk($keys, max(1, $action->chunkCount)));
    }

    /** @param Closure(Collection<int, Model>): void $dispatch */
    public function dispatch(Closure $dispatch): int
    {
        $impacted = 0;

        foreach ($this->chunks as $keys) {
            $models = $this->search->rows($keys, $this->request);
            $impacted += $models->count();

            $dispatch($models);
        }

        return $impacted;
    }
}
