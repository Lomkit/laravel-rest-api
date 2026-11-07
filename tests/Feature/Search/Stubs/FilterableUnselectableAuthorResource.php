<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Relations\HasMany;

class FilterableUnselectableAuthorResource extends UnselectableAuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('posts', FilterableUnselectablePostResource::class)];
    }

    #[\Override]
    protected function createdAt(): DateTime
    {
        return parent::createdAt()->filterable()->sortable();
    }
}
