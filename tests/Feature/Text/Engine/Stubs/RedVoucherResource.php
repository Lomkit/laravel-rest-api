<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class RedVoucherResource extends TitledVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code', 'title'])
            ->sortable(['price', 'number', 'code'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->where('team', '=', 'red'), [Text::make('team')]);
    }
}
