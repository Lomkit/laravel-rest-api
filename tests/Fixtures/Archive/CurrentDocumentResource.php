<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class CurrentDocumentResource extends Resource
{
    public static string $model = Document::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): EloquentBuilder
    {
        return $query->where('archived', false);
    }

    #[\Override]
    public function destroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('archived', false);
    }

    #[\Override]
    public function restoreQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('archived', false);
    }

    #[\Override]
    public function forceDestroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('archived', false);
    }
}
