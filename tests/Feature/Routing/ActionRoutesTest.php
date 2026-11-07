<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Routing\ResourceRegistrar as IlluminateResourceRegistrar;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketResource;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

class ActionRoutesTest extends HelpdeskTestCase
{
    /** @var array<string, string> */
    private array $laravelVerbs = [];

    #[Before]
    public function rememberLaravelVerbs(): void
    {
        $this->laravelVerbs = IlluminateResourceRegistrar::verbs();
        RoutedTicketResource::reset();
    }

    #[After]
    public function restoreLaravelVerbs(): void
    {
        (new ReflectionProperty(IlluminateResourceRegistrar::class, 'verbs'))->setValue(null, $this->laravelVerbs);
        RoutedTicketResource::reset();
    }

    #[Test]
    public function it_gives_each_action_a_route_with_its_middleware_before_the_route_of_unknown_actions(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)
            ->middleware('verified')
            ->middlewareFor('operate', 'auth')
            ->register();

        $this->assertSame([
            'routed-tickets.operate.close-tickets' => [
                'POST routed-tickets/actions/close-tickets',
                ['action' => 'close-tickets'],
                [EnforceExpectsJson::class, 'verified', 'auth', 'throttle:1,1'],
                [],
            ],
            'routed-tickets.operate.refresh-tickets' => [
                'POST routed-tickets/actions/refresh-tickets',
                ['action' => 'refresh-tickets'],
                [EnforceExpectsJson::class, 'verified', 'auth'],
                [],
            ],
            'routed-tickets.operate.report-outage' => [
                'POST routed-tickets/actions/report-outage',
                ['action' => 'report-outage'],
                [EnforceExpectsJson::class, 'verified', 'auth'],
                ['auth', 'verified'],
            ],
            'routed-tickets.operate.throttled-watch-tickets' => [
                'POST routed-tickets/actions/throttled-watch-tickets',
                ['action' => 'throttled-watch-tickets'],
                [EnforceExpectsJson::class, 'verified', 'auth', 'throttle:60,1'],
                ['verified'],
            ],
            'routed-tickets.operate' => [
                'POST routed-tickets/actions/{action}',
                [],
                [EnforceExpectsJson::class, 'verified', 'auth'],
                [],
            ],
        ], $this->operateRoutes());
        $this->assertSame(
            [EnforceExpectsJson::class],
            $this->app['router']->gatherRouteMiddleware($this->routeNamed('routed-tickets.operate.report-outage')),
        );
    }

    /** @param Closure(): mixed $register */
    #[Test]
    #[DataProvider('authenticatedRegistrations')]
    public function it_runs_an_action_that_drops_the_auth_middleware_for_a_guest(Closure $register): void
    {
        $register();

        $this->postJson('/routed-tickets/actions/report-outage')
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->postJson('/routed-tickets/actions/refresh-tickets')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /** @return iterable<string, array{Closure(): mixed}> */
    public static function authenticatedRegistrations(): iterable
    {
        yield 'auth on the resource' => [fn () => Rest::resource('routed-tickets', RoutedTicketController::class)
            ->middleware('auth')
            ->register()];
        yield 'auth on the route group' => [fn () => RouteFacade::middleware('auth')->group(function (): void {
            Rest::resource('routed-tickets', RoutedTicketController::class);
        })];
    }

    #[Test]
    public function it_throttles_the_action_that_declares_it_and_not_the_others(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)->register();

        $this->postJson('/routed-tickets/actions/close-tickets')->assertOk();
        $this->postJson('/routed-tickets/actions/close-tickets')
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Too Many Attempts.']);
        $this->postJson('/routed-tickets/actions/refresh-tickets')->assertOk();
        $this->postJson('/routed-tickets/actions/refresh-tickets')->assertOk();
    }

    /**
     * @param  Closure(): mixed  $change
     * @param  array<string, string>  $body
     */
    #[Test]
    #[DataProvider('unroutedActions')]
    public function it_answers_an_action_without_a_route_as_an_unknown_action(Closure $change, string $key, ?Response $listing, int $status, array $body): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        Rest::resource('routed-tickets', RoutedTicketController::class)->register();
        $change();

        if ($listing !== null) {
            $this->tickets->answers['viewAny'] = $listing;
        }

        $this->postJson("/routed-tickets/actions/{$key}")
            ->assertStatus($status)
            ->assertExactJson($body);

        $this->assertSame('open', $jam->fresh()?->status);
        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    /** @return iterable<string, array{Closure(): mixed, string, ?Response, int, array<string, string>}> */
    public static function unroutedActions(): iterable
    {
        $denied = Response::deny('Tickets are not listed.');

        yield 'declared after the routes, user who can list' => [fn () => RoutedTicketResource::$late = true, 'late-close-tickets', null, 404, ['message' => '']];
        yield 'declared after the routes, user who cannot list' => [fn () => RoutedTicketResource::$late = true, 'late-close-tickets', $denied, 403, ['message' => 'Tickets are not listed.']];
        yield 'no longer declared, user who can list' => [fn () => RoutedTicketResource::$dropped = true, 'refresh-tickets', null, 404, ['message' => '']];
        yield 'no longer declared, user who cannot list' => [fn () => RoutedTicketResource::$dropped = true, 'refresh-tickets', $denied, 403, ['message' => 'Tickets are not listed.']];
    }

    #[Test]
    public function it_rejects_two_actions_with_one_uri_key(): void
    {
        RoutedTicketResource::$duplicated = true;
        $before = $this->app['router']->getRoutes()->count();

        try {
            Rest::resource('routed-tickets', RoutedTicketController::class)->register();
            $this->fail('The registration was not rejected.');
        } catch (InvalidResourceRouteException $exception) {
            $this->assertSame('Two endpoints of the resource use the route [POST routed-tickets/actions/close-tickets].', $exception->getMessage());
        }

        $this->assertSame($before, $this->app['router']->getRoutes()->count());
    }

    /**
     * @param  Closure(PendingResourceRegistration): mixed  $configure
     * @param  array<string, string>  $verbs
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('namings')]
    public function it_names_the_action_routes_after_the_operate_route(array $verbs, Closure $configure, array $expected): void
    {
        RouteFacade::resourceVerbs($verbs);
        $pending = Rest::resource('routed-tickets', RoutedTicketController::class)->only('operate');
        $configure($pending);
        $pending->register();

        $this->assertSame($expected, array_map(fn (array $route): string => $route[0], $this->operateRoutes()));
    }

    /** @return iterable<string, array{array<string, string>, Closure(PendingResourceRegistration): mixed, array<string, string>}> */
    public static function namings(): iterable
    {
        yield 'a verb' => [['actions' => 'run'], fn (PendingResourceRegistration $pending) => $pending, [
            'routed-tickets.operate.close-tickets' => 'POST routed-tickets/run/close-tickets',
            'routed-tickets.operate.refresh-tickets' => 'POST routed-tickets/run/refresh-tickets',
            'routed-tickets.operate.report-outage' => 'POST routed-tickets/run/report-outage',
            'routed-tickets.operate.throttled-watch-tickets' => 'POST routed-tickets/run/throttled-watch-tickets',
            'routed-tickets.operate' => 'POST routed-tickets/run/{action}',
        ]];
        yield 'a name prefix' => [[], fn (PendingResourceRegistration $pending) => $pending->names('desk'), [
            'desk.operate.close-tickets' => 'POST routed-tickets/actions/close-tickets',
            'desk.operate.refresh-tickets' => 'POST routed-tickets/actions/refresh-tickets',
            'desk.operate.report-outage' => 'POST routed-tickets/actions/report-outage',
            'desk.operate.throttled-watch-tickets' => 'POST routed-tickets/actions/throttled-watch-tickets',
            'desk.operate' => 'POST routed-tickets/actions/{action}',
        ]];
        yield 'an operate name' => [[], fn (PendingResourceRegistration $pending) => $pending->name('operate', 'helpdesk.run'), [
            'helpdesk.run.close-tickets' => 'POST routed-tickets/actions/close-tickets',
            'helpdesk.run.refresh-tickets' => 'POST routed-tickets/actions/refresh-tickets',
            'helpdesk.run.report-outage' => 'POST routed-tickets/actions/report-outage',
            'helpdesk.run.throttled-watch-tickets' => 'POST routed-tickets/actions/throttled-watch-tickets',
            'helpdesk.run' => 'POST routed-tickets/actions/{action}',
        ]];
    }

    /** @return array<string, array{string, array<string, mixed>, list<mixed>, list<string>}> */
    private function operateRoutes(): array
    {
        $routes = [];

        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            if ($route->getActionName() === RoutedTicketController::class.'@operate') {
                $routes[$route->getName() ?? ''] = [
                    implode('|', $route->methods()).' '.$route->uri(),
                    $route->defaults,
                    $route->middleware(),
                    $route->excludedMiddleware(),
                ];
            }
        }

        return $routes;
    }

    private function routeNamed(string $name): Route
    {
        return $this->app['router']->getRoutes()->getByName($name) ?? $this->fail("The route [{$name}] is not registered.");
    }
}
