<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutesProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutesProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\FirstAclProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\SecondAclProvider;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;

final class SharedRouteNameTest extends PackageTestCase
{
    #[Before]
    #[After]
    public function resetTheStubs(): void
    {
        StampRoutes::reset();
        OtherStampRoutes::reset();
        ExtensionLog::reset();
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [StampRoutesProvider::class, OtherStampRoutesProvider::class, FirstAclProvider::class, SecondAclProvider::class, RestServiceProvider::class];
    }

    #[Test]
    #[WithConfig('rest.extensions', ['acl' => false])]
    public function it_refuses_an_unsettled_name_of_a_route_extension_when_routes_register(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::sharedName('stamp', [StampRoutes::class, OtherStampRoutes::class]));

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();
    }

    #[Test]
    #[WithConfig('rest.extensions', ['stamp' => OtherStampRoutes::class])]
    public function it_registers_routes_beside_an_unsettled_name_of_another_kind(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $route = $this->app['router']->getRoutes()->getByName('routed-tickets.search');
        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':b'], $route?->middleware());
        $this->assertSame([0, 1, []], [StampRoutes::$built, OtherStampRoutes::$built, ExtensionLog::$built]);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['stamp' => OtherStampRoutes::class])]
    public function it_refuses_the_unsettled_name_of_another_kind_on_request(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->expectExceptionObject(InvalidExtensionException::sharedName('acl', [NotingExtension::class, CountingExtension::class]));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
    }
}
