<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\TextSearch;

class PlainEngineVoucherResource extends VoucherResource
{
    #[\Override]
    public function relations(): array
    {
        return [];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return new PlainEngineSearch;
    }
}
