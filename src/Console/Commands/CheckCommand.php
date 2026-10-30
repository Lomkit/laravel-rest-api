<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Lomkit\Rest\Console\QuickStart\ExposureCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rest:check', description: 'Check that the routes of controllers are authenticated and that their models have a policy', hidden: true)]
class CheckCommand extends Command
{
    protected $signature = 'rest:check
        {controllers* : The controllers to check}
        {--json : Print the report as JSON}';

    protected $description = 'Check that the routes of controllers are authenticated and that their models have a policy';

    protected $hidden = true;

    public function handle(): int
    {
        $this->registerHttpMiddleware();

        $controllers = (array) $this->argument('controllers');
        $report = $this->laravel->make(ExposureCheck::class)->run(array_values(array_filter($controllers, is_string(...))));

        if ($this->option('json') === true) {
            $this->output->writeln($report->toJson(), OutputInterface::OUTPUT_RAW);
        } else {
            $report->render($this->components);
        }

        return $report->passed() ? self::SUCCESS : self::FAILURE;
    }

    private function registerHttpMiddleware(): void
    {
        $this->laravel->make(Kernel::class);
    }
}
