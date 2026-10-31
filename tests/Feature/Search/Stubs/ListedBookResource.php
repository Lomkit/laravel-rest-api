<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class ListedBookResource extends BookResource
{
    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('title', '!=', 'Dune');
    }
}
