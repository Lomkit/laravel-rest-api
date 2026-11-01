<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Text\Engine\EngineQuery;

class ComparesPrice extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            Text::make('operator')->rules('required'),
            Number::make('value')->rules('required'),
        ];
    }

    #[\Override]
    public function handleEngine(array $fields, EngineQuery $query): void
    {
        $query->where('price', (string) $fields['operator'], $fields['value']);
    }
}
