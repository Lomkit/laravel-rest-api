<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class UnlistedDatabaseVoucherResource extends OpenListedVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->filterable(['number'])->withoutPerimeter()->allowsDatabaseLists();
    }
}
