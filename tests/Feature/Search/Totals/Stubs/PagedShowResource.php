<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class PagedShowResource extends ShowResource
{
    /**
     * @param  Builder<Model>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    #[\Override]
    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        return parent::paginate($query->where('seats', '>', 0), $perPage, $page);
    }
}
