<?php

namespace Lomkit\Rest\Search\Plan;

use Lomkit\Rest\Actions\Rows\KeyChunks;

final readonly class PlannedRows
{
    public function __construct(
        public QueryPlan $plan,
        public KeyChunks $chunks,
    ) {}
}
