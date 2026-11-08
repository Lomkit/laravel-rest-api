<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Lomkit\Rest\Relations\HasMany;

class NestedDocumentResource extends RetriedDocumentResource
{
    public static string $model = NestedDocument::class;

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('children', self::class)->deletable()];
    }
}
