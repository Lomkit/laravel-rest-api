<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Grouped;

use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;
use Lomkit\Rest\Tests\Fixtures\Ledger\DeactivateVouchers;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\TextSearch;

class GroupedVoucherResource extends VoucherResource
{
    public static string $model = GroupedVoucher::class;

    #[\Override]
    public function scopes(): array
    {
        return [Scope::make('wide'), Scope::make('cheapFirst')];
    }

    #[\Override]
    public function instructions(): array
    {
        return [TitledOrPricey::make()];
    }

    #[\Override]
    public function actions(): array
    {
        return [DeactivateVouchers::make()];
    }

    #[\Override]
    public function metrics(): array
    {
        return [RowCount::make()];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return null;
    }
}
