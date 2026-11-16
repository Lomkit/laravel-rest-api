<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class ListedVoucherResource extends VoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->sortable(['price', 'number', 'code'])
            ->withoutPerimeter()
            ->servesLists();
    }

    #[\Override]
    public function actions(): array
    {
        return [...parent::actions(), QueuedDeactivateVouchers::make()];
    }
}
