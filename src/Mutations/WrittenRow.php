<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class WrittenRow
{
    /** @internal */
    public function __construct(
        public Resource $resource,
        public MutationNode $entry,
        public Model $model,
    ) {}
}
