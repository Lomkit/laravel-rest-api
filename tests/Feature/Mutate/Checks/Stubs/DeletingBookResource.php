<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

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

class DeletingBookResource extends BookResource
{
    use TracesHooks;

    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('loan', LoanResource::class)->deletable(),
            HasOneOfMany::make('latestLoan', LoanResource::class)->deletable(),
            MorphOne::make('label', LabelResource::class)->deletable(),
            MorphOneOfMany::make('latestLabel', LabelResource::class)->deletable(),
            MorphMany::make('labels', LabelResource::class)
                ->abilities(detach: 'detachLabel')
                ->deletable(),
            BelongsToMany::make('shelves', ShelfResource::class),
        ];
    }
}
