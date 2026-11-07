<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;

class UnselectableAuthorResource extends Resource
{
    public static string $model = Author::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('first_name'), $this->createdAt()];
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('posts', UnselectablePostResource::class)];
    }

    protected function createdAt(): DateTime
    {
        return DateTime::make('created_at')->selectable(false);
    }
}
