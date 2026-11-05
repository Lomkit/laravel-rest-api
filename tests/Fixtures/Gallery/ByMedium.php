<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Instructions\Instruction;

class ByMedium extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [Enum::make('medium', Medium::class)->rules('required')];
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        $query->where('medium', $fields['medium']);
    }
}
