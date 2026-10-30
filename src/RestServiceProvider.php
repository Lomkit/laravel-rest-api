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
use Lomkit\Rest\Console\Generation\Stub;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Scramble\LomkitLaravelRestApiOperationExtension;

class RestServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/rest.php', 'rest');

        $this->registerServices();

        $this->registerScramble();
    }

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();

        $this->registerStubs();
    }

    private function registerScramble(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::registerExtension(LomkitLaravelRestApiOperationExtension::class);

        FormRequestParametersExtractor::ignoreInstanceOf(RestRequest::class);
    }

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

    protected function registerStubs(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(function (PublishingStubs $event): void {
            foreach (Stub::cases() as $stub) {
                $event->add($stub->packagePath(), $stub->publishedName());
            }
        });
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/rest.php' => config_path('rest.php'),
            ], 'rest-config');
        }
    }

    protected function registerServices(): void
    {
        $this->app->bind(QueryBuilder::class, Builder::class);
        $this->app->bind(EloquentQueryBuilder::class, fn ($app, array $parameters) => $app->make(QueryBuilder::class, $parameters));
    }
}
