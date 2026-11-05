<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ScoutHandledInstructionPostResource extends PostResource
{
    #[\Override]
    public function instructions(): array
    {
        return [ScoutHandledInstruction::make()];
    }
}
