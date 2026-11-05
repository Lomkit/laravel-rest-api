<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class TitledVoucherResource extends TeamVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code', 'title'])
            ->sortable(['price', 'number', 'code'])
            ->perimeter(fn (EngineQuery $query, RestRequest $request): EngineQuery => $query->whereIn('team', $request->user()?->teams ?? []), [Text::make('team')]);
    }

    #[\Override]
    public function instructions(): array
    {
        return [...parent::instructions(), ComparesPrice::make()];
    }

    #[\Override]
    public function actions(): array
    {
        return [...parent::actions(), DeactivateVouchersInLargeChunks::make()];
    }
}
