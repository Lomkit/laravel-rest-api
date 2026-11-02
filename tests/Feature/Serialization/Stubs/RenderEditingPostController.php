<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class RenderEditingPostController extends Controller
{
    public static string $resource = PostResource::class;

    #[\Override]
    protected function beforeSearch(SearchRequest $request): void
    {
        $request->merge(['search' => [
            'selects' => [['field' => 'id']],
            'gates' => ['create', 'view'],
            'includes' => [['relation' => 'author', 'alias' => 'writer']],
        ]]);
    }
}
