<?php

namespace Lomkit\Rest\Tests\Support\Rest\Instructions;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class NumberedInstruction extends Instruction
{
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->where('number', '>', $fields->number ?? 0);
    }

    public function fields(): array
    {
        return [
            Integer::make('number'),
        ];
    }
}
