<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

class NumberedFrom extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Integer::make('min')->default(1)];
    }

    #[\Override]
    public function handleEngine(array $fields, EngineQuery $query): void
    {
        app(ReceivedFields::class)->record('handleEngine', $fields);

        $query->where('number', '>=', $fields['min']);
    }
}
