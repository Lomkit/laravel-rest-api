<?php

namespace Lomkit\Rest\Tests\Concerns;

use Closure;
use Illuminate\Routing\RouteCollection;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;

trait InspectsResourceRoutes
{
    /** @param (Closure(PendingResourceRegistration): mixed)|null $configure */
    private function registerDocuments(?Closure $configure = null): RouteCollection
    {
        $pending = Rest::resource('documents', DocumentController::class);

        if ($configure !== null) {
            $configure($pending);
        }

        return $pending->register();
    }

    /** @return array<string, string> */
    private function table(RouteCollection $routes): array
    {
        $table = [];

        foreach ($routes->getRoutes() as $route) {
            $table[$route->getName() ?? ''] = implode('|', $route->methods()).' '.$route->uri();
        }

        return $table;
    }

    private function routeCount(): int
    {
        return $this->app['router']->getRoutes()->count();
    }
}
