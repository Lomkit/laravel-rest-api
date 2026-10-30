<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Blog\Blog;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[RunTestsInSeparateProcesses]
#[WithConfig('app.url', 'http://localhost')]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class QuickStartRouteClashTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string CLASH = 'Routes of the application already use the URIs or names of the routes to add. Rename or remove them, or leave their models out, then run this command again.';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeModels('App', 'Post', 'Tag', 'User');
        $this->runChecksInThisProcess();
        $this->installApiRoutes();
    }

    #[Test]
    public function it_stops_before_any_write_when_an_api_resource_uses_the_same_routes(): void
    {
        $routes = $this->appendRoutes("Route::apiResource('posts', 'App\Http\Controllers\Api\PostController')->middleware('auth:sanctum');");

        $output = $this->quickStart(1, 'Post', 'Tag');

        $this->assertStringContainsString(self::CLASH, $output);
        $this->assertStringContainsString(
            "  ⇂ GET|HEAD /api/posts [posts.index] (App\Http\Controllers\Api\PostController@index) clashes with Rest::resource('posts')\n"
            ."  ⇂ DELETE /api/posts/{post} [posts.destroy] (App\Http\Controllers\Api\PostController@destroy) clashes with Rest::resource('posts')\n",
            $output,
        );
        $this->assertSame(2, substr_count($output, '⇂'));
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
        $this->assertDirectoryDoesNotExist(app_path('Policies'));
        $this->assertSame($routes, (string) file_get_contents(base_path('routes/api.php')));
    }

    #[Test]
    public function it_stops_when_routes_without_names_answer_the_same_uris(): void
    {
        $this->appendRoutes("Route::get('/posts', fn () => 'posts');\nRoute::delete('/posts', fn () => 'gone');\nRoute::post('/posts/actions/{name}', fn () => 'done');");

        $output = $this->quickStart(1, 'Post');

        $this->assertStringContainsString(
            "  ⇂ GET|HEAD /api/posts (Closure) clashes with Rest::resource('posts')\n"
            ."  ⇂ POST /api/posts/actions/{name} (Closure) clashes with Rest::resource('posts')\n"
            ."  ⇂ DELETE /api/posts (Closure) clashes with Rest::resource('posts')\n",
            $output,
        );
    }

    #[Test]
    public function it_compares_the_uris_under_the_api_prefix_of_the_application(): void
    {
        $this->appendRoutes("Route::get('/posts', fn () => 'posts');", 'api/v1');
        Route::get('/api/posts', fn () => 'web');

        $output = $this->quickStart(1, 'Post');

        $this->assertStringContainsString("  ⇂ GET|HEAD /api/v1/posts (Closure) clashes with Rest::resource('posts')\n", $output);
        $this->assertSame(1, substr_count($output, '⇂'));
    }

    #[Test]
    public function it_exposes_a_model_whose_uri_is_taken_outside_the_api_routes(): void
    {
        $this->appendRoutes("Route::get('/user/posts', fn () => 'posts');");
        Route::prefix('admin')->group(function (): void {
            Route::get('/posts', fn () => 'admin')->name('admin.posts.index');
            Route::delete('/posts', fn () => 'admin');
        });
        Route::domain('admin.blog.test')->get('/api/posts', fn () => 'admin');

        $output = $this->quickStart(0, 'Post');

        $this->assertStringContainsString('  routes/api.php ... posts behind auth:sanctum', $output);
    }

    private function appendRoutes(string $routes, string $prefix = 'api'): string
    {
        file_put_contents(base_path('routes/api.php'), "\n{$routes}\n", FILE_APPEND);
        $this->loadApiRoutes($prefix);

        return (string) file_get_contents(base_path('routes/api.php'));
    }

    private function quickStart(int $expectedExitCode, string ...$models): string
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => $models, '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame($expectedExitCode, $exitCode, $contents);

        return $contents;
    }
}
