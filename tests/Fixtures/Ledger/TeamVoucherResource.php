<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class TeamVoucherResource extends VoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->sortable(['price', 'number'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereIn('team', $this->teams()), [Text::make('team')]);
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->whereIn('team', $this->teams());
    }

    /** @return list<string> */
    private function teams(): array
    {
        return $this->request()->user()?->teams ?? [];
    }
}
