<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Actions\Rows\CheckedKeys;
use Lomkit\Rest\Actions\Rows\ChunkSize;
use Lomkit\Rest\Actions\Rows\RowCap;
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
        $keys = (new EngineTargetKeys($engine->query(), $action->chunkCount, RowCap::of($action->maxResources), $search->limit))->resolve();

        $size = ChunkSize::of($action, $request->context()->limits());

        return new self($engine, array_chunk($keys, $size->for(fn (): int => count($keys))));
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

    #[\Override]
    public function count(): int
    {
        return array_sum(array_map(count(...), $this->chunks));
    }

    #[\Override]
    public function only(CheckedKeys $keys): ActionRows
    {
        $chunks = array_map(fn (array $chunk): array => array_values(array_filter($chunk, $keys->has(...))), $this->chunks);

        return new self($this->search, array_values(array_filter($chunks, fn (array $chunk): bool => $chunk !== [])));
    }
}
