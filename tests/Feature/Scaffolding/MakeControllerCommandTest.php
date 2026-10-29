<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\Controller as BaseController;
use App\Rest\Controllers\ScaffoldedPlaceholderController;
use App\Rest\Controllers\ScaffoldedProductController;
use App\Rest\Controllers\ScaffoldedPromptedController;
use App\Rest\Controllers\ScaffoldedSuggestedController;
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

    #[Test]
    public function it_suggests_the_application_resources_when_it_prompts_for_the_resource(): void
    {
        mkdir(app_path('Rest/Resources'), recursive: true);
        foreach (['ProductResource', 'CategoryResource'] as $resource) {
            file_put_contents(app_path("Rest/Resources/{$resource}.php"), '<?php');
        }

        $this->artisan('rest:controller')
            ->expectsQuestion('What should the controller be named?', 'ScaffoldedSuggestedController')
            ->expectsChoice('What resource should this controller rely on? (Optional)', 'ProductResource', ['CategoryResource', 'ProductResource'], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(BaseController::class);
        $this->loadGeneratedClass(ScaffoldedSuggestedController::class);

        $this->assertSame('App\Rest\Resources\ProductResource', ScaffoldedSuggestedController::resourceClass());
    }

    #[Test]
    public function it_prompts_for_the_resource_before_any_resource_exists(): void
    {
        $this->artisan('rest:controller')
            ->expectsQuestion('What should the controller be named?', 'ScaffoldedPromptedController')
            ->expectsChoice('What resource should this controller rely on? (Optional)', '', [], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(BaseController::class);
        $this->loadGeneratedClass(ScaffoldedPromptedController::class);

        $this->assertSame('Resource', ScaffoldedPromptedController::resourceClass());
    }
}
