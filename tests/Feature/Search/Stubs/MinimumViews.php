<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class MinimumViews extends Instruction
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Integer::make('views')->rules('sometimes', 'min:10')];
    }
}
