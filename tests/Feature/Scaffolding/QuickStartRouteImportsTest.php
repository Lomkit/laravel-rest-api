<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Blog\Blog;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class QuickStartRouteImportsTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string PINT_KEEPING_GROUPS = '{"preset": "laravel", "rules": {"single_import_per_statement": {"group_to_single_imports": false}}}';

    private const string ROUTES = "Route::get('/tables', fn (Request \$request): JsonResponse => response()->json(Schema::getTables()));\n";

    private const string RESOURCE = "Rest::resource('posts', PostsController::class)\n    ->middleware('auth:sanctum');\n";

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
    #[DataProvider('groupedImports')]
    public function it_adds_imports_that_pint_accepts_to_routes_with_grouped_imports(string $imports, string $expected): void
    {
        file_put_contents(base_path('routes/api.php'), "<?php\n\n{$imports}\n\n".self::ROUTES);

        $this->artisan('rest:quick-start', ['--model' => ['Post'], '--no-interaction' => true])->assertSuccessful();

        $this->assertSame("<?php\n\n{$expected}\n\n".self::ROUTES."\n".self::RESOURCE, (string) file_get_contents(base_path('routes/api.php')));
        $result = $this->pint(base_path('routes/api.php'));
        $this->assertTrue($result->successful(), $result->output().$result->errorOutput());
    }

    /** @return array<string, array{string, string}> */
    public static function groupedImports(): array
    {
        $http = 'use Illuminate\Http\{JsonResponse, Request};';

        return [
            'grouped imports only' => [
                "{$http}\nuse Illuminate\Support\Facades\{Route, Schema};",
                "use App\Rest\Controllers\PostsController;\n{$http}\nuse Illuminate\Support\Facades\{Route, Schema};\nuse Lomkit\Rest\Facades\Rest;",
            ],
            'grouped and single imports' => [
                "{$http}\nuse Illuminate\Support\Facades\Route;\nuse Illuminate\Support\Facades\Schema;",
                "use App\Rest\Controllers\PostsController;\n{$http}\nuse Illuminate\Support\Facades\Route;\nuse Illuminate\Support\Facades\Schema;\nuse Lomkit\Rest\Facades\Rest;",
            ],
        ];
    }

    private function pint(string $path): ProcessResult
    {
        file_put_contents($config = base_path('../pint.json'), self::PINT_KEEPING_GROUPS);

        return Process::path(dirname(__DIR__, 3))
            ->timeout(300)
            ->run([PHP_BINARY, 'vendor/bin/pint', '--test', '--config', $config, '--cache-file', base_path('../pint.cache'), $path]);
    }
}
