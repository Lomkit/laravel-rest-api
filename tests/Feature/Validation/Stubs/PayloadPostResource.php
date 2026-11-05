<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

class PayloadPostResource extends Resource
{
    public static string $model = Post::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function actions(): array
    {
        return [AttachPayload::make()->standalone()];
    }

    #[\Override]
    public function instructions(): array
    {
        return [WithPayload::make()];
    }
}
