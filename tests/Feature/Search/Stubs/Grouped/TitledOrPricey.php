<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Grouped;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class TitledOrPricey extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [Text::make('title')];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->where('title', $fields->title)->orWhere('price', '>', 10);
    }
}
