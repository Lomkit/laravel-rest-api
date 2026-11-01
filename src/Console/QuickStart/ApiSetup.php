<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Contracts\Config\Repository;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

final readonly class ApiSetup
{
    public function __construct(
        private Command $console,
        private Repository $config,
        private ApiRoutes $routes,
        private ?ApiGuard $chosen,
        private bool $interactive,
        private bool $installs,
    ) {}

    public function guard(QuickStartPlan $plan): ?ApiGuard
    {
        if (! $this->routes->exist()) {
            return $this->installRouting($this->chosen ?? $this->ask());
        }

        $default = $this->chosen ?? ApiGuard::named(ApiGuard::SANCTUM, $this->config);

        if ($this->routes->unregistered($plan) === []) {
            return $this->existing($plan) ?? $default;
        }

        if (! $default->needsInstall($this->config)) {
            return $default;
        }

        $guard = $this->chosen ?? $this->ask();

        return $guard->needsInstall($this->config) ? $this->installPackage($guard) : $guard;
    }

    private function ask(): ApiGuard
    {
        if (! $this->interactive) {
            return ApiGuard::named(ApiGuard::SANCTUM, $this->config);
        }

        $answer = select('Which guard should protect the API routes?', ApiGuard::choices($this->config), default: ApiGuard::SANCTUM);

        return ApiGuard::named((string) $answer, $this->config);
    }

    private function existing(QuickStartPlan $plan): ?ApiGuard
    {
        $name = $this->routes->guardOf($plan);

        return $name === null ? null : ApiGuard::find($name, $this->config);
    }

    private function installRouting(ApiGuard $guard): ?ApiGuard
    {
        $package = $guard->kind->installs();
        $command = "php artisan {$package->installer()}";

        if (! $this->agrees("API routing is not installed. Run {$command} now? It installs {$package->value}.")) {
            $this->components()->error("API routing is not installed. Run {$command} first, or pass --install-api.");

            return null;
        }

        if ($this->console->call('install:api', $package->installOptions()) === Command::SUCCESS && $this->routes->exist()) {
            $this->remindGuard($guard, $package);
            $this->components()->twoColumnDetail(ApiRoutes::PATH, "created by {$package->installer()}");

            return $guard;
        }

        $this->components()->error("{$command} did not create routes/api.php.");

        return null;
    }

    private function installPackage(ApiGuard $guard): ?ApiGuard
    {
        $package = $guard->kind->installs();
        $command = "php artisan {$package->installer()}";

        if (! $this->agrees("{$package->value} is not installed. Run {$command} now to install it?")) {
            $this->components()->error("{$package->value} is not installed. Run {$command} first, or pass --install-api.");

            return null;
        }

        $this->components()->info("Running {$command} to install {$package->value}. It keeps routes/api.php, which already exists.");

        if ($this->console->call('install:api', $package->installOptions()) !== Command::SUCCESS) {
            $this->components()->error("{$command} failed.");

            return null;
        }

        $this->remindGuard($guard, $package);

        return $guard;
    }

    private function remindGuard(ApiGuard $guard, ApiPackage $package): void
    {
        $driver = $package->guardDriver();

        if ($driver !== null && ! $guard->isDefined($this->config)) {
            $this->components()->info("Define the {$guard->name} guard with the {$driver} driver in config/auth.php.");
        }
    }

    private function agrees(string $question): bool
    {
        return $this->installs || ($this->interactive && confirm($question, default: true));
    }

    private function components(): Factory
    {
        return $this->console->outputComponents();
    }
}
