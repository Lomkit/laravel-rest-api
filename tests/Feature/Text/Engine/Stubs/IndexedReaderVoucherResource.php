<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderInstruction;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\StringListAttribute;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class IndexedReaderVoucherResource extends ReaderVoucherResource
{
    #[\Override]
    public function instructions(): array
    {
        return [ReaderInstruction::class];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->withoutPerimeter()
            ->indexAttributes([StringListAttribute::make('readers')]);
    }
}
