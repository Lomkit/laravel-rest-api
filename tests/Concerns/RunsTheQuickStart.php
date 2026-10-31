<?php

namespace Lomkit\Rest\Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Console\QuickStart\ExposureCheck;
use Lomkit\Rest\Tests\Fixtures\Blog\FakeApiInstallCommand;

trait RunsTheQuickStart
{
    private const array MODELS = [
        'Post' => <<<'PHP'
            <?php

            namespace %s\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\Relations\BelongsTo;
            use Illuminate\Database\Eloquent\Relations\BelongsToMany;

            class Post extends Model
            {
                public function author(): BelongsTo
                {
                    return $this->belongsTo(User::class);
                }

                public function tags(): BelongsToMany
                {
                    return $this->belongsToMany(Tag::class);
                }
            }

            PHP,
        'Tag' => <<<'PHP'
            <?php

            namespace %s\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\Relations\BelongsToMany;

            class Tag extends Model
            {
                public function posts(): BelongsToMany
                {
                    return $this->belongsToMany(Post::class);
                }
            }

            PHP,
        'User' => <<<'PHP'
            <?php

            namespace %s\Models;

            use Illuminate\Foundation\Auth\User as Authenticatable;

            class User extends Authenticatable
            {
                protected $fillable = ['name', 'email'];

                protected $hidden = ['password'];
            }

            PHP,
        'Note' => <<<'PHP'
            <?php

            namespace %s\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\SoftDeletes;

            class Note extends Model
            {
                use SoftDeletes;
            }

            PHP,
    ];

    private const string ROUTES_WITH_POSTS = <<<'PHP'
        <?php

        use App\Rest\Controllers\PostsController;
        use Illuminate\Http\Request;
        use Illuminate\Support\Facades\Route;
        use Lomkit\Rest\Facades\Rest;

        Route::get('/user', function (Request $request) {
            return $request->user();
        })->middleware('auth:sanctum');

        Rest::resource('posts', PostsController::class)
            ->middleware('auth:sanctum');

        PHP;

    private function writeModels(string $namespace, string ...$models): void
    {
        (new Filesystem)->ensureDirectoryExists(app_path('Models'));

        foreach ($models as $model) {
            file_put_contents(app_path("Models/{$model}.php"), sprintf(self::MODELS[$model], $namespace));
        }
    }

    private function fakeApiInstall(bool $publishesRoutes = true, bool $succeeds = true): void
    {
        $this->app->make(Kernel::class)->registerCommand(new FakeApiInstallCommand($publishesRoutes, $succeeds));
    }

    private function runChecksInThisProcess(string $prefix = 'api'): void
    {
        Process::fake(['*rest:check*' => function (PendingProcess $process) use ($prefix): ProcessResult {
            $this->loadApiRoutes($prefix);
            $arguments = array_slice((array) $process->command, 3);
            $controllers = array_values(array_filter($arguments, fn (string $argument): bool => ! str_starts_with($argument, '--')));
            $this->app->make(HttpKernel::class);
            $report = $this->app->make(ExposureCheck::class)->run($controllers);

            return Process::result($report->toJson(), '', $report->passed() ? 0 : 1);
        }]);
    }

    private function loadApiRoutes(string $prefix = 'api'): void
    {
        Route::prefix($prefix)
            ->middleware('api')
            ->group(base_path('routes/api.php'));
    }

    private function installApiRoutes(): void
    {
        copy(FakeApiInstallCommand::routesStub(), base_path('routes/api.php'));
    }

    private function summary(string $output): string
    {
        $output = (string) preg_replace('/[ \t]+$/m', '', $output);

        return (string) preg_replace('/^  (\S.*?) \.+ (.*)$/m', '  $1 ... $2', $output);
    }
}
