<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Lomkit\Rest\Console\QuickStart\RouteFile;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Blog\Blog;
use Lomkit\Rest\Tests\Fixtures\Blog\FakeApiInstallCommand;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Article;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Comment;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Edition;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Rule;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Topic;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\SplFileInfo;

class GeneratedCodeQualityTest extends ScaffoldingTestCase
{
    private const string LARASTAN_CONFIG = <<<'NEON'
        includes:
            - %s/vendor/larastan/larastan/extension.neon

        parameters:
            level: 10
            phpVersion: 80300
            checkMissingOverrideMethodAttribute: true
            tmpDir: %s
            paths:
                - %s
                - %s
            scanDirectories:
                - %s
        NEON;

    private const array GENERATED_FILES = [
        'Actions/LintedAction.php',
        'Controllers/Admin/LintedTagsController.php',
        'Controllers/Controller.php',
        'Controllers/LintedEntriesController.php',
        'Controllers/LintedLabelsController.php',
        'Controllers/LintedMemosController.php',
        'Controllers/LintedPostsController.php',
        'Controllers/LintedStrayController.php',
        'Instructions/LintedInstruction.php',
        'Resources/Admin/LintedTagResource.php',
        'Resources/Admin/LintedTopicResource.php',
        'Resources/LintedArticleResource.php',
        'Resources/LintedCommentResource.php',
        'Resources/LintedDeskResource.php',
        'Resources/LintedDraftResource.php',
        'Resources/LintedEditionResource.php',
        'Resources/LintedEntryResource.php',
        'Resources/LintedLabelResource.php',
        'Resources/LintedLibraryResource.php',
        'Resources/LintedMemoResource.php',
        'Resources/LintedPostResource.php',
        'Resources/LintedRuleResource.php',
        'Resources/LintedStoryResource.php',
        'Resources/Resource.php',
        'Responses/LintedResponse.php',
    ];

    private const array GENERATED_POLICIES = [
        'Admin/LintedTagPolicy.php',
        'LintedArticlePolicy.php',
        'LintedEntryPolicy.php',
        'LintedLabelPolicy.php',
        'LintedMemoPolicy.php',
        'LintedPostPolicy.php',
    ];

    private const string ENTRY_MODEL = <<<'PHP'
        protected $table = 'posts';

            public function labels(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
            {
                return $this->belongsToMany(LintedLabel::class, 'post_tag', 'post_id', 'tag_id');
            }
        PHP;

    private const string LABEL_MODEL = <<<'PHP'
        protected $table = 'tags';

            public function entries(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
            {
                return $this->belongsToMany(LintedEntry::class, 'post_tag', 'tag_id', 'post_id');
            }
        PHP;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom, new Accounts, new Blog];
    }

    #[Test]
    public function it_generates_classes_that_pass_pint_with_the_laravel_preset(): void
    {
        $this->generateEveryClass();

        $result = $this->runTool(['vendor/bin/pint', '--test', '--no-config', '--preset', 'laravel', '--cache-file', base_path('../pint.cache'), app_path('Rest'), app_path('Policies'), base_path('routes')]);

        $this->assertTrue($result->successful(), $result->output().$result->errorOutput());
    }

    #[Test]
    public function it_generates_classes_that_pass_larastan_at_level_10(): void
    {
        $this->generateEveryClass();
        file_put_contents(
            $config = base_path('../phpstan.neon'),
            sprintf(self::LARASTAN_CONFIG, $this->packagePath(), base_path('../phpstan'), app_path('Rest'), app_path('Policies'), app_path('Models')),
        );

        $result = $this->runTool(['vendor/bin/phpstan', 'analyse', '--configuration', $config, '--memory-limit', '1G', '--no-progress', '--error-format', 'raw']);

        $this->assertTrue($result->successful(), $result->output().$result->errorOutput());
    }

    private function generateEveryClass(): void
    {
        $this->writeModel('App\Models\LintedPost');
        $this->writeModel('App\Models\Admin\LintedTag');
        $this->writeModel('App\Models\Resource');

        $this->generate('rest:resource', ['name' => 'LintedPostResource', '--model' => 'LintedPost']);
        $this->generate('rest:resource', ['name' => 'Admin/LintedTagResource']);
        $this->generate('rest:resource', ['name' => 'LintedLibraryResource', '--model' => 'Resource']);
        $this->generate('rest:resource', ['name' => 'LintedArticleResource', '--model' => Article::class, '--generate' => true]);
        $this->generate('rest:resource', ['name' => 'LintedEditionResource', '--model' => Edition::class, '--generate' => true]);
        $this->generate('rest:resource', ['name' => 'LintedRuleResource', '--model' => Rule::class, '--generate' => true]);
        $this->generate('rest:resource', ['name' => 'LintedDeskResource', '--model' => Desk::class]);
        $this->generate('rest:resource', ['name' => 'Admin/LintedTopicResource', '--model' => Topic::class]);
        $this->generate('rest:resource', ['name' => 'LintedStoryResource', '--model' => Story::class, '--generate' => true]);
        $this->generate('rest:resource', ['name' => 'LintedCommentResource', '--model' => Comment::class, '--generate' => true]);
        $this->generate('rest:resource', ['name' => 'LintedDraftResource']);
        $this->generate('rest:controller', ['name' => 'LintedPostsController']);
        $this->generate('rest:controller', ['name' => 'Admin/LintedTagsController']);
        $this->generate('rest:controller', ['name' => 'LintedStrayController']);
        $this->generate('rest:action', ['name' => 'LintedAction']);
        $this->generate('rest:instruction', ['name' => 'LintedInstruction']);
        $this->generate('rest:response', ['name' => 'LintedResponse']);
        $this->generate('rest:policy', ['name' => 'LintedPostPolicy', '--model' => 'LintedPost']);
        $this->generate('rest:policy', ['name' => 'LintedArticlePolicy', '--model' => Article::class, '--force' => true]);
        $this->generate('rest:policy', ['name' => 'Admin/LintedTagPolicy']);
        $this->quickStart();
        file_put_contents(base_path('routes/rest.php'), (new RouteFile("<?php\n\ndeclare(strict_types=1);\n"))->withResource('admin-logs', 'App\Rest\Controllers\AdminLogsController')->withResource('admin-posts', 'App\Rest\Controllers\Admin\PostsController')->contents);

        $this->assertSame(self::GENERATED_FILES, $this->filesIn(app_path('Rest')));
        $this->assertSame(self::GENERATED_POLICIES, $this->filesIn(app_path('Policies')));
    }

    private function quickStart(): void
    {
        $this->writeModel('App\Models\LintedEntry', self::ENTRY_MODEL);
        $this->writeModel('App\Models\LintedLabel', self::LABEL_MODEL);
        $this->writeModel('App\Models\LintedMemo', "use \\Illuminate\\Database\\Eloquent\\SoftDeletes;\n\n    protected \$table = 'notes';");
        copy(FakeApiInstallCommand::routesStub(), base_path('routes/api.php'));
        config(['auth.guards.sanctum' => ['driver' => 'session', 'provider' => 'users']]);
        Process::fake(['*rest:check*' => Process::result('{"routes":15,"policies":3,"problems":[],"search":"/api/linted-entries/search"}')]);

        $this->generate('rest:quick-start', ['--model' => ['LintedEntry', 'LintedLabel', 'LintedMemo']]);

        $this->assertStringContainsString("\nRest::resource('linted-memos', LintedMemosController::class)\n    ->middleware('auth:sanctum');\n", (string) file_get_contents(base_path('routes/api.php')));
    }

    /** @return list<string> */
    private function filesIn(string $directory): array
    {
        $files = array_map(fn (SplFileInfo $file): string => $file->getRelativePathname(), (new Filesystem)->allFiles($directory));
        sort($files);

        return $files;
    }

    /** @param  array<string, string|bool|list<string>>  $arguments */
    private function generate(string $command, array $arguments): void
    {
        $this->artisan($command, [...$arguments, '--no-interaction' => true])->assertSuccessful();
    }

    /** @param  list<string>  $command */
    private function runTool(array $command): ProcessResult
    {
        return Process::path($this->packagePath())
            ->timeout(300)
            ->run([PHP_BINARY, ...$command]);
    }

    private function packagePath(): string
    {
        return dirname(__DIR__, 3);
    }
}
