<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Closure;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;

abstract class EngineQueryTestCase extends EngineTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Ledger::seed();
        Voucher::query()->create(['id' => 7, 'code' => 'V-g', 'number' => 7, 'team' => 'green', 'title' => 'Report green', 'price' => 9, 'active' => true]);
    }

    /** @param (Closure(EngineQuery): mixed)|null $perimeter */
    protected function engine(?Closure $perimeter = null): ScoutEngine
    {
        $engine = ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code', Text::make('team')])
            ->sortable(['price', 'number']);

        return $perimeter === null
            ? $engine->withoutPerimeter()
            : $engine->perimeter(fn (EngineQuery $query): mixed => $perimeter($query));
    }

    protected function search(?ScoutEngine $engine = null, string $text = 'report', ?Trashed $trashed = null): EngineQuery
    {
        $target = $this->target(VoucherResource::class);

        return ($engine ?? $this->engine())->query(new TextQuery($text), $target, $this->context()->request, $trashed);
    }
}
