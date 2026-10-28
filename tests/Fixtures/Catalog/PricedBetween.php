<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class PricedBetween extends Instruction
{
    public function fields(RestRequest $request): array
    {
        return [Integer::make('min')->default(0), Integer::make('max')->rules('required')];
    }

    public function handleScout(array $fields, ScoutBuilder $query): void
    {
        app(ReceivedFields::class)->record('handleScout', $fields);

        $query->where('price', '>=', $fields['min'])->where('price', '<=', $fields['max']);
    }
}
