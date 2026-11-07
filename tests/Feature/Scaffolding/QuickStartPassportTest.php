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
class QuickStartPassportTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string REFUSAL = 'Laravel Passport is not installed. Run php artisan install:api --passport first, or pass --install-api.';

    private const string INSTALL = 'Running php artisan install:api --passport to install Laravel Passport. It keeps routes/api.php, which already exists.';

    private const string INSTALLED = 'API scaffolding installed. Please add the [Laravel\Passport\HasApiTokens] trait to your User model.';

    private const string HINT = 'Define the api guard with the passport driver in config/auth.php.';

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
    public function it_stops_without_interaction_when_laravel_passport_is_not_installed(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame("\n   ERROR  ".self::REFUSAL."\n\n", $this->summary($output->fetch()));
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_asks_only_for_laravel_passport_when_the_guard_is_given(): void
    {
        $this->artisan('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api'])
            ->expectsConfirmation('Laravel Passport is not installed. Run php artisan install:api --passport now to install it?', 'no')
            ->expectsOutputToContain(self::REFUSAL)
            ->assertFailed();

        $this->assertNothingWritten();
    }

    #[Test]
    public function it_installs_laravel_passport_without_asking_and_reminds_the_api_guard(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            "\n   INFO  ".self::INSTALL."\n\n"
            ."   ERROR  API routes file already exists.\n\n"
            .'   INFO  '.self::INSTALLED."\n\n"
            .'   INFO  '.self::HINT."\n\n"
            ."  app/Rest/Resources/Resource.php ... created\n"
            ."  app/Rest/Resources/PostResource.php ... created (6 fields, 0 relations)\n"
            ."  app/Rest/Controllers/Controller.php ... created\n"
            ."  app/Rest/Controllers/PostsController.php ... created\n"
            ."  app/Policies/PostPolicy.php ... created (allows every ability)\n"
            ."  routes/api.php ... posts behind auth:api\n"
            ."  checks (fresh process) ... FAILED\n"
            .$this->undefinedGuardProblems()
            ."\n  Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.\n"
            ."  Skipped: Post.tags: no resource for App\Models\Tag. Rerun with --model=Tag to expose tags.\n"
            ."  PostPolicy lets signed-in users do everything. Edit app/Policies before production.\n\n"
            ."   ERROR  The API is not ready yet: fix the problems above, then run this command again.\n\n",
            $this->summary($output->fetch()),
        );
        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:api');\n", $this->routes());
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'passport', 'provider' => 'users'])]
    public function it_does_not_remind_the_api_guard_when_it_is_defined(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(0, $exitCode, $contents);
        $this->assertStringStartsWith("\n   INFO  ".self::INSTALL."\n\n   ERROR  API routes file already exists.\n\n   INFO  ".self::INSTALLED."\n\n  app/", $contents);
        $this->assertStringNotContainsString(self::HINT, $contents);
    }

    #[Test]
    public function it_stops_before_any_write_when_install_api_with_passport_fails(): void
    {
        $this->fakeApiInstall(succeeds: false);

        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame("\n   INFO  ".self::INSTALL."\n\n   ERROR  php artisan install:api --passport failed.\n\n", $this->summary($output->fetch()));
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_installs_api_routing_with_laravel_passport_when_the_routes_file_is_missing(): void
    {
        unlink(base_path('routes/api.php'));

        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post'], '--guard' => 'api', '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertStringStartsWith(
            "\n   INFO  Published API routes file.\n\n"
            .'   INFO  '.self::INSTALLED."\n\n"
            .'   INFO  '.self::HINT."\n\n"
            ."  routes/api.php ... created by install:api --passport\n"
            ."  app/Rest/Resources/Resource.php ... created\n",
            $this->summary($output->fetch()),
        );
        $this->assertSame(str_replace('auth:sanctum', 'auth:api', self::ROUTES_WITH_POSTS), $this->routes());
    }

    private function undefinedGuardProblems(): string
    {
        $endpoints = ['GET|HEAD /api/posts', 'POST /api/posts/search', 'POST /api/posts/mutate', 'POST /api/posts/actions/{action}', 'POST /api/posts/metrics/{metric}', 'DELETE /api/posts'];

        return implode('', array_map(fn (string $endpoint): string => "  ⇂ {$endpoint} authenticates with the [api] guard, which is not defined\n", $endpoints));
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
