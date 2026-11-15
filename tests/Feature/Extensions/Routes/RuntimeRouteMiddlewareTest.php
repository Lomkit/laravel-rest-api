<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutesProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;

class RuntimeRouteMiddlewareTest extends HelpdeskTestCase
{
    #[Before]
    #[After]
    public function resetTheStubs(): void
    {
        StampRoutes::reset();
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [StampRoutesProvider::class, RestServiceProvider::class];
    }

    #[Test]
    public function it_adds_the_middleware_of_a_runtime_extension(): void
    {
        config(['rest.extensions' => []]);
        StampRoutes::$resources = [RoutedTicketController::resourceClass()];

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $route = $this->app['router']->getRoutes()->getByName('routed-tickets.search');
        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':a'], $route?->middleware());
    }
}
