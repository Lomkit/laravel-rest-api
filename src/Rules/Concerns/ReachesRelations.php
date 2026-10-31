<?php

namespace Lomkit\Rest\Rules\Concerns;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Validation\RuleCompiler;

trait ReachesRelations
{
    protected function reachRelation(RuleCompiler $into, mixed $relation, string $path): void
    {
        if (! is_string($relation)) {
            return;
        }

        $resource = $this->resource;

        foreach (explode('.', $relation) as $segment) {
            $resource = $resource->relation($segment)?->resource();

            if ($resource === null) {
                return;
            }

            $into->reach(Check::onClass($resource, 'viewAny', $path));
        }
    }
}
