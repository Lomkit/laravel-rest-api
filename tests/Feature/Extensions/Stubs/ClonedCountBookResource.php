<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ClonedCountBookResource extends UncappedGroupedBookResource
{
    #[\Override]
    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        return parent::paginate((clone $query)->where('books.id', '>', 0), $perPage, $page);
    }
}
