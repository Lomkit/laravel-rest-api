<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\FullText;
use Lomkit\Rest\Text\TextSearch;

class PerimeterPostResource extends SearchablePostResource
{
    #[\Override]
    public function text(RestRequest $request): TextSearch
    {
        return FullText::make()
            ->index('title', 'summary')
            ->index('title')
            ->otherwise(Contains::make());
    }

    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('featured', true)->orWhere('views', '>=', 100);
    }
}
