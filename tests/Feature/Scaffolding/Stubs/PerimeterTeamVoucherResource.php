<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

final class PerimeterTeamVoucherResource extends VoucherResource
{
    #[\Override]
    public function relations(): array
    {
        return [];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->where('team', '=', 1), [Integer::make('team')]);
    }
}
