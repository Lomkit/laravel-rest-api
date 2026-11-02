<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class InputEditingPostController extends Controller
{
    public static string $resource = PostResource::class;

    #[\Override]
    protected function beforeSearch(SearchRequest $request): void
    {
        $request->merge(['search' => [
            'filters' => [['field' => 'id', 'value' => 2]],
            'sorts' => [['field' => 'id', 'direction' => 'desc']],
            'scopes' => [['name' => 'popular']],
            'limit' => 10,
            'page' => 2,
        ]]);
    }
}
