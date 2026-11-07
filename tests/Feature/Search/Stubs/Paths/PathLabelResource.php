<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Paths;

use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class PathLabelResource extends LabelResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('labelable', PathBookResource::class)];
    }
}
