<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\StringListAttribute;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PrefixedVoucherResource extends VoucherResource
{
    #[\Override]
    public function instructions(): array
    {
        return [NamePrefixes::class, OtherNamePrefixes::class];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->withoutPerimeter()
            ->indexAttributes([StringListAttribute::make('name_prefixes')])
            ->servesLists();
    }
}
