<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Routing\Endpoint;

class WidePerimeterBookResource extends PerimeterBookResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        if ($this->request()->endpoint() === Endpoint::Mutate) {
            return $query;
        }

        return $query->where('books.title', '<>', 'Hidden');
    }
}
