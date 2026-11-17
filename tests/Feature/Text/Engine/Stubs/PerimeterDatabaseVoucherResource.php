<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PerimeterDatabaseVoucherResource extends OpenListedVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereIn('code', ['V-a']), ['code'])
            ->servesLists()
            ->allowsDatabaseLists();
    }
}
