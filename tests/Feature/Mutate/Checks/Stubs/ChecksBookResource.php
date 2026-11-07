<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class ChecksBookResource extends BookResource
{
    use TracesHooks;

    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('loan', LoanResource::class)->abilities(attach: 'attachLoan'),
            MorphMany::make('labels', LabelResource::class),
            BelongsToMany::make('shelves', ShelfResource::class)->abilities(attach: 'attachShelf', detach: 'detachShelf'),
        ];
    }
}
