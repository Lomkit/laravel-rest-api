<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

class PricedUpTo extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function fields(): array
    {
        return [Number::make('max')->rules('required')];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        app(ReceivedFields::class)->record('handle', $fields->toArray());

        $query->where('price', '<=', $fields->max);
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        app(ReceivedFields::class)->record('handleEngine', $fields->toArray());

        $query->where('price', '<', $fields->max);
    }
}
