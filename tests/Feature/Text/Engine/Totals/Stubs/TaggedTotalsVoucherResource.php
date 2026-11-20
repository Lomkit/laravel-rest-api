<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class TaggedTotalsVoucherResource extends VoucherResource
{
    public static string $model = CentsVoucher::class;

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code', 'team', Text::make('tags')])
            ->groupable(['team', 'tags', 'code'])
            ->measurable(['price'])
            ->withoutPerimeter()
            ->servesLists();
    }
}
