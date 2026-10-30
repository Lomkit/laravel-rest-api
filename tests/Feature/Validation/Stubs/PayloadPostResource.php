<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

class PayloadPostResource extends Resource
{
    public static string $model = Post::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function actions(RestRequest $request): array
    {
        return [AttachPayload::make()->standalone()];
    }

    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [WithPayload::make()];
    }
}
