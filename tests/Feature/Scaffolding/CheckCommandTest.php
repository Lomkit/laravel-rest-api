<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Library\BookController;
use Lomkit\Rest\Tests\Fixtures\Library\LabelController;
use Lomkit\Rest\Tests\Fixtures\Library\StaffAuthenticate;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Article;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class CheckCommandTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    #[Test]
    public function it_counts_the_authenticated_routes_and_the_policies_found(): void
    {
        $this->expose('books', BookController::class, 'auth:sanctum');
        $this->expose('labels', LabelController::class, 'auth');

        $output = $this->check(0, BookController::class, LabelController::class);

        $this->assertSame("  checks (fresh process) ... 10 routes authenticated, 2 policies found\n", $output);
    }

    #[Test]
    public function it_counts_a_single_route_and_policy_in_the_singular(): void
    {
        Route::post('api/books/search', [BookController::class, 'search'])->middleware('auth:sanctum');

        $output = $this->check(0, BookController::class);

        $this->assertSame("  checks (fresh process) ... 1 route authenticated, 1 policy found\n", $output);
    }

    #[Test]
    public function it_fails_for_a_route_without_authentication(): void
    {
        $this->expose('books', BookController::class, 'auth:sanctum');
        Route::post('api/books/search', [BookController::class, 'search']);

        $output = $this->check(1, BookController::class);

        $this->assertStringContainsString("  checks (fresh process) ... FAILED\n", $output);
        $this->assertStringContainsString("  ⇂ POST /api/books/search is not behind an authentication middleware\n", $output);
        $this->assertSame(1, substr_count($output, '⇂'));
    }

    #[Test]
    public function it_fails_for_a_guard_that_is_not_defined(): void
    {
        $this->expose('books', BookController::class, 'auth:passport');

        $output = $this->check(1, BookController::class);

        $this->assertStringContainsString('  ⇂ POST /api/books/search authenticates with the [passport] guard, which is not defined', $output);
    }

    #[Test]
    public function it_accepts_a_subclass_of_the_authentication_middleware(): void
    {
        Route::aliasMiddleware('auth.staff', StaffAuthenticate::class);
        $this->expose('books', BookController::class, 'auth.staff:sanctum');

        $output = $this->check(0, BookController::class);

        $this->assertSame("  checks (fresh process) ... 5 routes authenticated, 1 policy found\n", $output);
    }

    #[Test]
    public function it_accepts_several_guards_that_are_defined(): void
    {
        $this->expose('books', BookController::class, 'auth:sanctum,web');

        $output = $this->check(0, BookController::class);

        $this->assertSame("  checks (fresh process) ... 5 routes authenticated, 1 policy found\n", $output);
    }

    #[Test]
    public function it_fails_for_one_guard_that_is_not_defined_among_several(): void
    {
        $this->expose('books', BookController::class, 'auth:sanctum,passport');

        $output = $this->check(1, BookController::class);

        $this->assertStringContainsString('  ⇂ POST /api/books/search authenticates with the [passport] guard, which is not defined', $output);
        $this->assertSame(5, substr_count($output, '⇂'));
    }

    #[Test]
    public function it_reports_the_check_and_the_search_route_as_json(): void
    {
        $this->expose('books', BookController::class, 'auth:sanctum');
        Route::prefix('api')->post('labels/search', [LabelController::class, 'search']);

        $exitCode = Artisan::call('rest:check', ['controllers' => [BookController::class, LabelController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            ['routes' => 5, 'policies' => 2, 'problems' => ['POST /api/labels/search is not behind an authentication middleware.'], 'search' => '/api/books/search'],
            json_decode($output->fetch(), true),
        );
    }

    #[Test]
    public function it_fails_for_a_controller_without_routes(): void
    {
        $output = $this->check(1, BookController::class);

        $this->assertStringContainsString('  ⇂ No route uses '.BookController::class.'. Register it with Rest::resource() in routes/api.php, and check that bootstrap/app.php loads routes/api.php', $output);
    }

    #[Test]
    public function it_fails_for_a_model_without_a_policy(): void
    {
        $this->writeModel('App\Models\Uncovered');
        $this->artisan('rest:resource', ['name' => 'UncoveredResource', '--model' => 'Uncovered', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:controller', ['name' => 'UncoveredController', '--resource' => 'UncoveredResource', '--no-interaction' => true])->assertSuccessful();
        $this->loadGeneratedClass('App\Rest\Controllers\UncoveredController');
        $this->expose('uncovered', 'App\Rest\Controllers\UncoveredController', 'auth:sanctum');

        $output = $this->check(1, 'App\Rest\Controllers\UncoveredController');

        $this->assertStringContainsString('  ⇂ No policy is found for App\Models\Uncovered. Generate one with php artisan rest:policy, or register it with Gate::policy()', $output);
        $this->assertSame(1, substr_count($output, '⇂'));
    }

    #[Test]
    public function it_fails_for_a_class_that_is_not_a_controller_of_the_package(): void
    {
        $output = $this->check(1, Article::class);

        $this->assertStringContainsString('  ⇂ '.Article::class.' is not a controller with a resource and its model', $output);
    }

    #[Test]
    public function it_fails_for_a_controller_without_a_resource(): void
    {
        $this->artisan('rest:controller', ['name' => 'UnboundController', '--no-interaction' => true])->assertSuccessful();
        $this->loadGeneratedClass('App\Rest\Controllers\UnboundController');

        $output = $this->check(1, 'App\Rest\Controllers\UnboundController');

        $this->assertStringContainsString('  ⇂ App\Rest\Controllers\UnboundController is not a controller with a resource and its model', $output);
    }

    #[Test]
    public function it_reads_the_middleware_aliases_of_the_http_kernel(): void
    {
        $this->expose('books', BookController::class, 'auth:sanctum');
        (fn () => $this->middleware = [])->call($this->app->make(Router::class));
        $this->app->forgetInstance(Kernel::class);

        $output = $this->check(0, BookController::class);

        $this->assertSame("  checks (fresh process) ... 5 routes authenticated, 1 policy found\n", $output);
    }

    #[Test]
    public function it_is_hidden_from_the_command_list(): void
    {
        $this->assertTrue(Artisan::all()['rest:check']->isHidden());
    }

    private function expose(string $name, string $controller, string $middleware): void
    {
        Route::prefix('api')->group(function () use ($name, $controller, $middleware): void {
            Rest::resource($name, $controller)
                ->middleware($middleware)
                ->register();
        });
    }

    private function check(int $expectedExitCode, string ...$controllers): string
    {
        $exitCode = Artisan::call('rest:check', ['controllers' => $controllers], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame($expectedExitCode, $exitCode, $contents);

        return $contents;
    }
}
