<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Routing\ResourceRegistrar as IlluminateResourceRegistrar;
use Illuminate\Routing\Route;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Exceptions\InvalidRouteExtensionException;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Concerns\CompilesRoutes;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PlainLimits;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampingLimits;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use stdClass;

class ExtensionRouteMiddlewareTest extends HelpdeskTestCase
{
    use CompilesRoutes;

    private const string STAMP = StampsRequests::class.':a';

    private const array CATCH_ALL = [
        'routed-tickets.details',
        'routed-tickets.search',
        'routed-tickets.mutate',
        'routed-tickets.operate',
        'routed-tickets.metric',
        'routed-tickets.destroy',
    ];

    /** @var array<string, string> */
    private array $laravelVerbs = [];

    #[Before]
    public function resetTheStubs(): void
    {
        $this->laravelVerbs = IlluminateResourceRegistrar::verbs();
        RoutedTicketResource::reset();
        StampRoutes::reset();
        OtherStampRoutes::reset();
        StampsRequests::$stamps = [];
        ExtensionLog::reset();
        Applicability::flush();
    }

    #[After]
    public function restoreTheStubs(): void
    {
        (new ReflectionProperty(IlluminateResourceRegistrar::class, 'verbs'))->setValue(null, $this->laravelVerbs);
        RoutedTicketResource::reset();
        StampRoutes::reset();
        OtherStampRoutes::reset();
        StampsRequests::$stamps = [];
        ExtensionLog::reset();
        Applicability::flush();
    }

    #[Test]
    public function it_appends_extension_middleware_after_resource_and_endpoint_middleware(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)
            ->middleware('verified')
            ->middlewareFor('operate', 'auth')
            ->register();

        $this->assertSame([
            'routed-tickets.operate.close-tickets' => [[EnforceExpectsJson::class, 'verified', 'auth', self::STAMP, 'throttle:1,1'], []],
            'routed-tickets.operate.refresh-tickets' => [[EnforceExpectsJson::class, 'verified', 'auth', self::STAMP], []],
            'routed-tickets.operate.report-outage' => [[EnforceExpectsJson::class, 'verified', 'auth', self::STAMP], ['auth', 'verified']],
            'routed-tickets.operate.throttled-watch-tickets' => [[EnforceExpectsJson::class, 'verified', 'auth', self::STAMP, 'throttle:60,1'], ['verified']],
            'routed-tickets.operate' => [[EnforceExpectsJson::class, 'verified', 'auth', self::STAMP], []],
        ], $this->operateRoutes());
        $this->assertSame([
            'routed-tickets.details' => [EnforceExpectsJson::class, 'verified', self::STAMP],
            'routed-tickets.search' => [EnforceExpectsJson::class, 'verified', self::STAMP],
            'routed-tickets.mutate' => [EnforceExpectsJson::class, 'verified', self::STAMP],
            'routed-tickets.operate' => [EnforceExpectsJson::class, 'verified', 'auth', self::STAMP],
            'routed-tickets.metric' => [EnforceExpectsJson::class, 'verified', self::STAMP],
            'routed-tickets.destroy' => [EnforceExpectsJson::class, 'verified', self::STAMP],
        ], $this->catchAllMiddleware());
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_adds_middleware_in_name_order_whatever_the_config_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP, StampsRequests::class.':b'], $this->routeNamed('routed-tickets.search')->middleware());
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [['a' => StampRoutes::class, 'b' => OtherStampRoutes::class]],
            'in reverse order' => [['b' => OtherStampRoutes::class, 'a' => StampRoutes::class]],
        ];
    }

    #[Test]
    public function it_lists_a_middleware_once(): void
    {
        config(['rest.extensions' => ['a' => StampRoutes::class, 'b' => OtherStampRoutes::class]]);
        StampRoutes::$middleware = static fn (): array => ['verified', self::STAMP, 'verified'];
        OtherStampRoutes::$middleware = static fn (): array => [self::STAMP, 'verified'];

        Rest::resource('routed-tickets', RoutedTicketController::class)->middleware('verified')->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, 'verified', self::STAMP], $this->routeNamed('routed-tickets.search')->middleware());
    }

    #[Test]
    public function it_cannot_remove_middleware(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);
        StampRoutes::$middleware = static fn (): array => ['verified'];

        Rest::resource('routed-tickets', RoutedTicketController::class)->middleware('auth')->register();

        foreach (self::CATCH_ALL as $name) {
            $this->assertSame([EnforceExpectsJson::class, 'auth', 'verified'], $this->routeNamed($name)->middleware());
            $this->assertSame([], $this->routeNamed($name)->excludedMiddleware());
        }

        $this->postJson('/routed-tickets/search')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertSame(['routeMiddleware'], get_class_methods(AddsRouteMiddleware::class));
        $this->assertSame(['resource', 'endpoint', 'controller', 'routeName'], array_keys(get_class_vars(RouteMiddlewareContext::class)));
    }

    #[Test]
    public function it_lets_an_action_exclude_extension_middleware(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);
        StampRoutes::$middleware = static fn (): array => ['verified'];

        Rest::resource('routed-tickets', RoutedTicketController::class)->register();

        $this->assertSame([EnforceExpectsJson::class], $this->app['router']->gatherRouteMiddleware($this->routeNamed('routed-tickets.operate.report-outage')));
        $this->assertSame([EnforceExpectsJson::class, 'verified'], $this->app['router']->gatherRouteMiddleware($this->routeNamed('routed-tickets.operate')));
    }

    #[Test]
    public function it_adds_middleware_for_one_endpoint(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);
        StampRoutes::$middleware = static fn (RouteMiddlewareContext $context): array => $context->endpoint === Endpoint::Mutate ? [self::STAMP] : [];

        Rest::resource('routed-tickets', RoutedTicketController::class)->register();

        $this->assertSame([
            'routed-tickets.details' => [EnforceExpectsJson::class],
            'routed-tickets.search' => [EnforceExpectsJson::class],
            'routed-tickets.mutate' => [EnforceExpectsJson::class, self::STAMP],
            'routed-tickets.operate' => [EnforceExpectsJson::class],
            'routed-tickets.metric' => [EnforceExpectsJson::class],
            'routed-tickets.destroy' => [EnforceExpectsJson::class],
        ], $this->catchAllMiddleware());
    }

    #[Test]
    public function it_passes_the_resource_endpoint_controller_and_route_name(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->names('desk')->register();

        $this->assertSame([
            [RoutedTicketResource::class, 'details', RoutedTicketController::class, 'desk.details'],
            [RoutedTicketResource::class, 'search', RoutedTicketController::class, 'desk.search'],
            [RoutedTicketResource::class, 'mutate', RoutedTicketController::class, 'desk.mutate'],
            [RoutedTicketResource::class, 'operate', RoutedTicketController::class, 'desk.operate'],
            [RoutedTicketResource::class, 'metric', RoutedTicketController::class, 'desk.metric'],
            [RoutedTicketResource::class, 'destroy', RoutedTicketController::class, 'desk.destroy'],
        ], array_map(
            static fn (RouteMiddlewareContext $context): array => [$context->resource, $context->endpoint->value, $context->controller, $context->routeName],
            StampRoutes::$contexts,
        ));
    }

    #[Test]
    public function it_skips_a_resource_the_extension_does_not_apply_to(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);
        StampRoutes::$resources = [];

        Rest::resource('routed-tickets', RoutedTicketController::class)->register();

        $this->assertSame(array_fill_keys(self::CATCH_ALL, [EnforceExpectsJson::class]), $this->catchAllMiddleware());
        $this->assertSame([], StampRoutes::$contexts);
    }

    #[Test]
    public function it_runs_the_extension_middleware_on_a_request(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);
        Rest::resource('routed-tickets', RoutedTicketController::class)->register();

        $this->actingAs(new GenericUser(['id' => 1]))->postJson('/routed-tickets/search')->assertOk();

        $this->assertSame(['a'], StampsRequests::$stamps);
    }

    /** @param list<mixed> $returned */
    #[Test]
    #[DataProvider('invalidMiddleware')]
    public function it_refuses_middleware_that_is_not_a_non_empty_string(array $returned, string $given): void
    {
        $this->assertRefused(static fn (): array => $returned, $given);
    }

    /** @return array<string, array{list<mixed>, string}> */
    public static function invalidMiddleware(): array
    {
        return [
            'an integer' => [[0], 'int'],
            'an empty string' => [[''], 'an empty string'],
            'a blank string' => [[' '], 'an empty string'],
            'null' => [[null], 'null'],
            'a list' => [[['auth']], 'array'],
        ];
    }

    #[Test]
    public function it_refuses_a_closure_or_an_object(): void
    {
        $this->assertRefused(static fn (): array => [static fn (): null => null], 'Closure');
        $this->assertRefused(static fn (): array => [new stdClass], 'stdClass');
    }

    #[Test]
    public function it_adds_middleware_to_a_search_only_registration(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->routeNamed('routed-tickets.search')->middleware());
    }

    #[Test]
    public function it_builds_the_extension_once_per_registration(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->withBatchProgress()->register();

        $this->assertSame(1, StampRoutes::$built);
        $this->assertCount(7, StampRoutes::$contexts);
    }

    #[Test]
    public function it_keeps_the_middleware_of_compiled_routes(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);
        Rest::resource('routed-tickets', RoutedTicketController::class)->register();

        $this->useCompiledRoutes();
        config(['rest.extensions' => []]);
        Applicability::flush();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->routeNamed('routed-tickets.search')->middleware());
        $this->actingAs(new GenericUser(['id' => 1]))->postJson('/routed-tickets/search')->assertOk();
        $this->assertSame(['a'], StampsRequests::$stamps);
    }

    #[Test]
    public function it_builds_nothing_at_registration_for_other_kinds(): void
    {
        config(['rest.extensions' => ['counting' => CountingExtension::class, 'acl' => 'Missing\Extension']]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->register();

        $this->assertSame([], ExtensionLog::$built);
        $this->assertSame(array_fill_keys(self::CATCH_ALL, [EnforceExpectsJson::class]), $this->catchAllMiddleware());
        $this->expectExceptionObject(InvalidExtensionException::notAClass('acl', 'Missing\Extension'));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
    }

    #[Test]
    public function it_adds_the_middleware_of_a_subclass_bound_in_the_container(): void
    {
        config(['rest.extensions' => ['plain' => PlainLimits::class]]);
        $this->app->bind(PlainLimits::class, StampingLimits::class);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':bound'], $this->routeNamed('routed-tickets.search')->middleware());
    }

    /** @param Closure(): array<mixed> $returned */
    private function assertRefused(Closure $returned, string $given): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);
        StampRoutes::$middleware = $returned;
        $routes = count($this->app['router']->getRoutes()->getRoutes());

        try {
            Rest::resource('routed-tickets', RoutedTicketController::class)->register();
            $this->fail('The registration passed.');
        } catch (InvalidRouteExtensionException $exception) {
            $this->assertSame('The [stamp] extension '.StampRoutes::class.' returns '.$given.' in routeMiddleware() for the details routes of '.RoutedTicketResource::class.". Return a list of middleware names as non-empty strings, such as 'throttle:quota' or QuotaMiddleware::class: route:cache stores each middleware as text.", $exception->getMessage());
            $this->assertTrue($exception->namesExtension('stamp'));
        }

        $this->assertCount($routes, $this->app['router']->getRoutes()->getRoutes());
    }

    /** @return array<string, list<string>> */
    private function catchAllMiddleware(): array
    {
        return array_combine(self::CATCH_ALL, array_map(fn (string $name): array => $this->routeNamed($name)->middleware(), self::CATCH_ALL));
    }

    /** @return array<string, array{list<string>, list<string>}> */
    private function operateRoutes(): array
    {
        $routes = [];

        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            if ($route->getActionName() === RoutedTicketController::class.'@operate') {
                $routes[$route->getName() ?? ''] = [$route->middleware(), $route->excludedMiddleware()];
            }
        }

        return $routes;
    }

    private function routeNamed(string $name): Route
    {
        return $this->app['router']->getRoutes()->getByName($name) ?? $this->fail("The route [{$name}] is not registered.");
    }
}
