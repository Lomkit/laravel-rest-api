<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

class UnselectablePostResource extends Resource
{
    public static string $model = Post::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title'), $this->views()];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('author', UnselectableAuthorResource::class)];
    }

    protected function views(): Integer
    {
        return Integer::make('views')->selectable(false);
    }
}
