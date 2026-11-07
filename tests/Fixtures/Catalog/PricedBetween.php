<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

class PricedBetween extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function fields(): array
    {
        return [Integer::make('min')->default(0), Integer::make('max')->rules('required')];
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        app(ReceivedFields::class)->record('handleEngine', $fields->toArray());

        $query->where('price', '>=', $fields->min)->where('price', '<=', $fields->max);
    }
}
