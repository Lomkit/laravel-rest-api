<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Archive\CurrentDocumentResource;

class TrashedDocumentResource extends CurrentDocumentResource
{
    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): EloquentBuilder
    {
        return $query->withTrashed()->where('archived', false);
    }
}
