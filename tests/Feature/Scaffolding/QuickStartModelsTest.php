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
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\BufferedOutput;

use function Illuminate\Support\php_binary;

#[RunTestsInSeparateProcesses]
#[WithConfig('app.url', 'http://localhost')]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class QuickStartModelsTest extends ScaffoldingTestCase
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
    public function it_maps_the_relations_between_the_models_of_one_run(): void
    {
        $output = $this->quickStart('Post', 'Tag', 'User');

        $this->assertStringContainsString("            BelongsTo::make('author', UserResource::class)->requiredOnCreation(),\n", $this->contents('Rest/Resources/PostResource.php'));
        $this->assertStringContainsString("            Integer::make('author_id')->readonly(),\n", $this->contents('Rest/Resources/PostResource.php'));
        $this->assertStringContainsString('  checks (fresh process) ... 15 routes authenticated, 3 policies found', $output);
        $this->assertStringNotContainsString('Skipped:', $output);
        $this->assertStringContainsString("\n  Note: User.password: hidden and not fillable on the model, left out.\n", $output);
        $this->assertStringContainsString('PostPolicy, TagPolicy and UserPolicy let signed-in users read', $output);
    }

    #[Test]
    public function it_adds_restore_and_force_delete_to_the_policy_of_a_soft_deletable_model(): void
    {
        $this->writeModels('App', 'Note');

        $output = $this->quickStart('Note');

        $this->assertStringContainsString('NotePolicy lets signed-in users read and refuses every write.', $output);
        $this->assertStringEndsWith(
            "    public function delete(User \$user, Note \$note): bool\n    {\n        return false;\n    }\n\n"
            ."    public function restore(User \$user, Note \$note): bool\n    {\n        return false;\n    }\n\n"
            ."    public function forceDelete(User \$user, Note \$note): bool\n    {\n        return false;\n    }\n}\n",
            $this->contents('Policies/NotePolicy.php'),
        );
    }

    #[Test]
    public function it_runs_the_checks_in_a_fresh_artisan_process(): void
    {
        $this->quickStart('Post', 'Tag');

        Process::assertRan(fn ($process): bool => $process->path === base_path() && $process->command === [
            php_binary(), 'artisan', 'rest:check', 'App\Rest\Controllers\PostsController', 'App\Rest\Controllers\TagsController', '--json', '--no-interaction',
        ]);
    }

    #[Test]
    public function it_prints_the_checks_with_the_width_and_colors_of_the_command(): void
    {
        Artisan::call('rest:quick-start', ['--model' => ['Tag'], '--install-api' => true, '--no-interaction' => true, '--ansi' => true], $output = new BufferedOutput(decorated: true));
        $lines = explode("\n", $output->fetch());
        $checks = array_values(preg_grep('/checks \(fresh process\)/', $lines) ?: []);
        $routes = array_values(preg_grep('/routes\/api\.php/', $lines) ?: []);

        $this->assertCount(1, $checks);
        $this->assertStringContainsString("\e[", $checks[0]);
        $this->assertSame(mb_strwidth(Helper::removeDecoration(new OutputFormatter, end($routes))), mb_strwidth(Helper::removeDecoration(new OutputFormatter, $checks[0])));
    }

    #[Test]
    public function it_writes_the_classes_of_a_nested_model_in_directories_of_the_same_name(): void
    {
        $this->writeModel('App\Models\Archive\Note');

        $output = $this->quickStart('Archive/Note');

        $this->assertStringContainsString("  app/Rest/Controllers/Archive/NotesController.php ... created\n  app/Policies/Archive/NotePolicy.php ... created (read-only)\n", $output);
        $this->assertStringContainsString('  checks (fresh process) ... 5 routes authenticated, 1 policy found', $output);
        $this->assertStringStartsWith(
            "<?php\n\nnamespace App\Rest\Controllers\Archive;\n\nuse App\Rest\Controllers\Controller;\nuse App\Rest\Resources\Archive\NoteResource;\n",
            $this->contents('Rest/Controllers/Archive/NotesController.php'),
        );
        $this->assertStringContainsString("use App\Rest\Controllers\Archive\NotesController;\n", (string) file_get_contents(base_path('routes/api.php')));
    }

    #[Test]
    public function it_sends_the_first_request_to_the_url_of_the_application(): void
    {
        config(['app.url' => 'https://blog.test/']);

        $output = $this->quickStart('Tag');

        $this->assertStringContainsString("\n    curl -X POST https://blog.test/api/tags/search -H ", $output);
    }

    #[Test]
    public function it_sends_the_first_request_to_the_search_route_that_the_application_registers(): void
    {
        $this->runChecksInThisProcess('api/v1');

        $output = $this->quickStart('Tag');

        $this->assertStringContainsString("\n    curl -X POST http://localhost/api/v1/tags/search -H ", $output);
    }

    #[Test]
    public function it_relates_a_model_to_the_resource_of_an_earlier_run(): void
    {
        $this->quickStart('Tag');

        $output = $this->quickStart('Post');

        $this->assertStringContainsString("            BelongsToMany::make('tags', TagResource::class),\n", $this->contents('Rest/Resources/PostResource.php'));
        $this->assertStringContainsString('  app/Rest/Resources/PostResource.php ... created (6 fields, 1 relation)', $output);
        $this->assertStringNotContainsString('Skipped: Post.tags', $output);
    }

    #[Test]
    public function it_exposes_a_model_given_twice_once(): void
    {
        $output = $this->quickStart('Post', 'App\Models\Post');

        $this->assertSame(1, substr_count($output, 'app/Rest/Resources/PostResource.php'));
        $this->assertSame(1, substr_count((string) file_get_contents(base_path('routes/api.php')), "Rest::resource('posts'"));
    }

    private function quickStart(string ...$models): string
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => $models, '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(0, $exitCode, $contents);

        return $contents;
    }

    private function contents(string $path): string
    {
        return (string) file_get_contents(app_path($path));
    }
}
