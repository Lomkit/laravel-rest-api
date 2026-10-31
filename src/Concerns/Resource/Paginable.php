<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Paginable
{
    /**
     * @param  Builder<Model>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate($query, RestRequest $request): LengthAwarePaginator
    {
        $defaultLimit = $this->defaultLimit ?? 50;

        return $query->paginate($request->input('search.limit', $defaultLimit), ['*'], 'page', $request->input('search.page', 1));
    }
}
