<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Lomkit\Rest\Console\Check\PerimeterCheck;
use Lomkit\Rest\Console\Check\RegisteredControllers;
use Lomkit\Rest\Console\QuickStart\ExposureCheck;
use Lomkit\Rest\Console\QuickStart\ExposureReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rest:check', description: 'Check the routes, policies, perimeters and action row checks of the resources')]
class CheckCommand extends Command
{
    protected $signature = 'rest:check
        {controllers?* : The controllers to check, every controller that a route uses by default}
        {--json : Print the report as JSON}';

    protected $description = 'Check the routes, policies, perimeters and action row checks of the resources';

    public function handle(): int
    {
        $this->registerHttpMiddleware();

        $controllers = $this->controllers();
        $access = $this->access($controllers);
        $rows = $this->laravel->make(PerimeterCheck::class)->run($controllers);

        if ($this->option('json') === true) {
            $this->output->writeln(json_encode([...$access->toArray(), ...$rows->toArray()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        } else {
            $access->render($this->components, 'routes and policies');
            $rows->render($this->components);
        }

        return $access->passed() && $rows->passed() ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function controllers(): array
    {
        $controllers = array_values(array_filter((array) $this->argument('controllers'), is_string(...)));

        return $controllers === [] ? $this->laravel->make(RegisteredControllers::class)->all() : $controllers;
    }

    /** @param list<string> $controllers */
    private function access(array $controllers): ExposureReport
    {
        if ($controllers === []) {
            return (new ExposureReport)->withProblem('No route uses a controller of the package. Register resources with Rest::resource() in routes/api.php, and check that bootstrap/app.php loads routes/api.php.');
        }

        return $this->laravel->make(ExposureCheck::class)->run($controllers);
    }

    private function registerHttpMiddleware(): void
    {
        $this->laravel->make(Kernel::class);
    }
}
