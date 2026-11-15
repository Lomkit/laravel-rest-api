<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class RestrictedLabelResource extends LabelResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('labelable', BookResource::class)];
    }
}
