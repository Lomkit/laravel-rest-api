<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\Admin\NestedGuessTagsController;
use App\Rest\Controllers\BlankResourceController;
use App\Rest\Controllers\FqcnProductsController;
use App\Rest\Controllers\GuessedTagsController;
use App\Rest\Controllers\QualifiedGuessTagsController;
use App\Rest\Controllers\StrayController;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ControllerResourceReferenceTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_keeps_a_resource_given_by_its_fully_qualified_name_outside_the_app_namespace(): void
    {
        $this->artisan('rest:controller', ['name' => 'FqcnProductsController', '--resource' => ProductResource::class, '--no-interaction' => true])
            ->doesntExpectOutputToContain('does not exist')
            ->doesntExpectOutputToContain('Set $resource')
            ->assertSuccessful();

        $this->loadGeneratedClass(FqcnProductsController::class);

        $this->assertSame(ProductResource::class, FqcnProductsController::resourceClass());
    }

    #[Test]
    #[DataProvider('invalidResources')]
    public function it_fails_before_writing_anything_when_the_resource_is_not_a_valid_class_name(string $resource): void
    {
        $this->artisan('rest:controller', ['name' => 'InvalidResourceController', '--resource' => $resource, '--no-interaction' => true])
            ->expectsOutputToContain("[{$resource}] is not a valid class name.")
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    /** @return array<string, array{string}> */
    public static function invalidResources(): array
    {
        return [
            'dash' => ['Post-1'],
            'leading digit' => ['1PostResource'],
            'trailing slash' => ['PostResource/'],
            'double slash' => ['Admin//PostResource'],
        ];
    }

    #[Test]
    public function it_guesses_the_resource_from_the_controller_name_when_the_resource_exists(): void
    {
        $this->artisan('rest:resource', ['name' => 'GuessedTagResource', '--model' => 'Tag', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:controller', ['name' => 'GuessedTagsController', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No resource was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(GuessedTagsController::class);

        $this->assertSame('App\Rest\Resources\GuessedTagResource', GuessedTagsController::resourceClass());
    }

    #[Test]
    public function it_guesses_a_nested_resource_from_a_nested_controller_name(): void
    {
        $this->artisan('rest:resource', ['name' => 'Admin/NestedGuessTagResource', '--model' => 'Tag', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:controller', ['name' => 'Admin/NestedGuessTagsController', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No resource was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(NestedGuessTagsController::class);

        $this->assertSame('App\Rest\Resources\Admin\NestedGuessTagResource', NestedGuessTagsController::resourceClass());
    }

    #[Test]
    public function it_guesses_the_resource_from_a_fully_qualified_controller_name(): void
    {
        $this->artisan('rest:resource', ['name' => 'QualifiedGuessTagResource', '--model' => 'Tag', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:controller', ['name' => 'App\Rest\Controllers\QualifiedGuessTagsController', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No resource was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(QualifiedGuessTagsController::class);

        $this->assertSame('App\Rest\Resources\QualifiedGuessTagResource', QualifiedGuessTagsController::resourceClass());
    }

    #[Test]
    public function it_leaves_the_resource_out_and_warns_when_no_resource_is_given_or_found(): void
    {
        $this->artisan('rest:controller', ['name' => 'StrayController', '--no-interaction' => true])
            ->expectsOutputToContain('No resource was given or found for this controller. Pass --resource to set it.')
            ->expectsOutputToContain('Set $resource before you register this controller with Rest::resource(): the routes cannot register without it.')
            ->assertSuccessful();

        $this->loadGeneratedClass(StrayController::class);
        $this->assertSame(
            "<?php\n\nnamespace App\Rest\Controllers;\n\nclass StrayController extends Controller {}\n",
            file_get_contents(app_path('Rest/Controllers/StrayController.php')),
        );

        $this->expectExceptionObject(InvalidControllerException::missingResource(StrayController::class));

        StrayController::resourceClass();
    }

    #[Test]
    public function it_treats_an_empty_resource_as_no_resource(): void
    {
        $this->artisan('rest:controller', ['name' => 'BlankResourceController', '--resource' => '', '--no-interaction' => true])
            ->expectsOutputToContain('No resource was given or found for this controller. Pass --resource to set it.')
            ->assertSuccessful();

        $this->loadGeneratedClass(BlankResourceController::class);

        $this->expectExceptionObject(InvalidControllerException::missingResource(BlankResourceController::class));

        BlankResourceController::resourceClass();
    }

    #[Test]
    #[DataProvider('resourcesNamedLikeAnImport')]
    public function it_aliases_a_resource_whose_short_name_the_controller_already_uses(string $name, string $resource, string $alias): void
    {
        $this->artisan('rest:controller', ['name' => $name, '--resource' => $resource, '--no-interaction' => true])->assertSuccessful();

        $contents = file_get_contents(app_path("Rest/Controllers/{$name}.php"));
        $this->assertStringContainsString("use App\\Rest\\Resources\\{$resource} as {$alias};\n", $contents);
        $this->assertStringContainsString("public static string \$resource = {$alias}::class;\n", $contents);

        $controller = "App\\Rest\\Controllers\\{$name}";
        $this->loadGeneratedClass($controller);
        $this->assertSame("App\\Rest\\Resources\\{$resource}", $controller::resourceClass());
        $this->assertSame('App\Rest\Controllers\Controller', get_parent_class($controller));
    }

    /** @return array<string, array{string, string, string}> */
    public static function resourcesNamedLikeAnImport(): array
    {
        return [
            'the base controller' => ['AliasedLegacyController', 'Controller', 'ControllerResource'],
            'the controller itself' => ['AliasedReport', 'AliasedReport', 'AliasedReportResource'],
        ];
    }
}
