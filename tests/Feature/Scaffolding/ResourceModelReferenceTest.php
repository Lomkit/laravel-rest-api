<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Resources\BlankModelResource;
use App\Rest\Resources\FqcnProductResource;
use App\Rest\Resources\MissingModelResource;
use App\Rest\Resources\NestedModelResource;
use App\Rest\Resources\RootModelArticleResource;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResourceModelReferenceTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_keeps_a_model_given_by_its_fully_qualified_name_outside_the_app_namespace(): void
    {
        $this->artisan('rest:resource', ['name' => 'FqcnProductResource', '--model' => Product::class, '--no-interaction' => true])
            ->doesntExpectOutputToContain('does not exist')
            ->assertSuccessful();

        $this->loadGeneratedClass(FqcnProductResource::class);

        $this->assertSame(Product::class, FqcnProductResource::modelClass());
    }

    #[Test]
    public function it_qualifies_a_nested_model_given_with_slashes(): void
    {
        $this->artisan('rest:resource', ['name' => 'NestedModelResource', '--model' => 'Admin/Post', '--no-interaction' => true])
            ->expectsOutputToContain('The model [App\Models\Admin\Post] does not exist.')
            ->assertSuccessful();

        $this->loadGeneratedClass(NestedModelResource::class);

        $this->assertSame('App\Models\Admin\Post', NestedModelResource::modelClass());
    }

    #[Test]
    public function it_qualifies_a_model_with_the_root_namespace_when_the_application_has_no_models_directory(): void
    {
        rmdir(app_path('Models'));
        $this->writeModel('App\RootModelArticle');

        $this->artisan('rest:resource', ['name' => 'RootModelArticleResource', '--model' => 'RootModelArticle', '--no-interaction' => true])
            ->doesntExpectOutputToContain('does not exist')
            ->assertSuccessful();

        $this->loadGeneratedClass(RootModelArticleResource::class);

        $this->assertSame('App\RootModelArticle', RootModelArticleResource::modelClass());
    }

    #[Test]
    public function it_keeps_a_model_given_in_the_app_namespace_and_warns_that_it_does_not_exist(): void
    {
        $this->artisan('rest:resource', ['name' => 'MissingModelResource', '--model' => '\App\Models\MissingPost', '--no-interaction' => true])
            ->expectsOutputToContain('The model [App\Models\MissingPost] does not exist.')
            ->assertSuccessful();

        $this->loadGeneratedClass(MissingModelResource::class);

        $this->assertSame('App\Models\MissingPost', MissingModelResource::modelClass());
    }

    #[Test]
    #[DataProvider('invalidModels')]
    public function it_fails_before_writing_anything_when_the_model_is_not_a_valid_class_name(string $model): void
    {
        $this->artisan('rest:resource', ['name' => 'InvalidModelResource', '--model' => $model, '--no-interaction' => true])
            ->expectsOutputToContain("[{$model}] is not a valid class name.")
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    /** @return array<string, array{string}> */
    public static function invalidModels(): array
    {
        return [
            'dash' => ['Post-1'],
            'leading digit' => ['1Post'],
            'trailing slash' => ['Post/'],
            'double slash' => ['Admin//Post'],
        ];
    }

    #[Test]
    public function it_treats_an_empty_model_as_no_model(): void
    {
        $this->artisan('rest:resource', ['name' => 'BlankModelResource', '--model' => '', '--no-interaction' => true])
            ->expectsOutputToContain('No model was given or found for this resource. Pass --model to set it.')
            ->assertSuccessful();

        $this->loadGeneratedClass(BlankModelResource::class);

        $this->expectExceptionObject(InvalidResourceException::missingModel(BlankModelResource::class));

        BlankModelResource::modelClass();
    }

    #[Test]
    #[DataProvider('modelsNamedLikeAnImport')]
    public function it_aliases_a_model_whose_short_name_the_resource_already_uses(string $name, string $model, string $alias): void
    {
        $this->writeModel("App\\Models\\{$model}");

        $this->artisan('rest:resource', ['name' => $name, '--model' => $model, '--no-interaction' => true])->assertSuccessful();

        $contents = file_get_contents(app_path("Rest/Resources/{$name}.php"));
        $this->assertStringContainsString("use App\\Models\\{$model} as {$alias};\n", $contents);
        $this->assertStringContainsString("public static string \$model = {$alias}::class;\n", $contents);

        $resource = "App\\Rest\\Resources\\{$name}";
        $this->loadGeneratedClass($resource);
        $this->assertSame("App\\Models\\{$model}", $resource::modelClass());
        $this->assertSame('App\Rest\Resources\Resource', get_parent_class($resource));
    }

    /** @return array<string, array{string, string, string}> */
    public static function modelsNamedLikeAnImport(): array
    {
        return [
            'the base resource' => ['AliasedLibraryResource', 'Resource', 'ResourceModel'],
            'the resource itself' => ['AliasedShelf', 'AliasedShelf', 'AliasedShelfModel'],
            'a field' => ['AliasedBadgeResource', 'ID', 'IDModel'],
            'the request' => ['AliasedLogResource', 'RestRequest', 'RestRequestModel'],
        ];
    }
}
