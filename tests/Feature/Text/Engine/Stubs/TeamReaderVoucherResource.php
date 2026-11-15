<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class TeamReaderVoucherResource extends ReaderVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->where('team', '=', 'red')->options(['consistency' => ['level' => 'eventual']]), [Text::make('team')]);
    }
}
