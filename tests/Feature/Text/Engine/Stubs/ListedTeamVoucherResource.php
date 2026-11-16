<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class ListedTeamVoucherResource extends TeamVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->sortable(['price', 'number', 'code'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereIn('team', $this->listedTeams()), [Text::make('team')])
            ->servesLists();
    }

    /** @return list<string> */
    private function listedTeams(): array
    {
        return $this->request()->user()?->teams ?? [];
    }
}
