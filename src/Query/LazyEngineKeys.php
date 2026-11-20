<?php

namespace Lomkit\Rest\Query;

use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\EngineKeyWalk;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\EngineWalkRefusals;

/** @internal */
final readonly class LazyEngineKeys implements EngineWalkRefusals
{
    /** @param class-string<Resource> $resource */
    public function __construct(
        private EngineQuery $query,
        private string $resource,
        private ?int $max,
    ) {}

    /** @return list<int|string> */
    public function read(): array
    {
        $reachable = $this->query->reachable();

        if ($reachable === null && $this->max === null) {
            throw SearchRunException::engineWindow($this->resource, null);
        }

        $walk = EngineKeyWalk::whole($this->query, $this);
        $first = $walk->first();

        if (! $this->query->paging()->exactTotal()) {
            return $this->uncounted($walk, $walk->collect($first, $this->max ?? PHP_INT_MAX), $reachable);
        }

        $count = min($first->total, $this->max ?? $first->total);
        $this->ensureWithin($first->total, $count, $reachable);

        return $walk->distinct($walk->collect($first, $count), $count);
    }

    #[\Override]
    public function beyondWindow(int $window): SearchRunException
    {
        return SearchRunException::engineWindow($this->resource, $window);
    }

    #[\Override]
    public function changed(): SearchRunException
    {
        return SearchRunException::engineResultsChanged($this->resource);
    }

    /**
     * @param  list<int|string>  $keys
     * @return list<int|string>
     */
    private function uncounted(EngineKeyWalk $walk, array $keys, ?int $reachable): array
    {
        if ($reachable !== null && count($keys) >= $reachable && ($this->max === null || $this->max > $reachable)) {
            throw $this->beyondWindow($reachable);
        }

        return $walk->distinct($keys, count($keys));
    }

    private function ensureWithin(int $total, int $count, ?int $reachable): void
    {
        $window = $this->query->paging()->window();

        if ($window !== null && $count > $window) {
            throw $this->beyondWindow($window);
        }

        if ($reachable !== null && ($this->max === null ? $total >= $reachable : $count > $reachable)) {
            throw $this->beyondWindow($reachable);
        }
    }
}
