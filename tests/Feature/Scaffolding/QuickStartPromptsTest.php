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
