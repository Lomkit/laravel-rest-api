<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Fixtures\Archive\CurrentDocumentResource;

class LockedDocumentResource extends CurrentDocumentResource
{
    /** @var list<string> */
    public static array $endpoints = [];

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): EloquentBuilder
    {
        static::$endpoints[] = (string) $request->endpoint()?->value;

        $query->where('archived', false);

        return $request->endpoint() === Endpoint::Destroy ? $query->where('title', '<>', 'Locked') : $query;
    }
}
