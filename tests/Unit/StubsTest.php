<?php
namespace Lomkit\Rest\Tests\Unit;

use Illuminate\Events\Dispatcher;
use Lomkit\Rest\Tests\TestCase;

class StubsTest extends TestCase
{
    public function test_stubs_correctly_registered(): void
    {
        app(Dispatcher::class)->dispatch($event = new \Illuminate\Foundation\Events\PublishingStubs([]));

        $this->assertEquals(
            [
                realpath(__DIR__.'/../../src/Console/stubs/action.stub')                         => 'rest.action.stub',
                realpath(__DIR__.'/../../src/Console/stubs/base-controller.stub')               => 'rest.base-controller.stub',
                realpath(__DIR__.'/../../src/Console/stubs/base-resource.stub')                 => 'rest.base-resource.stub',
                realpath(__DIR__.'/../../src/Console/stubs/controller.stub')                    => 'rest.controller.stub',
                realpath(__DIR__.'/../../src/Console/stubs/instruction.stub')                   => 'rest.instruction.stub',
                realpath(__DIR__.'/../../src/Console/stubs/resource.stub')                      => 'rest.resource.stub',
                realpath(__DIR__.'/../../src/Console/stubs/response.stub')                      => 'rest.response.stub',
                realpath(__DIR__.'/../../src/Console/stubs/rest-documentation-service-provider.stub') => 'rest.rest-documentation-service-provider.stub',
            ],
            $event->stubs
        );
    }
}
