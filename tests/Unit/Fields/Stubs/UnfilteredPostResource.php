<?php

namespace Lomkit\Rest\Tests\Unit\Fields\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

class UnfilteredPostResource extends Resource
{
    public static string $model = Post::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title')->filterable(false)];
    }
}
