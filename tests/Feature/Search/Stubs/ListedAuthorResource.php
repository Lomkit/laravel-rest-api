<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class ListedAuthorResource extends AuthorResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('last_name', '!=', 'Hidden')->orWhere('first_name', 'Grace');
    }
}
