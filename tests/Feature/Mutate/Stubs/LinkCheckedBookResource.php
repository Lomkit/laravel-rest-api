<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\HasOneOfMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphOneOfMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class LinkCheckedBookResource extends BookResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('loan', LoanResource::class)->abilities(attach: 'attachLoan', detach: 'detachLoan'),
            HasOneOfMany::make('latestLoan', LoanResource::class)->abilities(attach: 'attachLoan', detach: 'detachLoan'),
            MorphOne::make('label', LabelResource::class)->abilities(attach: 'attachLabel', detach: 'detachLabel'),
            MorphOneOfMany::make('latestLabel', LabelResource::class)->abilities(attach: 'attachLabel', detach: 'detachLabel'),
            MorphMany::make('labels', LabelResource::class)->abilities(attach: 'attachLabel', detach: 'detachLabel'),
            BelongsToMany::make('shelves', ShelfResource::class),
        ];
    }
}
