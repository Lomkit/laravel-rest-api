<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutesProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use PHPUnit\Framework\Attributes\Test;

class RuntimeRouteMiddlewareTest extends HelpdeskTestCase
{
    use ResetsExtensionStubs;

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

    #[Test]
    public function it_refuses_a_disabled_config_that_is_not_a_list_beside_a_runtime_route_extension(): void
    {
        config(['rest.extensions' => [], 'rest.disabled_extensions' => ['stamp' => StampRoutes::class]]);

        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.disabled_extensions', 'a list of extension class names'));

        try {
            Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();
        } finally {
            $this->assertNull($this->app['router']->getRoutes()->getByName('routed-tickets.search'));
        }
    }
}
