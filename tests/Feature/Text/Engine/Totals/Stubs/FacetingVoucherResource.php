<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class FacetingVoucherResource extends VoucherResource
{
    public static string $model = FacetingVoucher::class;

    #[\Override]
    public function relations(): array
    {
        return [];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'active'])
            ->groupable(['active'])
            ->withoutPerimeter();
    }
}
