<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class NewestFirst extends Instruction
{
    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->orderByDesc('tickets.id');
    }
}
