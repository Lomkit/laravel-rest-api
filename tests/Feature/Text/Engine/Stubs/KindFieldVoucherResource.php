<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherKind;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class KindFieldVoucherResource extends VoucherResource
{
    #[\Override]
    public function fields(): array
    {
        return [...parent::fields(), Enum::make('kind', VoucherKind::class)];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'kind'])
            ->withoutPerimeter()
            ->servesLists();
    }
}
