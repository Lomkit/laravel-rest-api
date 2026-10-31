<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Blog\Blog;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[RunTestsInSeparateProcesses]
#[WithConfig('app.url', 'http://localhost')]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class QuickStartPromptsTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

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
    public function it_installs_api_routing_without_asking_when_the_option_is_given(): void
    {
        $this->artisan('rest:quick-start', ['--model' => ['Post'], '--install-api' => true])
            ->expectsOutputToContain('Published API routes file.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_asks_only_about_api_routing_when_laravel_sanctum_is_not_installed_either(): void
    {
        config(['auth.guards.sanctum' => null]);
        Process::fake(['*rest:check*' => Process::result('{"routes":5,"policies":1,"problems":[],"search":"/api/posts/search"}')]);

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsConfirmation('API routing is not installed. Run php artisan install:api now? It installs Laravel Sanctum.', 'yes')
            ->expectsOutputToContain('Published API routes file.')
            ->doesntExpectOutputToContain('Laravel Sanctum is not installed.')
            ->assertSuccessful();

        $this->assertSame(self::ROUTES_WITH_POSTS, (string) file_get_contents(base_path('routes/api.php')));
    }

    #[Test]
    public function it_installs_laravel_sanctum_when_its_question_is_answered_with_the_default(): void
    {
        config(['auth.guards.sanctum' => null]);
        $this->installApiRoutes();
        Process::fake(['*rest:check*' => Process::result('{"routes":5,"policies":1,"problems":[],"search":"/api/posts/search"}')]);
        $answers = fopen('php://memory', 'r+');
        $this->assertIsResource($answers);
        fwrite($answers, "\n");
        rewind($answers);
        $input = new ArrayInput(['--model' => ['Post']]);
        $input->setStream($answers);

        $exitCode = Artisan::all()['rest:quick-start']->run($input, $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertSame(<<<'TXT'

              Laravel Sanctum is not installed. Run php artisan install:api now to install it? (yes/no) [yes]
            ❯
               INFO  Running php artisan install:api to install Laravel Sanctum. It keeps routes/api.php, which already exists.

               ERROR  API routes file already exists.

               INFO  API scaffolding installed. Please add the [Laravel\Sanctum\HasApiTokens] trait to your User model.

              app/Rest/Resources/Resource.php ... created
              app/Rest/Resources/PostResource.php ... created (6 fields, 0 relations)
              app/Rest/Controllers/Controller.php ... created
              app/Rest/Controllers/PostsController.php ... created
              app/Policies/PostPolicy.php ... created (read-only)
              routes/api.php ... posts behind auth:sanctum
              checks (fresh process) ... 5 routes authenticated, 1 policy found

              Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.
              Skipped: Post.tags: no resource for App\Models\Tag. Rerun with --model=Tag to expose tags.

               WARN  Add Laravel\Sanctum\HasApiTokens to App\Models\User.

              Next:
                php artisan tinker --execute="echo App\Models\User::first()->createToken('cli')->plainTextToken;"
                curl -X POST http://localhost/api/posts/search -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"search":{"limit":10}}'
              PostPolicy lets signed-in users read and refuses every write. Edit app/Policies before production.

            TXT, $this->summary($output->fetch()));
        $this->assertSame(self::ROUTES_WITH_POSTS, (string) file_get_contents(base_path('routes/api.php')));
    }

    #[Test]
    public function it_requires_a_model_when_the_application_has_none(): void
    {
        foreach (glob(app_path('Models/*.php')) ?: [] as $model) {
            unlink($model);
        }

        $this->artisan('rest:quick-start')
            ->expectsOutputToContain('Pass the models to expose with --model, for example --model=Post --model=Tag.')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    #[Test]
    public function it_requires_the_install_api_option_when_no_terminal_can_answer(): void
    {
        $output = $this->quickStartWithoutTerminal(['--model' => ['Post']]);

        $this->assertStringContainsString('API routing is not installed. Run php artisan install:api first, or pass --install-api.', $output);
        $this->assertStringNotContainsString('Published API routes file.', $output);
        $this->assertFileDoesNotExist(base_path('routes/api.php'));
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    #[Test]
    public function it_requires_a_model_when_no_terminal_can_answer(): void
    {
        $output = $this->quickStartWithoutTerminal(['--install-api' => true]);

        $this->assertStringContainsString('Pass the models to expose with --model, for example --model=Post --model=Tag.', $output);
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    /** @param  array<string, mixed>  $options */
    private function quickStartWithoutTerminal(array $options): string
    {
        $this->assertFalse(stream_isatty(STDIN));
        $this->app['env'] = 'local';

        $exitCode = Artisan::call('rest:quick-start', $options, $output = new BufferedOutput);
        $contents = $output->fetch();

        $this->assertSame(1, $exitCode, $contents);

        return $contents;
    }
}
