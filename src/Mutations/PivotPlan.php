<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;

final readonly class PivotPlan
{
    /**
     * @param  list<Model>  $attached
     * @param  list<Model>  $updated
     * @param  list<Model>  $detached
     */
    public function __construct(
        public array $attached = [],
        public array $updated = [],
        public array $detached = [],
    ) {}
}
