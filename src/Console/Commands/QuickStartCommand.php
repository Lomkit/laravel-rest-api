<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Concerns\FindsAvailableModels;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Process;
use Lomkit\Rest\Console\Generation\ClassReference;
use Lomkit\Rest\Console\Generation\ModelTable;
use Lomkit\Rest\Console\Generation\UserModel;
use Lomkit\Rest\Console\QuickStart\ApiRoutes;
use Lomkit\Rest\Console\QuickStart\AppNamespace;
use Lomkit\Rest\Console\QuickStart\ExposedModel;
use Lomkit\Rest\Console\QuickStart\ExposureReport;
use Lomkit\Rest\Console\QuickStart\Outro;
use Lomkit\Rest\Console\QuickStart\PlannedFile;
use Lomkit\Rest\Console\QuickStart\QuickStartPlan;
use Lomkit\Rest\Console\QuickStart\RouteConflicts;
use Lomkit\Rest\Console\QuickStart\RouteFile;
use Lomkit\Rest\Exceptions\InvalidClassReferenceException;
use Lomkit\Rest\Exceptions\InvalidModelTableException;
use Lomkit\Rest\Exceptions\UndiscoverablePolicyException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Illuminate\Support\artisan_binary;
use function Illuminate\Support\php_binary;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'rest:quick-start', description: 'Expose models through resources, controllers, read-only policies and authenticated API routes')]
class QuickStartCommand extends Command
{
    use FindsAvailableModels;

    protected $signature = 'rest:quick-start
        {--model=* : A model to expose, repeat the option to expose several models}
        {--install-api : Run php artisan install:api when the application has no API routes or no Laravel Sanctum}';

    protected $description = 'Expose models through resources, controllers, read-only policies and authenticated API routes';

    public function handle(Filesystem $files, Gate $gate, Router $router): int
    {
        $app = AppNamespace::of($this->laravel);
        $user = UserModel::configured($this->laravel->make(Repository::class), $app->root);
        $routes = new ApiRoutes($files, $this->laravel->basePath());
        $plan = $this->plan($app, $gate, $user, $routes);

        if ($plan === null || $this->clashes($plan, $routes, new RouteConflicts($router->getRoutes())) || ! $this->ensureApiRouting($plan, $routes)) {
            return self::FAILURE;
        }

        $created = array_values(array_filter($plan->files(), fn (PlannedFile $file): bool => $file->isNew($files)));

        foreach ($plan->files() as $file) {
            $this->components->twoColumnDetail($app->display($file->path), $file->write($files));
        }

        $added = $routes->append($plan);
        $this->components->twoColumnDetail(ApiRoutes::PATH, $added === [] ? 'kept' : implode(', ', $added).' behind '.RouteFile::MIDDLEWARE);

        $outro = new Outro($plan, $created, $app, $user, $this->appUrl());
        $report = $this->check($plan);

        if ($report === null) {
            $outro->renderUnfinished($this->output);
            $this->components->error('The API is not ready yet: fix the problems above, then run this command again.');

            return self::FAILURE;
        }

        $outro->render($this->output, $this->components, $report->search);

        return self::SUCCESS;
    }

    private function plan(AppNamespace $app, Gate $gate, string $user, ApiRoutes $routes): ?QuickStartPlan
    {
        $models = $this->models();

        if ($models === []) {
            $this->components->error('Pass the models to expose with --model, for example --model=Post --model=Tag.');

            return null;
        }

        try {
            $sources = [];

            foreach ($models as $model) {
                $source = ModelTable::of(ClassReference::fromInput($model, $app->root, $app->models()));
                $sources[$source->model::class] ??= $source;
            }

            $plan = QuickStartPlan::for(array_values($sources), $app, $gate, $user);
            $routes->ensureDistinctNames($plan);

            return $plan;
        } catch (InvalidClassReferenceException|InvalidModelTableException|UndiscoverablePolicyException $exception) {
            $this->components->error($exception->getMessage());

            return null;
        }
    }

    /** @return list<string> */
    private function models(): array
    {
        $models = array_values(array_filter((array) $this->option('model'), fn (mixed $model): bool => is_string($model) && $model !== ''));

        if ($models !== [] || ! $this->canPrompt() || $this->findAvailableModels() === []) {
            return $models;
        }

        $chosen = multiselect('Which models should the API expose?', $this->findAvailableModels(), required: 'Choose at least one model.');

        return array_values(array_map(strval(...), $chosen));
    }

    private function clashes(QuickStartPlan $plan, ApiRoutes $routes, RouteConflicts $conflicts): bool
    {
        $clashes = array_merge(...array_map(fn (ExposedModel $model): array => $conflicts->of($model->uri()), $routes->unregistered($plan)));

        if ($clashes === []) {
            return false;
        }

        $this->components->error('Routes of the application already use the URIs or names of the routes to add. Rename or remove them, or leave their models out, then run this command again.');
        $this->components->bulletList($clashes);

        return true;
    }

    private function ensureApiRouting(QuickStartPlan $plan, ApiRoutes $routes): bool
    {
        if ($routes->exist()) {
            return $this->ensureSanctum($plan, $routes);
        }

        if (! $this->agreesToInstallApi('API routing is not installed. Run php artisan install:api now? It installs Laravel Sanctum.')) {
            $this->components->error('API routing is not installed. Run php artisan install:api first, or pass --install-api.');

            return false;
        }

        return $this->installApi($routes);
    }

    private function ensureSanctum(QuickStartPlan $plan, ApiRoutes $routes): bool
    {
        if ($routes->unregistered($plan) === [] || $this->laravel->make(Repository::class)->get('auth.guards.sanctum') !== null) {
            return true;
        }

        if (! $this->agreesToInstallApi('Laravel Sanctum is not installed. Run php artisan install:api now to install it?')) {
            $this->components->error('Laravel Sanctum is not installed. Run php artisan install:api first, or pass --install-api.');

            return false;
        }

        $this->components->info('Running php artisan install:api to install Laravel Sanctum. It keeps routes/api.php, which already exists.');

        if ($this->call('install:api') === self::SUCCESS) {
            return true;
        }

        $this->components->error('php artisan install:api failed.');

        return false;
    }

    private function installApi(ApiRoutes $routes): bool
    {
        if ($this->call('install:api') === self::SUCCESS && $routes->exist()) {
            $this->components->twoColumnDetail(ApiRoutes::PATH, 'created by install:api');

            return true;
        }

        $this->components->error('php artisan install:api did not create routes/api.php.');

        return false;
    }

    private function agreesToInstallApi(string $question): bool
    {
        return $this->option('install-api') === true
            || ($this->canPrompt() && confirm($question, default: true));
    }

    private function canPrompt(): bool
    {
        return $this->input->isInteractive()
            && ((defined('STDIN') && stream_isatty(STDIN)) || $this->laravel->runningUnitTests());
    }

    private function check(QuickStartPlan $plan): ?ExposureReport
    {
        $result = Process::path($this->laravel->basePath())
            ->run([php_binary(), artisan_binary(), 'rest:check', ...$plan->controllers(), '--json', '--no-interaction']);
        $report = ExposureReport::fromJson($result->output());

        if ($report === null) {
            $this->output->write($result->output());
            $this->output->write($result->errorOutput());

            return null;
        }

        $report->render($this->components);

        return $report->passed() ? $report : null;
    }

    private function appUrl(): string
    {
        $url = $this->laravel->make(Repository::class)->get('app.url');

        return is_string($url) && $url !== '' ? $url : 'http://localhost';
    }
}
