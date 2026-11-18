<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\EngineConditionGroup;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class PerimeterGroupReaderVoucherResource extends ReaderVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query
                ->whereIn('readers', ['bob'])
                ->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group->where('number', '=', 1)->where('active', '=', false)));
    }
}
