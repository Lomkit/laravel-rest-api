<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

class PricedUpTo extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Number::make('max')->rules('required')];
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        app(ReceivedFields::class)->record('handle', $fields);

        $query->where('price', '<=', $fields['max']);
    }

    #[\Override]
    public function handleEngine(array $fields, EngineQuery $query): void
    {
        app(ReceivedFields::class)->record('handleEngine', $fields);

        $query->where('price', '<', $fields['max']);
    }
}
