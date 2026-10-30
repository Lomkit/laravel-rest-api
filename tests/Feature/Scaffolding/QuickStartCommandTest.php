<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Filesystem\Filesystem;
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
class QuickStartCommandTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string FIRST_RUN = <<<'TXT'
          routes/api.php ... created by install:api
          app/Rest/Resources/Resource.php ... created
          app/Rest/Resources/PostResource.php ... created (6 fields, 1 relation)
          app/Rest/Resources/TagResource.php ... created (4 fields, 1 relation)
          app/Rest/Controllers/Controller.php ... created
          app/Rest/Controllers/PostsController.php ... created
          app/Rest/Controllers/TagsController.php ... created
          app/Policies/PostPolicy.php ... created (read-only)
          app/Policies/TagPolicy.php ... created (read-only)
          routes/api.php ... posts, tags behind auth:sanctum
          checks (fresh process) ... 10 routes authenticated, 2 policies found

          Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.

           WARN  Add Laravel\Sanctum\HasApiTokens to App\Models\User.

          Next:
            php artisan tinker --execute="echo App\Models\User::first()->createToken('cli')->plainTextToken;"
            curl -X POST http://localhost/api/posts/search -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"search":{"limit":10}}'
          PostPolicy and TagPolicy let signed-in users read and refuse every write. Edit app/Policies before production.

        TXT;

    private const string RERUN = <<<'TXT'
          app/Rest/Resources/Resource.php ... kept
          app/Rest/Resources/PostResource.php ... kept
          app/Rest/Resources/TagResource.php ... kept
          app/Rest/Controllers/Controller.php ... kept
          app/Rest/Controllers/PostsController.php ... kept
          app/Rest/Controllers/TagsController.php ... kept
          app/Policies/PostPolicy.php ... kept
          app/Policies/TagPolicy.php ... kept
          routes/api.php ... kept
          checks (fresh process) ... 10 routes authenticated, 2 policies found

           WARN  Add Laravel\Sanctum\HasApiTokens to App\Models\User.

          Next:

        TXT;

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
    public function it_exposes_the_models_chosen_in_the_prompt(): void
    {
        $this->artisan('rest:quick-start')
            ->expectsChoice('Which models should the API expose?', ['Post', 'Tag'], ['Post', 'Tag', 'User'])
            ->expectsConfirmation('API routing is not installed. Run php artisan install:api now? It installs Laravel Sanctum.', 'yes')
            ->expectsOutputToContain('Published API routes file.')
            ->expectsOutputToContain('Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Rest/Resources/PostResource.php'));
        $this->assertFileExists(app_path('Rest/Resources/TagResource.php'));
        $this->assertFileDoesNotExist(app_path('Rest/Resources/UserResource.php'));
        $this->assertFileDoesNotExist(app_path('Policies/UserPolicy.php'));
    }

    #[Test]
    public function it_reports_every_file_and_the_first_requests_without_interaction(): void
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => ['Post', 'Tag'], '--install-api' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertStringEndsWith("\n".self::FIRST_RUN, $this->summary($output->fetch()));
    }

    #[Test]
    public function it_writes_the_generated_classes_of_each_model(): void
    {
        $this->quickStart('Post', 'Tag');

        $this->assertStringContainsString("            BelongsToMany::make('tags', TagResource::class),\n", $this->contents('Rest/Resources/PostResource.php'));
        $this->assertStringContainsString("            BelongsToMany::make('posts', PostResource::class),\n", $this->contents('Rest/Resources/TagResource.php'));
        $this->assertStringContainsString("            Integer::make('author_id')->creationRules('required'),\n", $this->contents('Rest/Resources/PostResource.php'));
        $this->assertSame(
            "<?php\n\nnamespace App\Rest\Controllers;\n\nuse App\Rest\Resources\PostResource;\n\nclass PostsController extends Controller\n{\n    public static string \$resource = PostResource::class;\n}\n",
            $this->contents('Rest/Controllers/PostsController.php'),
        );
        $this->assertStringContainsString("    public function view(User \$user, Post \$post): bool\n    {\n        return true;\n    }", $this->contents('Policies/PostPolicy.php'));
        $this->assertStringContainsString("    public function create(User \$user): bool\n    {\n        return false;\n    }", $this->contents('Policies/PostPolicy.php'));
    }

    #[Test]
    public function it_keeps_every_file_when_it_runs_again(): void
    {
        $this->quickStart('Post', 'Tag');
        $files = $this->snapshot();

        $output = $this->quickStart('Post', 'Tag');

        $this->assertStringContainsString(self::RERUN, $output);
        $this->assertStringNotContainsString('created', $output);
        $this->assertStringNotContainsString('Policy let', $output);
        $this->assertSame($files, $this->snapshot());
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

    /** @return array<string, string> */
    private function snapshot(): array
    {
        $files = [base_path('routes/api.php') => (string) file_get_contents(base_path('routes/api.php'))];

        foreach ((new Filesystem)->allFiles(app_path()) as $file) {
            $files[$file->getPathname()] = $file->getContents();
        }

        ksort($files);

        return $files;
    }
}
