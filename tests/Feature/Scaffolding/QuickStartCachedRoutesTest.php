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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[RunTestsInSeparateProcesses]
#[WithConfig('app.url', 'http://localhost')]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class QuickStartCachedRoutesTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string ERROR = 'Routes are cached. Run php artisan route:clear, then run this command again.';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Blog];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        file_put_contents($app->getCachedRoutesPath(), "<?php\n");
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
    public function it_stops_before_install_api_runs_when_the_routes_are_cached(): void
    {
        $output = $this->quickStartWithoutInteraction(['--model' => ['Post', 'Tag'], '--install-api' => true]);

        $this->assertSame("\n   ERROR  ".self::ERROR."\n\n", $output);
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
        $this->assertDirectoryDoesNotExist(app_path('Policies'));
        $this->assertFileDoesNotExist(base_path('routes/api.php'));
    }

    #[Test]
    public function it_appends_no_route_when_the_routes_are_cached(): void
    {
        $this->installApiRoutes();

        $output = $this->quickStartWithoutInteraction(['--model' => ['Post', 'Tag']]);

        $this->assertSame("\n   ERROR  ".self::ERROR."\n\n", $output);
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
        $this->assertDirectoryDoesNotExist(app_path('Policies'));
        $this->assertFileEquals(FakeApiInstallCommand::routesStub(), base_path('routes/api.php'));
    }

    #[Test]
    public function it_stops_before_asking_for_the_models_when_the_routes_are_cached(): void
    {
        $input = new ArrayInput([]);
        $input->setStream($this->emptyStream());

        $exitCode = Artisan::all()['rest:quick-start']->run($input, $output = new BufferedOutput);

        $this->assertSame("\n   ERROR  ".self::ERROR."\n\n", $this->summary($output->fetch()));
        $this->assertSame(1, $exitCode);

        $this->assertDirectoryDoesNotExist(app_path('Rest'));
        $this->assertDirectoryDoesNotExist(app_path('Policies'));
        $this->assertFileDoesNotExist(base_path('routes/api.php'));
    }

    /** @param  array<string, mixed>  $options */
    private function quickStartWithoutInteraction(array $options): string
    {
        $exitCode = Artisan::call('rest:quick-start', [...$options, '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(1, $exitCode, $contents);

        return $contents;
    }

    /** @return resource */
    private function emptyStream()
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);

        return $stream;
    }
}
