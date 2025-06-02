<?php

namespace Lomkit\Rest;

use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Console\Commands\ActionCommand;
use Lomkit\Rest\Console\Commands\ActionMakeCommand;
use Lomkit\Rest\Console\Commands\BaseControllerCommand;
use Lomkit\Rest\Console\Commands\BaseControllerMakeCommand;
use Lomkit\Rest\Console\Commands\BaseResourceCommand;
use Lomkit\Rest\Console\Commands\BaseResourceMakeCommand;
use Lomkit\Rest\Console\Commands\ControllerCommand;
use Lomkit\Rest\Console\Commands\ControllerMakeCommand;
use Lomkit\Rest\Console\Commands\Documentation\DocumentationCommand;
use Lomkit\Rest\Console\Commands\Documentation\DocumentationProviderCommand;
use Lomkit\Rest\Console\Commands\Documentation\DocumentationServiceProviderMakeCommand;
use Lomkit\Rest\Console\Commands\InstructionCommand;
use Lomkit\Rest\Console\Commands\InstructionMakeCommand;
use Lomkit\Rest\Console\Commands\QuickStartCommand;
use Lomkit\Rest\Console\Commands\ResourceCommand;
use Lomkit\Rest\Console\Commands\ResourceMakeCommand;
use Lomkit\Rest\Console\Commands\ResponseCommand;
use Lomkit\Rest\Console\Commands\ResponseMakeCommand;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\ActionsRequest;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Query\Builder;

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

        $this->registerRoutes();

        $this->loadViewsFrom(
            __DIR__.'/../resources/views',
            'rest'
        );
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        });
    }

    /**
     * Get the Telescope route group configuration array.
     *
     * @return array
     */
    private function routeConfiguration(): array
    {
        return [
            'domain'     => config('rest.documentation.routing.domain'),
            'prefix'     => config('rest.documentation.routing.path'),
            'middleware' => config('rest.documentation.routing.middlewares', []),
        ];
    }

    /**
     * Register the Rest Artisan commands.
     *
     * @return void
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
                DocumentationCommand::class,
                DocumentationServiceProviderMakeCommand::class,
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
            $event->add(realpath(__DIR__.'/Console/stubs/rest-documentation-service-provider.stub'), 'rest.rest-documentation-service-provider.stub');
        });
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
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
     *
     * @return void
     */
    protected function registerServices(): void
    {
        $this->app->singleton('lomkit-rest', Rest::class);

        $this->app->bind(QueryBuilder::class, Builder::class);

        $this->app->singleton(RestRequest::class, RestRequest::class);
        $this->app->singleton(ActionsRequest::class, ActionsRequest::class);
        $this->app->singleton(DestroyRequest::class, DestroyRequest::class);
        $this->app->singleton(DetailsRequest::class, DetailsRequest::class);
        $this->app->singleton(ForceDestroyRequest::class, ForceDestroyRequest::class);
        $this->app->singleton(MutateRequest::class, MutateRequest::class);
        $this->app->singleton(OperateRequest::class, OperateRequest::class);
        $this->app->singleton(RestoreRequest::class, RestoreRequest::class);
        $this->app->singleton(SearchRequest::class, SearchRequest::class);
    }
}
