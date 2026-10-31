<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
class QuickStartExistingAppTest extends ScaffoldingTestCase
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

        $this->runChecksInThisProcess();
        $this->installApiRoutes();
    }

    #[Test]
    public function it_uses_the_namespace_of_the_application_on_every_run(): void
    {
        $this->useApplicationNamespace('MyApp');
        $this->writeModels('MyApp', 'Post', 'Tag', 'User');
        config(['auth.providers.users.model' => 'MyApp\Models\User']);

        $this->quickStart('Post', 'Tag');
        $output = $this->quickStart('Post', 'Tag');

        $this->assertStringContainsString('  checks (fresh process) ... 10 routes authenticated, 2 policies found', $output);
        $this->assertStringStartsWith("<?php\n\nnamespace MyApp\Rest\Resources;\n", $this->contents(app_path('Rest/Resources/PostResource.php')));
        $this->assertStringContainsString("use MyApp\Models\Post;\n", $this->contents(app_path('Rest/Resources/PostResource.php')));
        $this->assertStringContainsString("use MyApp\Rest\Resources\PostResource;\n", $this->contents(app_path('Rest/Controllers/PostsController.php')));
        $this->assertStringContainsString("namespace MyApp\Policies;\n\nuse MyApp\Models\Post;\nuse MyApp\Models\User;\n", $this->contents(app_path('Policies/PostPolicy.php')));
        $this->assertStringContainsString("use MyApp\Rest\Controllers\PostsController;\n", $this->contents(base_path('routes/api.php')));
        $this->assertStringContainsString('php artisan tinker --execute="echo MyApp\Models\User::first()', $output);

        foreach (['Rest/Resources/PostResource.php', 'Rest/Controllers/PostsController.php', 'Policies/PostPolicy.php'] as $path) {
            $this->assertStringNotContainsString('MyMyApp', $this->contents(app_path($path)));
        }
    }

    #[Test]
    public function it_finds_the_routes_of_a_controller_in_a_reformatted_routes_file(): void
    {
        $this->writeModels('App', 'Post', 'Tag', 'User');
        $routes = $this->contents(base_path('routes/api.php'))."\nRoute::middleware('auth:sanctum')->group(function () {\n"
            ."    \Lomkit\Rest\Facades\Rest::resource('posts', \App\Rest\Controllers\PostsController::class);\n"
            ."    \Lomkit\Rest\Facades\Rest::resource('tags', App\Rest\Controllers\TagsController::class);\n});\n";
        file_put_contents(base_path('routes/api.php'), $routes);

        $output = $this->quickStart('Post', 'Tag');

        $this->assertStringContainsString('  routes/api.php ... kept', $output);
        $this->assertSame($routes, $this->contents(base_path('routes/api.php')));
    }

    #[Test]
    public function it_adds_only_the_routes_of_controllers_that_are_not_registered(): void
    {
        $this->writeModels('App', 'Post', 'Tag', 'User');
        $routes = str_replace("use Illuminate\Http\Request;\n", "use App\Rest\Controllers\PostsController as Posts;\nuse Illuminate\Http\Request;\n", $this->contents(base_path('routes/api.php')))
            ."\nLomkit\Rest\Facades\Rest::resource('posts', Posts::class)->middleware('auth:sanctum');\n";
        file_put_contents(base_path('routes/api.php'), $routes);

        $output = $this->quickStart('Post', 'Tag');

        $this->assertStringContainsString('  routes/api.php ... tags behind auth:sanctum', $output);
        $this->assertStringContainsString('  checks (fresh process) ... 10 routes authenticated, 2 policies found', $output);
        $this->assertStringEndsWith("\nRest::resource('tags', TagsController::class)\n    ->middleware('auth:sanctum');\n", $this->contents(base_path('routes/api.php')));
    }

    #[Test]
    public function it_finds_the_models_of_an_application_without_a_models_directory(): void
    {
        rmdir(app_path('Models'));
        file_put_contents(app_path('Note.php'), "<?php\n\nnamespace App;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass Note extends Model {}\n");

        $output = $this->quickStart('Note');

        $this->assertStringContainsString('  checks (fresh process) ... 5 routes authenticated, 1 policy found', $output);
        $this->assertStringContainsString("use App\\Note;\n", $this->contents(app_path('Rest/Resources/NoteResource.php')));
        $this->assertStringContainsString("use App\\Note;\n", $this->contents(app_path('Policies/NotePolicy.php')));
    }

    #[Test]
    public function it_keeps_an_existing_policy_file(): void
    {
        $this->writeModels('App', 'Post', 'Tag', 'User');
        mkdir(app_path('Policies'));
        $policy = "<?php\n\nnamespace App\Policies;\n\nclass PostPolicy\n{\n    public function viewAny(): bool\n    {\n        return true;\n    }\n}\n";
        file_put_contents(app_path('Policies/PostPolicy.php'), $policy);

        $output = $this->quickStart('Post');

        $this->assertStringContainsString('  app/Policies/PostPolicy.php ... kept', $output);
        $this->assertStringNotContainsString('PostPolicy lets', $output);
        $this->assertSame($policy, $this->contents(app_path('Policies/PostPolicy.php')));
    }

    #[Test]
    public function it_keeps_an_existing_controller_file(): void
    {
        $this->writeModels('App', 'Post', 'Tag', 'User');
        mkdir(app_path('Rest/Controllers'), recursive: true);
        $controller = "<?php\n\nnamespace App\Rest\Controllers;\n\nuse App\Rest\Resources\PostResource as Posts;\n\nclass PostsController extends Controller\n{\n    public static string \$resource = Posts::class;\n}\n";
        file_put_contents(app_path('Rest/Controllers/PostsController.php'), $controller);

        $output = $this->quickStart('Post');

        $this->assertStringContainsString('  app/Rest/Controllers/PostsController.php ... kept', $output);
        $this->assertSame($controller, $this->contents(app_path('Rest/Controllers/PostsController.php')));
    }

    #[Test]
    public function it_keeps_the_policy_already_registered_for_a_model(): void
    {
        $this->writeModels('App', 'Post', 'Tag', 'User');
        mkdir(app_path('Policies/Blog'), recursive: true);
        file_put_contents(app_path('Policies/Blog/Editorial.php'), "<?php\n\nnamespace App\Policies\Blog;\n\nclass Editorial\n{\n    public function viewAny(): bool\n    {\n        return true;\n    }\n}\n");
        Gate::policy('App\Models\Post', 'App\Policies\Blog\Editorial');

        $output = $this->quickStart('Post', 'Tag');

        $this->assertStringContainsString("  app/Policies/Blog/Editorial.php ... kept\n  app/Policies/TagPolicy.php ... created (read-only)\n", $output);
        $this->assertStringContainsString('TagPolicy lets signed-in users read and refuses every write.', $output);
        $this->assertFileDoesNotExist(app_path('Policies/PostPolicy.php'));
    }

    #[Test]
    public function it_does_not_ask_for_api_tokens_when_the_user_model_has_them(): void
    {
        eval('namespace Laravel\Sanctum; trait HasApiTokens {}');
        $this->writeModels('App', 'Post', 'Tag');
        file_put_contents(app_path('Models/User.php'), "<?php\n\nnamespace App\Models;\n\nuse Illuminate\Foundation\Auth\User as Authenticatable;\nuse Laravel\Sanctum\HasApiTokens;\n\nclass User extends Authenticatable\n{\n    use HasApiTokens;\n}\n");

        $output = $this->quickStart('Tag');

        $this->assertStringNotContainsString('HasApiTokens', $output);
        $this->assertStringContainsString('  Next:', $output);
    }

    private function useApplicationNamespace(string $namespace): void
    {
        $composer = (array) json_decode($this->contents(base_path('composer.json')), true);
        file_put_contents(base_path('composer.json'), json_encode([...$composer, 'autoload' => ['psr-4' => ["{$namespace}\\" => 'app/']]]));
        (fn () => $this->namespace = null)->call($this->app);

        spl_autoload_register(static function (string $class) use ($namespace): void {
            $path = app_path(str_replace('\\', '/', Str::after($class, "{$namespace}\\")).'.php');

            if (str_starts_with($class, "{$namespace}\\") && is_file($path)) {
                require $path;
            }
        });
    }

    private function quickStart(string ...$models): string
    {
        $exitCode = Artisan::call('rest:quick-start', ['--model' => $models, '--no-interaction' => true], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame(0, $exitCode, $contents);

        return $contents;
    }

    private function contents(string $path): string
    {
        return (string) file_get_contents($path);
    }
}
