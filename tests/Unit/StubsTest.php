<?php

namespace Lomkit\Rest\Tests\Unit;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Events\PublishingStubs;
use Lomkit\Rest\Tests\TestCase;

class StubsTest extends TestCase
{
    public function test_stubs_correctly_registered(): void
    {
        app(Dispatcher::class)->dispatch($event = new PublishingStubs([]));

        $this->assertEquals(
            [
                realpath(__DIR__.'/../../src/Console/stubs/action.stub') => 'rest.action.stub',
                realpath(__DIR__.'/../../src/Console/stubs/base-controller.stub') => 'rest.base-controller.stub',
                realpath(__DIR__.'/../../src/Console/stubs/base-resource.stub') => 'rest.base-resource.stub',
                realpath(__DIR__.'/../../src/Console/stubs/controller.stub') => 'rest.controller.stub',
                realpath(__DIR__.'/../../src/Console/stubs/instruction.stub') => 'rest.instruction.stub',
                realpath(__DIR__.'/../../src/Console/stubs/resource.stub') => 'rest.resource.stub',
                realpath(__DIR__.'/../../src/Console/stubs/response.stub') => 'rest.response.stub',
            ],
            $event->stubs
        );
    }
}
