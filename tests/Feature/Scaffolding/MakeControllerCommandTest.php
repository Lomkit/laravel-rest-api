<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\Controller as BaseController;
use App\Rest\Controllers\ScaffoldedPlaceholderController;
use App\Rest\Controllers\ScaffoldedProductController;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;

class MakeControllerCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_a_controller_that_declares_its_typed_resource(): void
    {
        $this->artisan('rest:controller', ['name' => 'ScaffoldedProductController', '--resource' => 'ScaffoldedProductResource'])->assertSuccessful();

        $this->loadGeneratedClass(BaseController::class);
        $this->loadGeneratedClass(ScaffoldedProductController::class);

        $this->assertInstanceOf(BaseController::class, new ScaffoldedProductController);
        $this->assertSame('string', (string) (new ReflectionProperty(ScaffoldedProductController::class, 'resource'))->getType());
        $this->assertSame('App\Rest\Resources\ScaffoldedProductResource', ScaffoldedProductController::resourceClass());
    }

    #[Test]
    public function it_writes_a_placeholder_resource_when_no_resource_is_given(): void
    {
        $this->artisan('rest:controller', ['name' => 'ScaffoldedPlaceholderController'])->assertSuccessful();

        $this->loadGeneratedClass(BaseController::class);
        $this->loadGeneratedClass(ScaffoldedPlaceholderController::class);

        $this->assertSame('Resource', ScaffoldedPlaceholderController::resourceClass());
    }

    #[Test]
    public function it_writes_an_abstract_base_controller(): void
    {
        $this->artisan('rest:base-controller', ['name' => 'Controller'])->assertSuccessful();

        $this->loadGeneratedClass(BaseController::class);

        $this->assertTrue((new ReflectionClass(BaseController::class))->isAbstract());
        $this->assertTrue(is_subclass_of(BaseController::class, Controller::class));
    }
}
