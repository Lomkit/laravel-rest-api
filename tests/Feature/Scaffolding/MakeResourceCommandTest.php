<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Resources\Resource as BaseResource;
use App\Rest\Resources\ScaffoldedHooklessBaseResource;
use App\Rest\Resources\ScaffoldedNamedResource;
use App\Rest\Resources\ScaffoldedProductResource;
use App\Rest\Resources\ScaffoldedSuggestedResource;
use App\Rest\Resources\ScaffoldedVerboseResource;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class MakeResourceCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_a_resource_that_declares_its_typed_model(): void
    {
        $this->artisan('rest:resource', ['name' => 'ScaffoldedProductResource', '--model' => 'Product'])->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedProductResource::class);
        $resource = new ScaffoldedProductResource;

        $this->assertInstanceOf(BaseResource::class, $resource);
        $this->assertSame('string', (string) (new ReflectionProperty(ScaffoldedProductResource::class, 'model'))->getType());
        $this->assertSame('App\Models\Product', ScaffoldedProductResource::modelClass());
        $this->assertEquals([ID::make('id')], $resource->fields(new RestRequest));
        $this->assertSame([10, 25, 50], $resource->limits(new RestRequest));
    }

    #[Test]
    public function it_writes_a_base_resource_that_leaves_every_query_hook_to_the_package(): void
    {
        $this->artisan('rest:base-resource', ['name' => 'ScaffoldedHooklessBaseResource'])->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedHooklessBaseResource::class);

        $this->assertTrue((new ReflectionClass(ScaffoldedHooklessBaseResource::class))->isAbstract());
        $this->assertSame(Resource::class, get_parent_class(ScaffoldedHooklessBaseResource::class));
        foreach (['searchQuery', 'searchScoutQuery', 'mutateQuery', 'destroyQuery', 'restoreQuery', 'forceDestroyQuery'] as $hook) {
            $this->assertSame(Resource::class, (new ReflectionMethod(ScaffoldedHooklessBaseResource::class, $hook))->class);
        }
    }

    #[Test]
    public function it_suggests_the_application_models_when_it_prompts_for_the_model(): void
    {
        foreach (['User', 'Comment', 'Post'] as $model) {
            file_put_contents(app_path("Models/{$model}.php"), '<?php');
        }

        $this->artisan('rest:resource')
            ->expectsQuestion('What should the resource be named?', 'ScaffoldedSuggestedResource')
            ->expectsChoice('What model should this resource rely on? (Optional)', 'User', ['Comment', 'Post', 'User'], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedSuggestedResource::class);

        $this->assertSame('App\Models\User', ScaffoldedSuggestedResource::modelClass());
    }

    #[Test]
    public function it_prompts_for_the_model_when_only_the_name_is_given(): void
    {
        $this->artisan('rest:resource', ['name' => 'ScaffoldedNamedResource'])
            ->expectsChoice('What model should this resource rely on? (Optional)', 'Product', [], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedNamedResource::class);

        $this->assertSame('App\Models\Product', ScaffoldedNamedResource::modelClass());
    }

    #[Test]
    public function it_prompts_for_the_model_when_the_command_runs_verbosely(): void
    {
        $this->artisan('rest:resource', ['--verbose' => true])
            ->expectsQuestion('What should the resource be named?', 'ScaffoldedVerboseResource')
            ->expectsChoice('What model should this resource rely on? (Optional)', 'Product', [], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedVerboseResource::class);

        $this->assertSame('App\Models\Product', ScaffoldedVerboseResource::modelClass());
    }

    #[Test]
    public function it_does_not_prompt_for_the_model_when_it_is_given(): void
    {
        $this->artisan('rest:resource', ['--model' => 'Product'])
            ->expectsQuestion('What should the resource be named?', 'ScaffoldedGivenResource')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Rest/Resources/ScaffoldedGivenResource.php'));
    }

    #[Test]
    public function it_leaves_the_model_out_when_the_model_prompt_is_left_empty(): void
    {
        file_put_contents(app_path('Models/Post.php'), '<?php');

        $this->artisan('rest:resource')
            ->expectsQuestion('What should the resource be named?', 'ScaffoldedPromptedResource')
            ->expectsChoice('What model should this resource rely on? (Optional)', '', ['Post'], true)
            ->expectsOutputToContain('No model was given or found for this resource. Pass --model to set it.')
            ->assertSuccessful();

        $this->assertStringNotContainsString('$model', file_get_contents(app_path('Rest/Resources/ScaffoldedPromptedResource.php')));
    }
}
