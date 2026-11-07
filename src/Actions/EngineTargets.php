<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Query\EngineSearch;

final readonly class EngineTargets implements ActionRows
{
    /** @param list<list<int|string>> $chunks */
    private function __construct(
        private EngineSearch $search,
        private array $chunks,
    ) {}

    public static function of(OperateRequest $request, Action $action): ?self
    {
        if ($action->target !== ActionTarget::Search) {
            return null;
        }

        $text = $request->context()->text($request->resource);

        if (! $text->usesEngine()) {
            return null;
        }

        $search = $request->payload()->search();
        $engine = new EngineSearch($request->resource, $text, $search);
        $keys = (new EngineTargetKeys($engine->query(), $action->chunkCount, $action->maxResources, $search->limit))->resolve();

        return new self($engine, array_chunk($keys, max(1, $action->chunkCount)));
    }

    /** @param Closure(Collection<int, Model>): int $each */
    #[\Override]
    public function each(Closure $each): int
    {
        $impacted = 0;

        foreach ($this->chunks as $keys) {
            $impacted += $each($this->search->rows($keys));
        }

        return $impacted;
    }
}
