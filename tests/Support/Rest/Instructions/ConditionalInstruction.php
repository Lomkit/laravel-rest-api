<?php

namespace Lomkit\Rest\Tests\Support\Rest\Instructions;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Instructions\Instruction;

class ConditionalInstruction extends Instruction
{
    public function handle(array $fields, Builder $query): void
    {
        // No-op refinement — used only to exercise field validation.
    }

    public function fields(): array
    {
        return [
            Text::make('type')->nullable(),
            Text::make('reason')->rules('required_if:type,ban'),
        ];
    }
}
