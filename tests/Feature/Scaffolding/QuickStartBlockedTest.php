<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
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
class QuickStartBlockedTest extends ScaffoldingTestCase
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
        $this->runChecksInThisProcess();
    }

    #[Test]
    public function it_requires_a_model_without_interaction(): void
    {
        $this->fakeApiInstall();

        $output = $this->blockedQuickStart(['--install-api' => true]);

        $this->assertStringContainsString('Pass the models to expose with --model, for example --model=Post --model=Tag.', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_requires_consent_to_install_api_routing_without_interaction(): void
    {
        $this->fakeApiInstall();

        $output = $this->blockedQuickStart(['--model' => ['Post']]);

        $this->assertStringContainsString('API routing is not installed. Run php artisan install:api first, or pass --install-api.', $output);
        $this->assertStringNotContainsString('Published API routes file.', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_when_the_api_routing_install_is_declined(): void
    {
        $this->fakeApiInstall();

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsConfirmation('API routing is not installed. Run php artisan install:api now? It installs Laravel Sanctum.', 'no')
            ->expectsOutputToContain('API routing is not installed. Run php artisan install:api first, or pass --install-api.')
            ->assertFailed();

        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_when_install_api_does_not_publish_the_routes_file(): void
    {
        $this->fakeApiInstall(publishesRoutes: false);

        $output = $this->blockedQuickStart(['--model' => ['Post'], '--install-api' => true]);

        $this->assertStringContainsString('php artisan install:api did not create routes/api.php.', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_before_any_write_when_a_model_has_no_table(): void
    {
        $this->writeModel('App\Models\Draft');
        $this->fakeApiInstall();

        $output = $this->blockedQuickStart(['--model' => ['Post', 'Draft'], '--install-api' => true]);

        $this->assertStringContainsString('The table [drafts] of the model [App\Models\Draft] does not exist.', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_before_any_write_when_a_model_does_not_exist(): void
    {
        $this->fakeApiInstall();

        $output = $this->blockedQuickStart(['--model' => ['Post', 'Missing'], '--install-api' => true]);

        $this->assertStringContainsString('The model [App\Models\Missing] does not exist.', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_before_any_write_when_two_models_share_a_resource_name(): void
    {
        $this->writeModel('App\Models\Note');
        $this->writeModel('App\Models\Archive\Note');
        $this->fakeApiInstall();

        $output = $this->blockedQuickStart(['--model' => ['Note', 'Archive/Note'], '--install-api' => true]);

        $this->assertSame(
            "\n   ERROR  The models [App\Models\Note] and [App\Models\Archive\Note] would share the resource name [notes], so their routes would have the same URIs and names. Expose them in separate runs of this command, or rename one of the models.\n\n",
            $output,
        );
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_fails_when_the_checks_find_a_route_without_authentication(): void
    {
        $this->installApiRoutes();
        file_put_contents(base_path('routes/api.php'), "\nLomkit\Rest\Facades\Rest::resource('posts', App\Rest\Controllers\PostsController::class);\n", FILE_APPEND);

        $output = $this->blockedQuickStart(['--model' => ['Post']]);

        $this->assertStringContainsString('  routes/api.php ... kept', $output);
        $this->assertStringContainsString('  checks (fresh process) ... FAILED', $output);
        $this->assertStringContainsString("  ⇂ GET|HEAD /api/posts is not behind an authentication middleware\n  ⇂ POST /api/posts/search is not behind an authentication middleware\n", $output);
        $this->assertStringContainsString('The API is not ready yet: fix the problems above, then run this command again.', $output);
        $this->assertStringNotContainsString('Next:', $output);
    }

    #[Test]
    public function it_reports_the_skipped_relations_and_the_read_only_policies_of_a_run_that_fails_its_checks(): void
    {
        $this->installApiRoutes();
        file_put_contents(base_path('routes/api.php'), "\nLomkit\Rest\Facades\Rest::resource('posts', App\Rest\Controllers\PostsController::class);\n", FILE_APPEND);

        $output = $this->blockedQuickStart(['--model' => ['Post']]);

        $this->assertStringContainsString(
            "  ⇂ DELETE /api/posts is not behind an authentication middleware\n\n"
            ."  Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.\n"
            ."  Skipped: Post.tags: no resource for App\Models\Tag. Rerun with --model=Tag to expose tags.\n"
            ."  PostPolicy lets signed-in users read and refuses every write. Edit app/Policies before production.\n\n"
            .'   ERROR  The API is not ready yet: fix the problems above, then run this command again.',
            $output,
        );
    }

    #[Test]
    public function it_stops_before_any_write_when_gate_would_not_discover_the_policy_of_a_model_outside_the_application(): void
    {
        $this->fakeApiInstall();

        $output = $this->blockedQuickStart(['--model' => ['Post', User::class], '--install-api' => true]);

        $this->assertStringContainsString('Gate finds no policy for the model ['.User::class.'] and would not discover the policy [App\Policies\UserPolicy] that this command generates. Register a policy for the model with Gate::policy(), for example one generated with php artisan rest:policy, then run this command again.', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_stops_before_any_write_when_gate_guesses_another_policy_name_for_a_model(): void
    {
        Gate::guessPolicyNamesUsing(fn (string $model): string => 'App\Authorization\\'.class_basename($model).'Policy');
        $this->fakeApiInstall();

        $output = $this->blockedQuickStart(['--model' => ['Post'], '--install-api' => true]);

        $this->assertStringContainsString('Gate finds no policy for the model [App\Models\Post] and would not discover the policy [App\Policies\PostPolicy] that this command generates. Register a policy for the model with Gate::policy(), for example one generated with php artisan rest:policy, then run this command again.', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function it_fails_when_the_fresh_process_fails(): void
    {
        $this->installApiRoutes();
        Process::fake(['*rest:check*' => Process::result('', 'PHP Fatal error: Allowed memory size exhausted', 255)]);

        $output = $this->blockedQuickStart(['--model' => ['Post']]);

        $this->assertStringContainsString('PHP Fatal error: Allowed memory size exhausted', $output);
        $this->assertStringContainsString('The API is not ready yet', $output);
    }

    /** @param  array<string, mixed>  $options */
    private function blockedQuickStart(array $options): string
    {
        $exitCode = Artisan::call('rest:quick-start', [...$options, '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(1, $exitCode, $contents);

        return $contents;
    }

    private function assertNothingWritten(): void
    {
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
        $this->assertDirectoryDoesNotExist(app_path('Policies'));
        $this->assertFileDoesNotExist(base_path('routes/api.php'));
    }
}
