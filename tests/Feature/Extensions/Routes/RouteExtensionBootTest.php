<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [StampRoutes::class, 'Missing\Extension'])]
class RouteExtensionBootTest extends HelpdeskTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search');
    }

    #[Test]
    public function it_boots_and_migrates_beside_an_invalid_entry_of_another_kind(): void
    {
        $this->artisan('migrate', ['--pretend' => true])->assertSuccessful();

        $route = $this->app['router']->getRoutes()->getByName('routed-tickets.search');
        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':a'], $route?->middleware());
    }
}
