<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class WithBook extends Instruction
{
    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->with('book');
    }
}
