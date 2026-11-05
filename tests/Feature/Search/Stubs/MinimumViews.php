<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Instructions\Instruction;

class MinimumViews extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [Integer::make('views')->rules('sometimes', 'min:10')];
    }
}
