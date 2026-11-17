<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class DatabaseListedVoucherResource extends ListedVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->sortable(['price', 'number', 'code'])
            ->withoutPerimeter()
            ->servesLists()
            ->allowsDatabaseLists();
    }
}
