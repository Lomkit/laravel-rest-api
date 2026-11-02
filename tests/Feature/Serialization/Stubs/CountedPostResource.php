<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class CountedPostResource extends PostResource
{
    #[\Override]
    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        $total = $query->toBase()->getCountForPagination();

        return new CountedPaginator($query->forPage($page, $perPage)->get(), $total, $perPage, $page);
    }
}
