<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\Admin\NestedBaseController;
use App\Rest\Controllers\TopLevelBaseController;
use App\Rest\Resources\Admin\NestedBaseResource;
use App\Rest\Resources\TopLevelBaseResource;
use Illuminate\Support\Facades\Artisan;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

class BaseClassGenerationTest extends ScaffoldingTestCase
{
    #[Test]
    #[DataProvider('generators')]
    public function it_writes_the_base_class_after_the_target_and_reports_it(string $command, string $option, string $type, string $base): void
    {
        $exitCode = Artisan::call($command, ['name' => 'Reported'.$type, $option => 'Reported', '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/'.preg_quote("{$type} [app/Rest/{$type}s/Reported{$type}.php] created successfully.", '/').'.*'.preg_quote("Base {$type} [app/Rest/{$type}s/{$base}.php] created successfully.", '/').'/s',
            $output->fetch(),
        );

        $this->assertFileExists(app_path("Rest/{$type}s/{$base}.php"));
    }

    #[Test]
    #[DataProvider('generators')]
    public function it_writes_nothing_when_the_name_is_reserved(string $command, string $option, string $type, string $base): void
    {
        $this->artisan($command, ['name' => 'class', $option => 'Reserved', '--no-interaction' => true])
            ->expectsOutputToContain('The name "class" is reserved by PHP.')
            ->doesntExpectOutputToContain('does not exist')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    #[Test]
    #[DataProvider('generators')]
    public function it_keeps_an_existing_base_class_even_with_force(string $command, string $option, string $type, string $base): void
    {
        mkdir(app_path("Rest/{$type}s"), recursive: true);
        file_put_contents(app_path("Rest/{$type}s/{$base}.php"), '<?php // kept');

        $this->artisan($command, ['name' => 'Kept'.$type, $option => 'Kept', '--force' => true, '--no-interaction' => true])
            ->doesntExpectOutputToContain("Base {$type}")
            ->assertSuccessful();

        $this->assertSame('<?php // kept', file_get_contents(app_path("Rest/{$type}s/{$base}.php")));
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function generators(): array
    {
        return [
            'resource' => ['rest:resource', '--model', 'Resource', 'Resource'],
            'controller' => ['rest:controller', '--resource', 'Controller', 'Controller'],
        ];
    }

    #[Test]
    #[DataProvider('baseClassNames')]
    public function it_writes_nothing_when_the_name_is_the_one_of_the_base_class(string $command, string $name, string $base): void
    {
        $this->artisan($command, ['name' => $name, '--no-interaction' => true])
            ->expectsOutputToContain("The name \"{$name}\" is reserved for the base class [{$base}].")
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    /** @return array<string, array{string, string, string}> */
    public static function baseClassNames(): array
    {
        return [
            'base resource' => ['rest:resource', 'Resource', 'App\Rest\Resources\Resource'],
            'base resource in lower case' => ['rest:resource', 'resource', 'App\Rest\Resources\Resource'],
            'nested resource' => ['rest:resource', 'Admin/Resource', 'App\Rest\Resources\Resource'],
            'base controller' => ['rest:controller', 'Controller', 'App\Rest\Controllers\Controller'],
            'nested controller' => ['rest:controller', 'Admin/Controller', 'App\Rest\Controllers\Controller'],
        ];
    }

    #[Test]
    public function it_keeps_the_existing_base_resource_when_its_name_is_given_with_force(): void
    {
        $this->artisan('rest:resource', ['name' => 'ForcedNameResource', '--model' => 'Post', '--no-interaction' => true])->assertSuccessful();
        $base = file_get_contents(app_path('Rest/Resources/Resource.php'));

        $this->artisan('rest:resource', ['name' => 'Resource', '--force' => true, '--no-interaction' => true])->assertFailed();

        $this->assertSame($base, file_get_contents(app_path('Rest/Resources/Resource.php')));
        $this->assertStringContainsString('abstract class Resource extends RestResource', $base);
    }

    #[Test]
    public function it_imports_the_base_resource_only_for_nested_resources(): void
    {
        $this->artisan('rest:resource', ['name' => 'TopLevelBaseResource', '--model' => 'Post', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:resource', ['name' => 'Admin/NestedBaseResource', '--model' => 'Post', '--no-interaction' => true])->assertSuccessful();

        $this->loadGeneratedClass(TopLevelBaseResource::class);
        $this->loadGeneratedClass(NestedBaseResource::class);

        $this->assertStringNotContainsString('use App\Rest\Resources\Resource;', file_get_contents(app_path('Rest/Resources/TopLevelBaseResource.php')));
        $this->assertStringContainsString("namespace App\\Rest\\Resources;\n\nuse App\\Models\\Post;\nuse Lomkit\\Rest\\Fields\\ID;\nuse Lomkit\\Rest\\Http\\Requests\\RestRequest;\n\nclass TopLevelBaseResource extends Resource", file_get_contents(app_path('Rest/Resources/TopLevelBaseResource.php')));
        $this->assertStringContainsString('use App\Rest\Resources\Resource;', file_get_contents(app_path('Rest/Resources/Admin/NestedBaseResource.php')));
        $this->assertSame('App\Rest\Resources\Resource', get_parent_class(TopLevelBaseResource::class));
        $this->assertSame('App\Rest\Resources\Resource', get_parent_class(NestedBaseResource::class));
    }

    #[Test]
    public function it_imports_the_base_controller_only_for_nested_controllers(): void
    {
        $this->artisan('rest:controller', ['name' => 'TopLevelBaseController', '--resource' => 'PostResource', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:controller', ['name' => 'Admin/NestedBaseController', '--resource' => 'PostResource', '--no-interaction' => true])->assertSuccessful();

        $this->loadGeneratedClass(TopLevelBaseController::class);
        $this->loadGeneratedClass(NestedBaseController::class);

        $this->assertStringNotContainsString('use App\Rest\Controllers\Controller;', file_get_contents(app_path('Rest/Controllers/TopLevelBaseController.php')));
        $this->assertStringContainsString("namespace App\\Rest\\Controllers;\n\nuse App\\Rest\\Resources\\PostResource;\n\nclass TopLevelBaseController extends Controller", file_get_contents(app_path('Rest/Controllers/TopLevelBaseController.php')));
        $this->assertStringContainsString('use App\Rest\Controllers\Controller;', file_get_contents(app_path('Rest/Controllers/Admin/NestedBaseController.php')));
        $this->assertSame('App\Rest\Controllers\Controller', get_parent_class(TopLevelBaseController::class));
        $this->assertSame('App\Rest\Controllers\Controller', get_parent_class(NestedBaseController::class));
    }
}
