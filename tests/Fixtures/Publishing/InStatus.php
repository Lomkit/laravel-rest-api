<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class InStatus extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [
            Enum::make('status', PostStatus::class)->rules('required'),
            Date::make('since')->rules('required_if:status,published'),
        ];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        app(ReceivedFields::class)->record('handle', $fields->toArray());

        $query->where('status', $fields->status);

        if ($fields->filled('since')) {
            $query->where('published_on', '>=', $fields->since);
        }
    }
}
