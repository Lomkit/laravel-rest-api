<?php

namespace Lomkit\Rest\Actions;

use Lomkit\Rest\Actions\Rows\RowCap;
use Lomkit\Rest\Text\Engine\EngineKeyWalk;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\EngineQuery;

final readonly class EngineTargetKeys
{
    public function __construct(
        private EngineQuery $query,
        private int $chunkCount,
        private ?RowCap $cap,
        private ?int $limit = null,
    ) {}

    /** @return list<int|string> */
    public function resolve(): array
    {
        $walk = EngineKeyWalk::of($this->query, $this->chunkCount, new ActionWalkRefusals);
        $first = $walk->first();

        return $this->query->paging()->exactTotal() ? $this->counted($walk, $first) : $this->uncounted($walk, $first);
    }

    /** @return list<int|string> */
    private function counted(EngineKeyWalk $walk, EnginePage $first): array
    {
        $count = min($first->total, $this->limit ?? $first->total);

        $this->cap?->check($count);

        if ($this->limit === null) {
            $this->ensureReachable($first->total);
        }

        return $walk->distinct($walk->collect($first, $count), $count);
    }

    /** @return list<int|string> */
    private function uncounted(EngineKeyWalk $walk, EnginePage $first): array
    {
        $keys = $walk->collect($first, $this->cap?->limit($this->limit) ?? $this->limit ?? PHP_INT_MAX);

        $this->cap?->check(count($keys));

        return $walk->distinct($keys, count($keys));
    }

    private function ensureReachable(int $total): void
    {
        $refusals = new ActionWalkRefusals;
        $reachable = $this->query->reachable()
            ?? throw $refusals->unreported();

        if ($total >= $reachable) {
            throw $refusals->beyondWindow($reachable);
        }
    }
}
