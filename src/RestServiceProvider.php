<?php

namespace Lomkit\Rest;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\Commands\ActionCommand;
use Lomkit\Rest\Console\Commands\BaseControllerCommand;
use Lomkit\Rest\Console\Commands\BaseResourceCommand;
use Lomkit\Rest\Console\Commands\ControllerCommand;
use Lomkit\Rest\Console\Commands\DocumentationCommand;
use Lomkit\Rest\Console\Commands\InstructionCommand;
use Lomkit\Rest\Console\Commands\QuickStartCommand;
use Lomkit\Rest\Console\Commands\ResourceCommand;
use Lomkit\Rest\Console\Commands\ResponseCommand;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Query\Builder;

class RestServiceProvider extends ServiceProvider{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rest.php', 'rest'
        );

        $this->registerServices();
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
        $this->registerPublishing();
    }

    /**
     * Register the Rest Artisan commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BaseControllerCommand::class,
                ControllerCommand::class,
                BaseResourceCommand::class,
                ResourceCommand::class,
                ResponseCommand::class,
                QuickStartCommand::class,
                ActionCommand::class,
                InstructionCommand::class,
                DocumentationCommand::class
            ]);
        }
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    private function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/rest.php' => config_path('rest.php'),
            ], 'rest-config');
        }
    }

    /**
     * Register Rest's services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        $this->app->singleton('lomkit-rest', Rest::class);
        $this->app->bind(QueryBuilder::class, Builder::class);
    }
}