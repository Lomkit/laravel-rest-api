<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class PricedAt extends Instruction
{
    public function fields(RestRequest $request): array
    {
        return [Integer::make('price')->rules('required')];
    }

    public function handle(array $fields, Builder $query): void
    {
        $query->where('price', $fields['price']);
    }

    public function handleScout(array $fields, ScoutBuilder $query): void
    {
        $query->where('price', $fields['price']);
    }
}
