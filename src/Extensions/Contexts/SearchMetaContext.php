<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\SearchContext;

final readonly class SearchMetaContext
{
    /**
     * @internal
     *
     * @param  list<Model>  $rows
     */
    public function __construct(
        public Resource $resource,
        public ?Authenticatable $user,
        public array $rows,
        public int $total,
        public int $page,
        public int $perPage,
        public SearchContext $search,
    ) {}
}
