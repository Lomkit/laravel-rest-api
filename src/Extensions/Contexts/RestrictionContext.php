<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class RestrictionContext
{
    /**
     * @internal
     *
     * @param  Builder<Model>  $query
     */
    public function __construct(
        public Builder $query,
        public Resource $resource,
        public ?Authenticatable $user,
    ) {}
}
