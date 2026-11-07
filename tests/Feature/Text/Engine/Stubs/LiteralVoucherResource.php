<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class LiteralVoucherResource extends TeamVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['code', 'title'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereIn('team', $this->request()->user()?->teams ?? []), [Text::make('team')]);
    }
}
