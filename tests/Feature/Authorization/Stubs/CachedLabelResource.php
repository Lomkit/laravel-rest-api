<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class CachedLabelResource extends LabelResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('labelable', [CachedBookResource::class, CachedShelfResource::class])];
    }

    #[\Override]
    public function authorizationCache(): array
    {
        return ['viewAny' => 300, 'view' => 60];
    }
}
