<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

class BoundedViewsPostResource extends Resource
{
    public static string $model = Post::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title'), Integer::make('views')];
    }

    public function scopes(RestRequest $request): array
    {
        return [
            Scope::make('viewedBetween')->fields([Integer::make('min'), Integer::make('max')->rules('gte:min')]),
        ];
    }
}
