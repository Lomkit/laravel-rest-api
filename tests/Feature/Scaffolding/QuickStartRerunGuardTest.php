<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
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
class QuickStartRerunGuardTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string KEPT = "  app/Rest/Resources/Resource.php ... kept\n"
        ."  app/Rest/Resources/PostResource.php ... kept\n"
        ."  app/Rest/Controllers/Controller.php ... kept\n"
        ."  app/Rest/Controllers/PostsController.php ... kept\n"
        ."  app/Policies/PostPolicy.php ... kept\n"
        ."  routes/api.php ... kept\n"
        ."  checks (fresh process) ... 6 routes authenticated, 1 policy found\n\n";

    private const string SEARCH = '    curl -X POST http://localhost/api/posts/search%s -H "Accept: application/json" -H "Content-Type: application/json" -d \'{"search":{"limit":10}}\''."\n";

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
        $this->fakeApiInstall();
        $this->runChecksInThisProcess();
    }

    #[Test]
    public function it_prints_the_laravel_passport_outro_when_it_runs_again_after_the_api_guard_is_defined(): void
    {
        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsChoice(self::GUARD_QUESTION, 'api', self::GUARDS, strict: true)
            ->expectsConfirmation('API routing is not installed. Run php artisan install:api --passport now? It installs Laravel Passport.', 'yes')
            ->expectsOutputToContain('Define the api guard with the passport driver in config/auth.php.')
            ->expectsOutputToContain('The API is not ready yet: fix the problems above, then run this command again.')
            ->assertFailed();

        config(['auth.guards.api' => ['driver' => 'passport', 'provider' => 'users']]);

        $this->assertSame(
            self::KEPT
            ."   WARN  Add Laravel\Passport\HasApiTokens to App\Models\User.\n\n"
            ."  Next:\n"
            ."    php artisan tinker --execute=\"echo App\Models\User::first()->createToken('cli')->accessToken;\"\n"
            .sprintf(self::SEARCH, ' -H "Authorization: Bearer <token>"'),
            $this->rerun(),
        );
    }

    #[Test]
    public function it_names_the_guard_of_the_existing_routes_when_it_runs_again(): void
    {
        $this->installApiRoutes();
        Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'web', '--no-interaction' => true]);

        $this->assertSame(
            self::KEPT
            ."  Next:\n"
            ."    Authenticate with the web guard to call the API.\n"
            .sprintf(self::SEARCH, ''),
            $this->rerun(),
        );
    }

    #[Test]
    public function it_names_the_guard_of_the_existing_routes_rather_than_the_given_guard_when_it_adds_no_route(): void
    {
        $this->installApiRoutes();
        Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'web', '--no-interaction' => true]);

        $output = $this->rerun(['--guard' => 'api']);

        $this->assertStringContainsString("  Next:\n    Authenticate with the web guard to call the API.\n", $output);
        $this->assertStringNotContainsString('Laravel Passport', $output);
    }

    #[Test]
    #[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
    public function it_keeps_the_laravel_sanctum_outro_when_the_guard_of_the_existing_routes_is_not_chained_to_them(): void
    {
        $this->installApiRoutes();
        Artisan::call('rest:quick-start', ['--model' => ['Post'], '--no-interaction' => true]);
        $routes = (string) file_get_contents(base_path('routes/api.php'));
        $grouped = str_replace("Rest::resource('posts', PostsController::class)\n    ->middleware('auth:sanctum');", "Route::middleware('auth:sanctum')->group(function () {\n    Rest::resource('posts', PostsController::class);\n});", $routes);
        file_put_contents(base_path('routes/api.php'), $grouped);

        $this->assertNotSame($routes, $grouped);
        $this->assertStringContainsString(
            "  Next:\n    php artisan tinker --execute=\"echo App\Models\User::first()->createToken('cli')->plainTextToken;\"\n",
            $this->rerun(),
        );
    }

    /** @param  array<string, string>  $options */
    private function rerun(array $options = []): string
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--no-interaction' => true, ...$options], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(0, $exitCode, $contents);

        return $contents;
    }
}
