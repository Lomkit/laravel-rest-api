<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Paths;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\TagResource;

class PathTagResource extends TagResource
{
    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('name', '!=', 'Hidden');
    }
}
