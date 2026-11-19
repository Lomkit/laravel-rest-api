<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\StringListAttribute;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class ListPerimeterPrefixedVoucherResource extends PrefixedVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereIn('name_prefixes', ['re']))
            ->indexAttributes([StringListAttribute::make('name_prefixes')]);
    }
}
