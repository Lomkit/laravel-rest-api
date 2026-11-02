<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ContextCheckingPostController extends Controller
{
    public static string $resource = PostResource::class;

    #[\Override]
    protected function beforeSearch(SearchRequest $request): void
    {
        $search = $request->searchContext();

        if ($search->filtersOn('featured') && ! $search->filtersOn('status')) {
            throw ValidationException::withMessages(['search.filters' => 'The featured filter requires a status filter.']);
        }
    }
}
