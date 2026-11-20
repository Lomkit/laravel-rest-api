<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PerimeterTotalledVoucherResource extends VoucherResource
{
    public static string $model = CentsVoucher::class;

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code', 'team'])
            ->groupable(['team', 'active', 'number', 'code'])
            ->measurable(['price', 'number'])
            ->perimeter(static fn (EngineQuery $query): EngineQuery => $query->whereIn('team', ['red']))
            ->servesLists();
    }
}
