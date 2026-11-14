<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Query\PageQuery;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class ShortCountBookResource extends BookResource
{
    #[\Override]
    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        return (new PageQuery($query->where('title', '!=', 'Dune'), null))->paginate($perPage, $page);
    }
}
