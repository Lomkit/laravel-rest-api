<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class ListedAuthorResource extends AuthorResource
{
    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('last_name', '!=', 'Hidden')->orWhere('first_name', 'Grace');
    }
}
