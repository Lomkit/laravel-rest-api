<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\StringListAttribute;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PerimeterPrefixedVoucherResource extends PrefixedVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->where('team', '=', 'red'))
            ->indexAttributes([Text::make('team'), StringListAttribute::make('name_prefixes')]);
    }
}
