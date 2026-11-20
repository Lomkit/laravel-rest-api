<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Text\Engine\Profiles\AppliesRowRestrictions;

/** @internal */
final readonly class EngineKeyWalk
{
    public const int UNBOUNDED_PAGE_SIZE = 1000;

    private function __construct(
        private EngineQuery $query,
        private int $perPage,
        private EngineWalkRefusals $refusals,
        private bool $steadyTotal,
    ) {}

    public static function of(EngineQuery $query, int $pageSize, EngineWalkRefusals $refusals): self
    {
        return new self($query, $query->paging()->pageSize($pageSize), $refusals, false);
    }

    public static function whole(EngineQuery $query, EngineWalkRefusals $refusals): self
    {
        $profile = $query->profile();
        $pageSize = $profile instanceof AppliesRowRestrictions
            ? PHP_INT_MAX
            : min($profile->maxPageSize() ?? self::UNBOUNDED_PAGE_SIZE, $profile->window() ?? PHP_INT_MAX);

        return new self($query, $query->paging()->pageSize($pageSize), $refusals, $profile->exactTotal());
    }

    public function first(): EnginePage
    {
        return $this->query->page($this->perPage, 1);
    }

    /** @return list<int|string> */
    public function collect(EnginePage $first, int $wanted): array
    {
        $keys = $first->keys;
        $last = $first;
        $window = $this->query->paging()->window();

        for ($page = 2; count($keys) < $wanted && count($last->keys) === $this->perPage; $page++) {
            if ($window !== null && $this->perPage * $page > $window) {
                throw $this->refusals->beyondWindow($window);
            }

            $last = $this->query->page($this->perPage, $page);

            if ($this->steadyTotal && $last->total !== $first->total) {
                throw $this->refusals->changed();
            }

            array_push($keys, ...$last->keys);
        }

        return array_slice($keys, 0, $wanted);
    }

    /**
     * @param  list<int|string>  $keys
     * @return list<int|string>
     */
    public function distinct(array $keys, int $count): array
    {
        if (count($keys) !== $count || count(array_unique($keys)) !== $count) {
            throw $this->refusals->changed();
        }

        return $keys;
    }
}
