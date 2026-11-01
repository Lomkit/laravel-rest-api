<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class RecordingDocumentResource extends DocumentResource
{
    /** @var list<class-string> */
    public static array $hooked = [];

    #[\Override]
    public function destroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $this->record($request, $query);
    }

    #[\Override]
    public function restoreQuery(RestRequest $request, Builder $query): Builder
    {
        return $this->record($request, $query);
    }

    #[\Override]
    public function forceDestroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $this->record($request, $query);
    }

    private function record(RestRequest $request, Builder $query): Builder
    {
        self::$hooked[] = $request->resource::class;

        return $query;
    }
}
