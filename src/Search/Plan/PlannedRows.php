<?php

namespace Lomkit\Rest\Search\Plan;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class PlannedRows
{
    /** @param Closure(): Generator<int, Collection<int, Model>> $chunks */
    public function __construct(
        public QueryPlan $plan,
        private Closure $chunks,
    ) {}

    /** @return Generator<int, Collection<int, Model>> */
    public function chunks(): Generator
    {
        return ($this->chunks)();
    }
}
