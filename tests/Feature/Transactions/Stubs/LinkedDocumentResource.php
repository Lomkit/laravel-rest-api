<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocumentResource;

class LinkedDocumentResource extends SecondaryDocumentResource
{
    public static string $model = LinkedDocument::class;

    #[\Override]
    public function relations(): array
    {
        return [BelongsToMany::make('links', self::class)->persistedBy(InterruptedPivots::class)];
    }
}
