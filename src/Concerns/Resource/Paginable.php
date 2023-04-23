<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Paginable
{
    public function paginate(Builder $query, RestRequest $request) {
        return $query->paginate($request->input('limit', 50));
    }
}