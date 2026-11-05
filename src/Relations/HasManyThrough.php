<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class HasManyThrough extends Relation
{
    use HasMultipleResults;

    /**
     * Define validation rules for the HasManyThrough relation.
     *
     * @param  resource  $resource  The resource associated with the relation.
     * @param  string  $prefix  The prefix used for validation rules.
     * @return array An array of validation rules.
     */
    #[\Override]
    public function rules(Resource $resource, string $prefix)
    {
        return [
            $prefix => 'prohibited',
        ];
    }
}
