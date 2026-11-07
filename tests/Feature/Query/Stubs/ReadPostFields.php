<?php

namespace Lomkit\Rest\Tests\Feature\Query\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class ReadPostFields extends Instruction
{
    public static ?InstructionFields $received = null;

    #[\Override]
    public function fields(): array
    {
        return [
            Date::make('since'),
            Integer::make('min_views')->default(100),
            Text::make('status')->nullable(),
            Text::make('title'),
        ];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        self::$received = $fields;
    }
}
