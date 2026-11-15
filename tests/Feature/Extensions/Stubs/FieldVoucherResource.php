<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class FieldVoucherResource extends VoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', Number::make('price'), 'active', 'code'])
            ->sortable(['price', 'number'])
            ->withoutPerimeter();
    }
}
