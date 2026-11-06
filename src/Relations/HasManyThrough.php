<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class HasManyThrough extends Relation
{
    use HasMultipleResults;

    /** @return array<string, string> */
    #[\Override]
    public function rules(Resource $resource, string $prefix): array
    {
        return [
            $prefix => 'prohibited',
        ];
    }
}
