<?php

namespace Lomkit\Rest;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        $this->app->singleton('lomkit-rest', Rest::class);
        $this->app->bind(QueryBuilder::class, Builder::class);
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        //@TODO: for tests see https://github.com/rebing/graphql-laravel/tree/master/tests
    }
}