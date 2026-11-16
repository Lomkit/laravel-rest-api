<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class GroupedReaderVoucherResource extends ReaderVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereIn('team', ['red']), [Text::make('team')])
            ->servesLists();
    }
}
