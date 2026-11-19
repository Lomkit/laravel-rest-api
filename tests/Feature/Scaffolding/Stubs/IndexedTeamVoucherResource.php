<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

final class IndexedTeamVoucherResource extends VoucherResource
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
            ->filterable(['number'])
            ->withoutPerimeter()
            ->indexAttributes([Integer::make('team')]);
    }
}
