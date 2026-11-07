<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ContextCheckingPostResource extends PostResource
{
    #[\Override]
    public function searching(SearchContext $search): void
    {
        if ($search->filtersOn('featured') && ! $search->filtersOn('status')) {
            throw ValidationException::withMessages(['search.filters' => 'The featured filter requires a status filter.']);
        }
    }
}
