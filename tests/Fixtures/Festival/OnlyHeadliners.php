<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class OnlyHeadliners extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [Integer::make('min_seats')->nullable()];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->where('level', ShowLevel::Headline->value);

        if ($fields->filled('min_seats')) {
            $query->where('seats', '>=', $fields->min_seats);
        }
    }
}
