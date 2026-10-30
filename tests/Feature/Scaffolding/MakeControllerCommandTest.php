<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\Controller as BaseController;
use App\Rest\Controllers\GivenPostsController;
use App\Rest\Controllers\PromptedPostsController;
use App\Rest\Controllers\ScaffoldedAbstractController;
use App\Rest\Controllers\ScaffoldedProductController;
use App\Rest\Controllers\VerbosePostsController;
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
        $this->artisan('rest:controller', ['name' => 'ScaffoldedProductController', '--resource' => 'ScaffoldedPlannedResource'])
            ->expectsOutputToContain('The resource [App\Rest\Resources\ScaffoldedPlannedResource] does not exist.')
            ->doesntExpectOutputToContain('Set $resource')
            ->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedProductController::class);

        $this->assertInstanceOf(BaseController::class, new ScaffoldedProductController);
        $this->assertSame('string', (string) (new ReflectionProperty(ScaffoldedProductController::class, 'resource'))->getType());
        $this->assertSame('App\Rest\Resources\ScaffoldedPlannedResource', ScaffoldedProductController::resourceClass());
    }

    #[Test]
    public function it_writes_an_abstract_base_controller(): void
    {
        $this->artisan('rest:base-controller', ['name' => 'ScaffoldedAbstractController'])->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedAbstractController::class);

        $this->assertTrue((new ReflectionClass(ScaffoldedAbstractController::class))->isAbstract());
        $this->assertTrue(is_subclass_of(ScaffoldedAbstractController::class, Controller::class));
    }

    #[Test]
    public function it_prompts_for_the_resource_when_only_the_name_is_given(): void
    {
        $this->artisan('rest:controller', ['name' => 'PromptedPostsController'])
            ->expectsChoice('What resource should this controller rely on? (Optional)', 'PromptedPostResource', [], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(PromptedPostsController::class);

        $this->assertSame('App\Rest\Resources\PromptedPostResource', PromptedPostsController::resourceClass());
    }

    #[Test]
    public function it_prompts_for_the_resource_when_the_command_runs_verbosely(): void
    {
        $this->artisan('rest:controller', ['--verbose' => true])
            ->expectsQuestion('What should the controller be named?', 'VerbosePostsController')
            ->expectsChoice('What resource should this controller rely on? (Optional)', 'VerbosePostResource', [], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(VerbosePostsController::class);

        $this->assertSame('App\Rest\Resources\VerbosePostResource', VerbosePostsController::resourceClass());
    }

    #[Test]
    public function it_does_not_prompt_for_the_resource_when_it_is_given(): void
    {
        $this->artisan('rest:controller', ['--resource' => 'GivenPostResource'])
            ->expectsQuestion('What should the controller be named?', 'GivenPostsController')
            ->assertSuccessful();

        $this->loadGeneratedClass(GivenPostsController::class);

        $this->assertSame('App\Rest\Resources\GivenPostResource', GivenPostsController::resourceClass());
    }

    #[Test]
    public function it_leaves_the_resource_out_when_the_resource_prompt_is_left_empty(): void
    {
        $this->artisan('rest:controller')
            ->expectsQuestion('What should the controller be named?', 'EmptyAnswerController')
            ->expectsChoice('What resource should this controller rely on? (Optional)', '', [], true)
            ->expectsOutputToContain('No resource was given or found for this controller. Pass --resource to set it.')
            ->assertSuccessful();

        $this->assertStringNotContainsString('$resource', file_get_contents(app_path('Rest/Controllers/EmptyAnswerController.php')));
    }
}
