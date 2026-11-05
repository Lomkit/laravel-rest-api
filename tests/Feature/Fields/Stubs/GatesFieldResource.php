<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

class GatesFieldResource extends Resource
{
    public static string $model = Post::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('gates'), Text::make('abilities')];
    }
}
