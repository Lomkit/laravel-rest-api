<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\QuickStart\RouteFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RouteFileTest extends TestCase
{
    private const string CONTROLLER = 'App\Rest\Controllers\PostsController';

    private const string INSTALLED = <<<'PHP'
        <?php

        use Illuminate\Http\Request;
        use Illuminate\Support\Facades\Route;

        Route::get('/user', function (Request $request) {
            return $request->user();
        })->middleware('auth:sanctum');

        PHP;

    #[Test]
    #[DataProvider('registeredControllers')]
    public function it_finds_a_controller_however_its_class_is_written(string $routes): void
    {
        $this->assertTrue((new RouteFile("<?php\n\n{$routes}\n"))->registers(self::CONTROLLER));
    }

    /** @return array<string, array{string}> */
    public static function registeredControllers(): array
    {
        return [
            'fully qualified' => ["Rest::resource('posts', \App\Rest\Controllers\PostsController::class);"],
            'qualified' => ["Rest::resource('posts', App\Rest\Controllers\PostsController::class);"],
            'imported' => ["use App\Rest\Controllers\PostsController;\n\nRest::resource('posts', PostsController::class);"],
            'imported with a leading backslash' => ["use \App\Rest\Controllers\PostsController;\n\nRest::resource('posts', PostsController::class);"],
            'aliased' => ["use App\Rest\Controllers\PostsController as Posts;\n\nRest::resource('posts', Posts::class);"],
            'in a group' => ["Route::middleware('auth:sanctum')->group(function () {\n    Rest::resource(\n        'posts',\n        \App\Rest\Controllers\PostsController::class,\n    );\n});"],
            'imported in a group' => ["use App\Rest\Controllers\{PostsController, TagsController};\n\nRest::resource('posts', PostsController::class);"],
            'aliased in a group' => ["use App\Rest\{\n    Controllers\TagsController,\n    Controllers\PostsController as Posts,\n};\n\nRest::resource('posts', Posts::class);"],
            'imported in a list' => ["use App\Http\Controllers\HomeController, App\Rest\Controllers\PostsController;\n\nRest::resource('posts', PostsController::class);"],
            'through its imported namespace' => ["use App\Rest\Controllers;\n\nRest::resource('posts', Controllers\PostsController::class);"],
            'through an aliased namespace' => ["use App\Rest\Controllers as Api;\n\nRest::resource('posts', Api\PostsController::class);"],
            'with another case' => ["use App\Rest\Controllers\PostsController;\n\nRest::resource('posts', postscontroller::class);"],
        ];
    }

    #[Test]
    #[DataProvider('unregisteredControllers')]
    public function it_does_not_find_a_controller_that_no_route_uses(string $routes): void
    {
        $this->assertFalse((new RouteFile("<?php\n\n{$routes}\n"))->registers(self::CONTROLLER));
    }

    /** @return array<string, array{string}> */
    public static function unregisteredControllers(): array
    {
        return [
            'another controller' => ["Rest::resource('tags', \App\Rest\Controllers\TagsController::class);"],
            'a short name without import' => ["Rest::resource('posts', PostsController::class);"],
            'an import without route' => ['use App\Rest\Controllers\PostsController;'],
            'a comment' => ["// Rest::resource('posts', \App\Rest\Controllers\PostsController::class);"],
            'a closure import' => ["Route::get('/', function () use (\$posts) {\n    return PostsController::class;\n});"],
            'a trait of a class' => ["\$routes = new class {\n    use App\Rest\Controllers\PostsController;\n};\n\nRest::resource('posts', PostsController::class);"],
            'a function import' => ["use function App\Rest\Controllers\PostsController;\n\nRest::resource('posts', PostsController::class);"],
            'a function in a group import' => ["use App\Rest\Controllers\{TagsController, function PostsController};\n\nRest::resource('posts', PostsController::class);"],
            'another class of an imported namespace' => ["use App\Rest\Controllers;\n\nRest::resource('posts', Controllers\Admin\PostsController::class);"],
        ];
    }

    #[Test]
    public function it_appends_the_resource_behind_authentication_and_sorts_its_imports(): void
    {
        $routes = (new RouteFile(self::INSTALLED))
            ->withResource('posts', self::CONTROLLER)
            ->withResource('tags', 'App\Rest\Controllers\TagsController');

        $this->assertSame(
            "<?php\n\nuse App\Rest\Controllers\PostsController;\nuse App\Rest\Controllers\TagsController;\nuse Illuminate\Http\Request;\nuse Illuminate\Support\Facades\Route;\nuse Lomkit\Rest\Facades\Rest;\n\n"
            ."Route::get('/user', function (Request \$request) {\n    return \$request->user();\n})->middleware('auth:sanctum');\n\n"
            ."Rest::resource('posts', PostsController::class)\n    ->middleware('auth:sanctum');\n\n"
            ."Rest::resource('tags', TagsController::class)\n    ->middleware('auth:sanctum');\n",
            $routes->contents,
        );
        $this->assertTrue($routes->registers(self::CONTROLLER));
    }

    #[Test]
    #[DataProvider('filesWithoutImports')]
    public function it_imports_the_classes_after_the_opening_tag_of_a_file_without_imports(string $contents, string $expected): void
    {
        $this->assertSame($expected, (new RouteFile($contents))->withResource('posts', self::CONTROLLER)->contents);
    }

    /** @return array<string, array{string, string}> */
    public static function filesWithoutImports(): array
    {
        $imports = "use App\Rest\Controllers\PostsController;\nuse Lomkit\Rest\Facades\Rest;\n\n";
        $route = "Rest::resource('posts', PostsController::class)\n    ->middleware('auth:sanctum');\n";
        $strict = "declare(strict_types=1);\n\n";

        return [
            'an empty file' => ['<?php', "<?php\n\n".$imports.$route],
            'a file with code' => ["<?php\n\nRoute::view('/', 'welcome');\n", "<?php\n\n".$imports."Route::view('/', 'welcome');\n\n".$route],
            'a file with code after the opening tag' => ["<?php\nRoute::view('/', 'welcome');", "<?php\n\n".$imports."Route::view('/', 'welcome');\n\n".$route],
            'a file with a strict types declaration' => ["<?php\n\n{$strict}Route::view('/', 'welcome');\n", "<?php\n\n{$strict}{$imports}Route::view('/', 'welcome');\n\n".$route],
            'a declaration on the line of the opening tag' => ["<?php declare(strict_types=1);\nRoute::view('/', 'welcome');\n", "<?php declare(strict_types=1);\n\n{$imports}Route::view('/', 'welcome');\n\n".$route],
            'a file with a namespace' => ["<?php\n\nnamespace App\Routes;\n\n\Route::view('/', 'welcome');\n", "<?php\n\nnamespace App\Routes;\n\n{$imports}\Route::view('/', 'welcome');\n\n".$route],
            'a file with a group import' => ["<?php\n\nuse App\Http\Controllers\{AboutController, HomeController};\n\nRoute::view('/', 'welcome');\n", "<?php\n\nuse App\Http\Controllers\{AboutController, HomeController};\n{$imports}Route::view('/', 'welcome');\n\n".$route],
        ];
    }

    #[Test]
    public function it_reuses_the_imports_of_the_file(): void
    {
        $routes = new RouteFile("<?php\n\nuse App\Rest\Controllers\PostsController as Posts;\nuse Lomkit\Rest\Facades\Rest as Api;\n");

        $this->assertSame(
            "<?php\n\nuse App\Rest\Controllers\PostsController as Posts;\nuse Lomkit\Rest\Facades\Rest as Api;\n\nApi::resource('posts', Posts::class)\n    ->middleware('auth:sanctum');\n",
            $routes->withResource('posts', self::CONTROLLER)->contents,
        );
    }

    #[Test]
    public function it_sorts_the_imports_like_pint(): void
    {
        $routes = new RouteFile("<?php\n\nuse App\Rest\Controllers\AdminLogsController;\nuse Lomkit\Rest\Facades\Rest;\n");

        $this->assertStringStartsWith(
            "<?php\n\nuse App\Rest\Controllers\Admin\PostsController;\nuse App\Rest\Controllers\AdminLogsController;\nuse Lomkit\Rest\Facades\Rest;\n\n",
            $routes->withResource('posts', 'App\Rest\Controllers\Admin\PostsController')->contents,
        );
    }

    #[Test]
    #[DataProvider('takenShortNames')]
    public function it_qualifies_a_class_whose_short_name_a_group_or_a_list_imports(string $imports): void
    {
        $this->assertStringEndsWith(
            "\nRest::resource('posts', \App\Rest\Controllers\PostsController::class)\n    ->middleware('auth:sanctum');\n",
            (new RouteFile("<?php\n\n{$imports}\nuse Lomkit\Rest\Facades\Rest;\n"))->withResource('posts', self::CONTROLLER)->contents,
        );
    }

    /** @return array<string, array{string}> */
    public static function takenShortNames(): array
    {
        return [
            'a group' => ['use App\Http\Controllers\{HomeController, PostsController};'],
            'a list' => ['use App\Http\Controllers\HomeController, App\Http\Controllers\PostsController;'],
            'an alias in another case' => ['use App\Http\Controllers\PostController as postsController;'],
        ];
    }

    #[Test]
    public function it_qualifies_a_class_whose_short_name_is_taken(): void
    {
        $routes = new RouteFile("<?php\n\nuse App\Http\Controllers\PostsController;\nuse App\Support\Rest;\n");

        $this->assertSame(
            "<?php\n\nuse App\Http\Controllers\PostsController;\nuse App\Support\Rest;\n\n\Lomkit\Rest\Facades\Rest::resource('posts', \App\Rest\Controllers\PostsController::class)\n    ->middleware('auth:sanctum');\n",
            $routes->withResource('posts', self::CONTROLLER)->contents,
        );
    }
}
