<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Search\SearchContext;

class ContextReadingSearchRequest extends SearchRequest
{
    /** @var list<SearchContext> */
    public static array $read = [];

    #[\Override]
    protected function passedValidation(): void
    {
        parent::passedValidation();

        self::$read = [$this->searchContext(), $this->searchContext()];
    }
}
