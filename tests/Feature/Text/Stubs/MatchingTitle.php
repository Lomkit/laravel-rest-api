<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class MatchingTitle extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [Text::make('words')];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->whereFullText('title', $fields->words);
    }
}
