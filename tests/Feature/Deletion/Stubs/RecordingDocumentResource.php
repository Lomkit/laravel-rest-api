<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class RecordingDocumentResource extends DocumentResource
{
    /** @var list<string> */
    public static array $hooked = [];

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        self::$hooked[] = $this->request()->resource::class.' '.$this->request()->endpoint()?->value;

        return $query;
    }
}
