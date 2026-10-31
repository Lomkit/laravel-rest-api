<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
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
class QuickStartSanctumTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string QUESTION = 'Laravel Sanctum is not installed. Run php artisan install:api now to install it?';

    private const string REFUSAL = "\n   ERROR  Laravel Sanctum is not installed. Run php artisan install:api first, or pass --install-api.\n\n";

    private const string INSTALL = 'Running php artisan install:api to install Laravel Sanctum. It keeps routes/api.php, which already exists.';

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
    public function it_stops_without_interaction_when_laravel_sanctum_is_not_installed(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame(self::REFUSAL, $this->summary($output->fetch()));
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_when_no_terminal_can_answer_and_laravel_sanctum_is_not_installed(): void
    {
        $this->assertFalse(stream_isatty(STDIN));
        $this->app['env'] = 'local';

        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post']], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame(self::REFUSAL, $this->summary($output->fetch()));
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_when_the_laravel_sanctum_install_is_declined(): void
    {
        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsConfirmation(self::QUESTION, 'no')
            ->expectsOutputToContain('Laravel Sanctum is not installed. Run php artisan install:api first, or pass --install-api.')
            ->doesntExpectOutputToContain('API scaffolding installed.')
            ->assertFailed();

        $this->assertNothingWritten();
    }

    #[Test]
    public function it_installs_laravel_sanctum_without_asking_when_the_option_is_given(): void
    {
        $this->checksPassInTheFreshProcess();

        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            "\n   INFO  ".self::INSTALL."\n\n"
            ."   ERROR  API routes file already exists.\n\n"
            ."   INFO  API scaffolding installed. Please add the [Laravel\Sanctum\HasApiTokens] trait to your User model.\n\n"
            ."  app/Rest/Resources/Resource.php ... created\n"
            ."  app/Rest/Resources/PostResource.php ... created (6 fields, 0 relations)\n"
            ."  app/Rest/Controllers/Controller.php ... created\n"
            ."  app/Rest/Controllers/PostsController.php ... created\n"
            ."  app/Policies/PostPolicy.php ... created (read-only)\n"
            ."  routes/api.php ... posts behind auth:sanctum\n"
            ."  checks (fresh process) ... 5 routes authenticated, 1 policy found\n\n"
            ."  Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.\n"
            ."  Skipped: Post.tags: no resource for App\Models\Tag. Rerun with --model=Tag to expose tags.\n\n"
            ."   WARN  Add Laravel\Sanctum\HasApiTokens to App\Models\User.\n\n"
            ."  Next:\n"
            ."    php artisan tinker --execute=\"echo App\Models\User::first()->createToken('cli')->plainTextToken;\"\n"
            ."    curl -X POST http://localhost/api/posts/search -H \"Authorization: Bearer <token>\" -H \"Accept: application/json\" -H \"Content-Type: application/json\" -d '{\"search\":{\"limit\":10}}'\n"
            ."  PostPolicy lets signed-in users read and refuses every write. Edit app/Policies before production.\n",
            $this->summary($output->fetch()),
        );
        $this->assertSame(self::ROUTES_WITH_POSTS, $this->routes());
    }

    #[Test]
    public function it_installs_laravel_sanctum_when_the_install_is_confirmed(): void
    {
        $this->checksPassInTheFreshProcess();

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsConfirmation(self::QUESTION, 'yes')
            ->expectsOutputToContain(self::INSTALL)
            ->expectsOutputToContain('API routes file already exists.')
            ->expectsOutputToContain('API scaffolding installed.')
            ->assertSuccessful();

        $this->assertSame(self::ROUTES_WITH_POSTS, $this->routes());
        $this->assertFileExists(app_path('Rest/Controllers/PostsController.php'));
    }

    #[Test]
    public function it_stops_before_any_write_when_install_api_fails(): void
    {
        $this->fakeApiInstall(succeeds: false);

        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame("\n   INFO  ".self::INSTALL."\n\n   ERROR  php artisan install:api failed.\n\n", $this->summary($output->fetch()));
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_does_not_ask_for_laravel_sanctum_when_the_run_adds_no_route(): void
    {
        $routes = $this->routes()."\nLomkit\Rest\Facades\Rest::resource('posts', App\Rest\Controllers\PostsController::class)->middleware('auth:web');\n";
        file_put_contents(base_path('routes/api.php'), $routes);

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->doesntExpectOutputToContain('install:api')
            ->assertSuccessful();

        $this->assertSame($routes, $this->routes());
        $this->assertFileExists(app_path('Rest/Controllers/PostsController.php'));
    }

    #[Test]
    public function it_does_not_ask_for_laravel_sanctum_when_its_guard_is_defined(): void
    {
        config(['auth.guards.sanctum' => ['driver' => 'session', 'provider' => 'users']]);

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->doesntExpectOutputToContain('install:api')
            ->assertSuccessful();

        $this->assertSame(self::ROUTES_WITH_POSTS, $this->routes());
    }

    private function checksPassInTheFreshProcess(): void
    {
        Process::fake(['*rest:check*' => Process::result('{"routes":5,"policies":1,"problems":[],"search":"/api/posts/search"}')]);
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
