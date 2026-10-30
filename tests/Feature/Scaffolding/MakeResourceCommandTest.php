<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Resources\Resource as BaseResource;
use App\Rest\Resources\ScaffoldedPlaceholderResource;
use App\Rest\Resources\ScaffoldedProductResource;
use App\Rest\Resources\ScaffoldedPromptedResource;
use App\Rest\Resources\ScaffoldedSuggestedResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
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

        $this->loadGeneratedClass(BaseResource::class);
        $this->loadGeneratedClass(ScaffoldedProductResource::class);
        $resource = new ScaffoldedProductResource;

        $this->assertInstanceOf(BaseResource::class, $resource);
        $this->assertSame('string', (string) (new ReflectionProperty(ScaffoldedProductResource::class, 'model'))->getType());
        $this->assertSame('App\Models\Product', ScaffoldedProductResource::modelClass());
        $this->assertEquals([ID::make('id')], $resource->fields(new RestRequest));
        $this->assertSame([10, 25, 50], $resource->limits(new RestRequest));
    }

    #[Test]
    public function it_writes_a_placeholder_model_when_no_model_is_given(): void
    {
        $this->artisan('rest:resource', ['name' => 'ScaffoldedPlaceholderResource'])->assertSuccessful();

        $this->loadGeneratedClass(BaseResource::class);
        $this->loadGeneratedClass(ScaffoldedPlaceholderResource::class);

        $this->assertSame('Model', ScaffoldedPlaceholderResource::modelClass());
    }

    #[Test]
    public function it_writes_a_base_resource_whose_query_hooks_return_the_builder(): void
    {
        $this->artisan('rest:base-resource', ['name' => 'Resource'])->assertSuccessful();

        $this->loadGeneratedClass(BaseResource::class);
        $resource = new class extends BaseResource
        {
            public static string $model = Product::class;
        };
        $request = new RestRequest;
        $query = Product::query();

        $this->assertTrue((new ReflectionClass(BaseResource::class))->isAbstract());
        $this->assertInstanceOf(Resource::class, $resource);
        foreach (['searchQuery', 'mutateQuery', 'destroyQuery', 'restoreQuery', 'forceDestroyQuery'] as $hook) {
            $this->assertSame(Builder::class, (string) (new ReflectionMethod(BaseResource::class, $hook))->getReturnType());
            $this->assertSame($query, $resource->{$hook}($request, $query));
        }
    }

    #[Test]
    public function it_writes_a_base_resource_that_declares_search_scout_query_next_to_search_query(): void
    {
        $this->artisan('rest:base-resource', ['name' => 'Resource'])->assertSuccessful();

        $this->loadGeneratedClass(BaseResource::class);
        $resource = new class extends BaseResource
        {
            public static string $model = Product::class;
        };
        $searchQuery = new ReflectionMethod(BaseResource::class, 'searchQuery');
        $searchScoutQuery = new ReflectionMethod(BaseResource::class, 'searchScoutQuery');
        $query = Product::search('lamp');

        $this->assertSame(BaseResource::class, $searchQuery->getDeclaringClass()->getName());
        $this->assertSame(BaseResource::class, $searchScoutQuery->getDeclaringClass()->getName());
        $this->assertSame(ScoutBuilder::class, (string) $searchScoutQuery->getReturnType());
        $this->assertSame($query, $resource->searchScoutQuery(new RestRequest, $query));
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

        $this->loadGeneratedClass(BaseResource::class);
        $this->loadGeneratedClass(ScaffoldedSuggestedResource::class);

        $this->assertSame('App\Models\User', ScaffoldedSuggestedResource::modelClass());
    }

    #[Test]
    public function it_writes_a_placeholder_model_when_the_model_prompt_is_left_empty(): void
    {
        file_put_contents(app_path('Models/Post.php'), '<?php');

        $this->artisan('rest:resource')
            ->expectsQuestion('What should the resource be named?', 'ScaffoldedPromptedResource')
            ->expectsChoice('What model should this resource rely on? (Optional)', '', ['Post'], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(BaseResource::class);
        $this->loadGeneratedClass(ScaffoldedPromptedResource::class);

        $this->assertSame('Model', ScaffoldedPromptedResource::modelClass());
    }
}
