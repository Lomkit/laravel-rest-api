<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Routing\Endpoint;

class WidePerimeterBookResource extends PerimeterBookResource
{
    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        if ($request->endpoint() === Endpoint::Mutate) {
            return $query;
        }

        return $query->where('books.title', '<>', 'Hidden');
    }
}
