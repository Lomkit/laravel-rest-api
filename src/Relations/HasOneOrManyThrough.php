<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Http\Resource;

abstract class HasOneOrManyThrough extends Relation
{
    /** @return array<string, string> */
    #[\Override]
    public function rules(Resource $resource, string $prefix): array
    {
        return [$prefix => 'prohibited'];
    }
}
