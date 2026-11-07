<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Relations\BelongsTo;

class FilterableUnselectablePostResource extends UnselectablePostResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('author', FilterableUnselectableAuthorResource::class)];
    }

    #[\Override]
    protected function views(): Integer
    {
        return parent::views()->filterable()->sortable();
    }
}
