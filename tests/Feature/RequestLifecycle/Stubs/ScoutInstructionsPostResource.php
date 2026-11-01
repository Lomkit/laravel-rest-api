<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ScoutInstructionsPostResource extends PostResource
{
    /** @return list<Instruction> */
    public function scoutInstructions(RestRequest $request): array
    {
        return [];
    }
}
