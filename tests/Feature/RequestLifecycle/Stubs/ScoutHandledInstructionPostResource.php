<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ScoutHandledInstructionPostResource extends PostResource
{
    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [ScoutHandledInstruction::make()];
    }
}
