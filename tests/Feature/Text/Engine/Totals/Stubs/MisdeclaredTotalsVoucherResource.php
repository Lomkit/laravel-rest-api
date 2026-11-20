<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class MisdeclaredTotalsVoucherResource extends VoucherResource
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
            ->filterable(['number', 'active'])
            ->groupable(['title'])
            ->withoutPerimeter();
    }
}
