<?php

namespace Lomkit\Rest;

use Illuminate\Support\ServiceProvider;
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
    }
}