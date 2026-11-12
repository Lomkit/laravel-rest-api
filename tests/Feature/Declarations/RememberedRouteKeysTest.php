<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Testing\ForgetsRoutes;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\OtherDeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\PublicOutage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class RememberedRouteKeysTest extends DeclarationTestCase
{
    use ForgetsRoutes;

    /**
     * @param  Closure(): mixed  $first
     * @param  Closure(): mixed  $second
     */
    #[Test]
    #[DataProvider('registrations')]
    public function it_registers_from_the_remembered_routes_what_a_fresh_registration_gives(Closure $first, Closure $second): void
    {
        $this->app['config']->set('rest.routes.remember', true);
        $this->registeredBy($first);
        $remembered = $this->registeredBy($second);

        Rest::forgetRoutes();
        $this->app['config']->set('rest.routes.remember', false);

        $this->assertSame($this->registeredBy($second), $remembered);
    }

    /** @return array<string, array{Closure(): mixed, Closure(): mixed}> */
    public static function registrations(): array
    {
        $plain = fn () => Rest::resource('desk-tickets', DeskTicketController::class)->register();

        return [
            'another name' => [$plain, fn () => Rest::resource('help-tickets', DeskTicketController::class)->register()],
            'other verbs' => [$plain, function (): void {
                RouteFacade::resourceVerbs(['actions' => 'run', 'metrics' => 'stats']);
                Rest::resource('desk-tickets', DeskTicketController::class)->register();
            }],
            'other names' => [$plain, fn () => Rest::resource('desk-tickets', DeskTicketController::class)->names('desk')->name('operate', 'helpdesk.run')->register()],
            'other middleware' => [$plain, fn () => Rest::resource('desk-tickets', DeskTicketController::class)->middleware('verified')->middlewareFor('operate', 'auth')->register()],
            'another controller' => [$plain, fn () => Rest::resource('desk-tickets', OtherDeskTicketController::class)->register()],
            'the operate endpoint first' => [fn () => Rest::resource('desk-tickets', DeskTicketController::class)->only('operate')->register(), $plain],
            'the metric endpoint first' => [fn () => Rest::resource('desk-tickets', DeskTicketController::class)->only('metric')->register(), $plain],
        ];
    }

    #[Test]
    public function it_refuses_an_instance_whose_route_changed_since_it_was_remembered(): void
    {
        $this->app['config']->set('rest.routes.remember', true);
        DeskTicketResource::$moreActions = [PublicOutage::make()->withoutMiddleware('auth')];
        $this->registeredBy(fn () => RouteFacade::middleware('auth')->group(fn () => Rest::resource('desk-tickets', DeskTicketController::class)->register()));
        DeskTicketResource::$moreActions = [PublicOutage::make()];
        $this->registeredBy(fn () => RouteFacade::middleware('auth')->group(fn () => Rest::resource('desk-tickets', DeskTicketController::class)->register()));

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson('/desk-tickets/actions/public-outage'),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidActionException::staleRoute(PublicOutage::class)->getMessage(),
        );
    }

    #[Test]
    public function it_refuses_an_option_that_is_not_a_boolean(): void
    {
        $this->app['config']->set('rest.routes.remember', 'sometimes');

        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.routes.remember', 'a boolean'));

        Rest::resource('desk-tickets', DeskTicketController::class);
    }

    /**
     * @param  Closure(): mixed  $register
     * @return array<string, array{string, string, array<string, mixed>, list<mixed>, list<string>, mixed}>
     */
    private function registeredBy(Closure $register): array
    {
        $this->app['router']->setRoutes(new RouteCollection);
        $register();
        $routes = [];

        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            $routes[$route->getName() ?? ''] = self::describe($route);
        }

        return $routes;
    }

    /** @return array{string, string, array<string, mixed>, list<mixed>, list<string>, mixed} */
    private static function describe(Route $route): array
    {
        return [implode('|', $route->methods()).' '.$route->uri(), $route->getActionName(), $route->defaults, $route->middleware(), $route->excludedMiddleware(), $route->getAction('rest_declaration')];
    }
}
