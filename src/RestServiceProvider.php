<?php

namespace Lomkit\Rest;

use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Console\Commands\ActionCommand;
use Lomkit\Rest\Console\Commands\BaseControllerCommand;
use Lomkit\Rest\Console\Commands\BaseResourceCommand;
use Lomkit\Rest\Console\Commands\ControllerCommand;
use Lomkit\Rest\Console\Commands\DocumentationCommand;
use Lomkit\Rest\Console\Commands\DocumentationProviderCommand;
use Lomkit\Rest\Console\Commands\InstructionCommand;
use Lomkit\Rest\Console\Commands\QuickStartCommand;
use Lomkit\Rest\Console\Commands\ResourceCommand;
use Lomkit\Rest\Console\Commands\ResponseCommand;
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
        $this->registerStubs();
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
    private function routeConfiguration()
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
                DocumentationCommand::class,
                DocumentationProviderCommand::class,
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

    protected function registerStubs()
    {
        Event::listen(function (PublishingStubs $event)
        {
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-action.stub'), 'rest-action.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-base-controller.stub'), 'rest-base-controller.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-base-resource.stub'), 'rest-base-resource.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-controller.stub'), 'rest-controller.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-instruction.stub'), 'rest-instruction.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-resource.stub'), 'rest-resource.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-response.stub'), 'response.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-user-resource.stub'), 'rest-user-resource.stub');
            $event->add(realpath(__DIR__ . '/Console/stubs/rest-user-controller.stub'), 'rest-user-controller.stub');
        });
    }
}
