<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class CollidingAuthorResource extends AuthorResource
{
    #[\Override]
    public function instructions(): array
    {
        return [CollidingInstruction::class];
    }
}
