<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherKind;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;

abstract class EngineQueryContractTestCase extends EngineTestCase
{
    protected RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine(runs: false);
        $this->useEngine($this->engine);
    }

    protected function engineQuery(?Trashed $trashed = null): EngineQuery
    {
        $engine = ScoutEngine::make()
            ->filterable([ID::make('id'), 'number', 'price', 'active', 'code', Enum::make('kind', VoucherKind::class)])
            ->sortable(['price', 'number'])
            ->withoutPerimeter();

        return $engine->query(new TextQuery('report'), $this->target(VoucherResource::class), $this->context()->request, $trashed);
    }
}
