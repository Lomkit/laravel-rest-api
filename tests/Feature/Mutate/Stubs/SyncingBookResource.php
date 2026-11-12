<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\ChildRemoval;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class SyncingBookResource extends BookResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('loan', LoanResource::class),
            MorphMany::make('labels', LabelResource::class)->syncable(ChildRemoval::Detach),
        ];
    }
}
