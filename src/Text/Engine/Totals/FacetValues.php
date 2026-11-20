<?php

namespace Lomkit\Rest\Text\Engine\Totals;

/** @internal */
final readonly class FacetValues
{
    /**
     * @param  list<array{mixed, int}>  $counts
     * @param  array<array-key, mixed>  $stats
     */
    public function __construct(
        public array $counts,
        public array $stats,
    ) {}
}
