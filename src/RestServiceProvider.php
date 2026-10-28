<?php

namespace Lomkit\Rest;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Console\Commands\ActionMakeCommand;
use Lomkit\Rest\Console\Commands\BaseControllerMakeCommand;
use Lomkit\Rest\Console\Commands\BaseResourceMakeCommand;
use Lomkit\Rest\Console\Commands\ControllerMakeCommand;
use Lomkit\Rest\Console\Commands\InstructionMakeCommand;
use Lomkit\Rest\Console\Commands\QuickStartCommand;
use Lomkit\Rest\Console\Commands\ResourceMakeCommand;
use Lomkit\Rest\Console\Commands\ResponseMakeCommand;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Scramble\LomkitLaravelRestApiOperationExtension;

class RestServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rest.php',
            'rest'
        );

        $this->registerServices();

        $this->registerScramble();
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

        $this->registerStubs();
    }

    /**
     * Register the Scramble extension before Scramble boots and reads its extension list.
     * Scramble's form request extractor fails on the trait actions, so it skips the package requests.
     */
    private function registerScramble(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::registerExtension(LomkitLaravelRestApiOperationExtension::class);

        FormRequestParametersExtractor::ignoreInstanceOf(RestRequest::class);
    }

    /**
     * Register the Rest Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BaseControllerMakeCommand::class,
                ControllerMakeCommand::class,
                BaseResourceMakeCommand::class,
                ResourceMakeCommand::class,
                ResponseMakeCommand::class,
                QuickStartCommand::class,
                ActionMakeCommand::class,
                InstructionMakeCommand::class,
            ]);
        }
    }

    /**
     * Register the stubs on the default laravel stub publish command.
     */
    protected function registerStubs(): void
    {
        Event::listen(function (PublishingStubs $event) {
            $event->add(realpath(__DIR__.'/Console/stubs/action.stub'), 'rest.action.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/base-controller.stub'), 'rest.base-controller.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/base-resource.stub'), 'rest.base-resource.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/controller.stub'), 'rest.controller.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/instruction.stub'), 'rest.instruction.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/resource.stub'), 'rest.resource.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/response.stub'), 'rest.response.stub');
        });
    }

    /**
     * Register the package's publishable resources.
     */
    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/rest.php' => config_path('rest.php'),
            ], 'rest-config');
        }
    }

    /**
     * Register Rest's services in the container.
     */
    protected function registerServices(): void
    {
        $this->app->singleton('lomkit-rest', Rest::class);

        $this->app->bind(QueryBuilder::class, Builder::class);
        $this->app->bind(EloquentQueryBuilder::class, fn ($app, array $parameters) => $app->make(QueryBuilder::class, $parameters));
    }
}
