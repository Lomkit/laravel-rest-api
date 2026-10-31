<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextCapabilities;

abstract class ScoutEngineDeclarationTestCase extends EngineTestCase
{
    /**
     * @param  array<mixed>  $filterable
     * @param  array<mixed>  $sortable
     */
    protected function open(array $filterable = [], array $sortable = []): ScoutEngine
    {
        return ScoutEngine::make()
            ->filterable($filterable)
            ->sortable($sortable)
            ->withoutPerimeter();
    }

    protected function capabilities(ScoutEngine $engine): TextCapabilities
    {
        return $engine->capabilities($this->target(VoucherResource::class));
    }
}
