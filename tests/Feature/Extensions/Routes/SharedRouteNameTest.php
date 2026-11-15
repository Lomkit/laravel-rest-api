<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AppStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutesProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutesProvider;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\FirstAclProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\RivalAclExtension;
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
    #[WithConfig('rest.disabled_extensions', [StampRoutes::class])]
    public function it_registers_routes_beside_a_shared_name_of_another_kind(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $route = $this->app['router']->getRoutes()->getByName('routed-tickets.search');
        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':b'], $route?->middleware());
        $this->assertSame([0, 1, []], [StampRoutes::$built, OtherStampRoutes::$built, ExtensionLog::$built]);
    }

    #[Test]
    #[WithConfig('rest.disabled_extensions', [StampRoutes::class])]
    public function it_refuses_the_shared_name_of_another_kind_on_request(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->expectExceptionObject(InvalidExtensionException::sharedName('acl', [AclExtension::class, RivalAclExtension::class]));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
    }

    #[Test]
    #[WithConfig('rest.extensions', [AppStampRoutes::class])]
    #[WithConfig('rest.disabled_extensions', [StampRoutes::class, OtherStampRoutes::class, RivalAclExtension::class])]
    public function it_registers_the_middleware_of_a_subclass_that_replaces_a_package_route_extension(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $route = $this->app['router']->getRoutes()->getByName('routed-tickets.search');
        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':app'], $route?->middleware());
        $this->assertSame([AppStampRoutes::class], StampRoutes::$ran);
    }
}
