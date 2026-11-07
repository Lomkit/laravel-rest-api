<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Blog\Blog;
use Lomkit\Rest\Tests\Fixtures\Blog\FakeApiInstallCommand;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[RunTestsInSeparateProcesses]
#[WithConfig('app.url', 'http://localhost')]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
class QuickStartGuardTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string FILES = "  app/Rest/Resources/Resource.php ... created\n"
        ."  app/Rest/Resources/PostResource.php ... created (6 fields, 0 relations)\n"
        ."  app/Rest/Controllers/Controller.php ... created\n"
        ."  app/Rest/Controllers/PostsController.php ... created\n"
        ."  app/Policies/PostPolicy.php ... created (allows every ability)\n";

    private const string SKIPPED = "  Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.\n"
        ."  Skipped: Post.tags: no resource for App\Models\Tag. Rerun with --model=Tag to expose tags.\n";

    private const string SEARCH = 'curl -X POST http://localhost/api/posts/search%s -H "Accept: application/json" -H "Content-Type: application/json" -d \'{"search":{"limit":10}}\'';

    private const string REMINDER = "  PostPolicy lets signed-in users do everything. Edit app/Policies before production.\n";

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
        $this->installApiRoutes();
        $this->fakeApiInstall();
        $this->runChecksInThisProcess();
    }

    #[Test]
    public function it_protects_the_routes_with_a_defined_guard_and_names_it_in_the_outro(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'web', '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            self::FILES
            ."  routes/api.php ... posts behind auth:web\n"
            ."  checks (fresh process) ... 5 routes authenticated, 1 policy found\n\n"
            .self::SKIPPED
            ."  Next:\n"
            ."    Authenticate with the web guard to call the API.\n"
            .'    '.sprintf(self::SEARCH, '')."\n"
            .self::REMINDER,
            $this->summary($output->fetch()),
        );
        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:web');\n", $this->routes());
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'passport', 'provider' => 'users'])]
    public function it_protects_the_routes_with_laravel_passport_and_prints_its_token_command(): void
    {
        eval('namespace Laravel\Passport; class Passport {}');

        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            self::FILES
            ."  routes/api.php ... posts behind auth:api\n"
            ."  checks (fresh process) ... 5 routes authenticated, 1 policy found\n\n"
            .self::SKIPPED
            ."\n   WARN  Add Laravel\Passport\HasApiTokens to App\Models\User.\n\n"
            ."  Next:\n"
            ."    php artisan tinker --execute=\"echo App\Models\User::first()->createToken('cli')->accessToken;\"\n"
            .'    '.sprintf(self::SEARCH, ' -H "Authorization: Bearer <token>"')."\n"
            .self::REMINDER,
            $this->summary($output->fetch()),
        );
        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:api');\n", $this->routes());
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'token', 'provider' => 'users'])]
    public function it_treats_an_api_guard_with_another_driver_as_a_defined_guard(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(0, $exitCode, $contents);
        $this->assertStringStartsWith(self::FILES."  routes/api.php ... posts behind auth:api\n", $contents);
        $this->assertStringContainsString("  Next:\n    Authenticate with the api guard to call the API.\n", $contents);
        $this->assertStringNotContainsString('HasApiTokens', $contents);
    }

    #[Test]
    #[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
    public function it_protects_the_routes_with_the_given_guard_when_laravel_sanctum_is_installed(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'web', '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(0, $exitCode, $contents);
        $this->assertStringStartsWith(self::FILES."  routes/api.php ... posts behind auth:web\n", $contents);
        $this->assertStringNotContainsString('HasApiTokens', $contents);
        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:web');\n", $this->routes());
    }

    #[Test]
    #[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
    public function it_stops_when_laravel_passport_is_missing_even_if_laravel_sanctum_is_installed(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame("\n   ERROR  Laravel Passport is not installed. Run php artisan install:api --passport first, or pass --install-api.\n\n", $this->summary($output->fetch()));
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_before_any_write_when_the_guard_is_not_defined(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'admin', '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame("\n   ERROR  The [admin] guard is not defined in config/auth.php.\n\n", $this->summary($output->fetch()));
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_before_asking_for_the_models_when_the_guard_is_not_defined(): void
    {
        $this->artisan('rest:quick-start', ['--guard' => 'web.driver'])
            ->expectsOutputToContain('The [web.driver] guard is not defined in config/auth.php.')
            ->assertFailed();

        $this->assertNothingWritten();
    }

    #[Test]
    public function it_installs_api_routing_with_laravel_sanctum_for_a_defined_guard(): void
    {
        unlink(base_path('routes/api.php'));

        $this->artisan('rest:quick-start', ['--model' => ['Post'], '--guard' => 'web'])
            ->expectsConfirmation('API routing is not installed. Run php artisan install:api now? It installs Laravel Sanctum.', 'yes')
            ->expectsOutputToContain('Published API routes file.')
            ->expectsOutputToContain('API scaffolding installed. Please add the [Laravel\Sanctum\HasApiTokens] trait to your User model.')
            ->expectsOutputToContain('created by install:api')
            ->expectsOutputToContain('posts behind auth:web')
            ->assertSuccessful();

        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:web');\n", $this->routes());
    }

    private function routes(): string
    {
        return (string) file_get_contents(base_path('routes/api.php'));
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame((string) file_get_contents(FakeApiInstallCommand::routesStub()), $this->routes());
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
        $this->assertDirectoryDoesNotExist(app_path('Policies'));
    }
}
