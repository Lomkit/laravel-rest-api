<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class RecordingDocumentResource extends DocumentResource
{
    /** @var list<string> */
    public static array $hooked = [];

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        self::$hooked[] = $request->resource::class.' '.$request->endpoint()?->value;

        return $query;
    }
}
