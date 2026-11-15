<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class WithArchivedInstruction extends Instruction
{
    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->where('last_name', 'Lovelace')->orWhere('first_name', 'Grace');
    }

    #[\Override]
    public function searchMeta(InstructionFields $fields): array
    {
        return ['scope' => 'archived'];
    }
}
