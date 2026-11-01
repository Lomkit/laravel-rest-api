<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class PricedAt extends Instruction
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Integer::make('price')->rules('required')];
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        $query->where('price', $fields['price']);
    }
}
