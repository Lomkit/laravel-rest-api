<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\PageQuery;

trait Paginable
{
    /** @return list<int> */
    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    /** @return list<int> */
    public function getLimits(): array
    {
        return $this->context()
            ->definition($this)
            ->limits()
            ->allowed;
    }

    /**
     * @param  Builder<Model>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        return (new PageQuery($query))->paginate($perPage, $page);
    }
}
