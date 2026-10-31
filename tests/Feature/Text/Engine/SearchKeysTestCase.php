<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;

abstract class SearchKeysTestCase extends EngineTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Ledger::seed();
    }

    /** @param class-string<Resource> $resource */
    protected function builder(string $resource): Builder
    {
        return new Builder($this->resource($resource));
    }
}
