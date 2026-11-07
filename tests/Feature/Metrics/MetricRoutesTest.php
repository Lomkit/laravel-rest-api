<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Routing\ResourceRegistrar as IlluminateResourceRegistrar;
use Illuminate\Support\Facades\Route as RouteFacade;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Concerns\CompilesRoutes;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\SlugTypedMetric;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

class MetricRoutesTest extends HelpdeskTestCase
{
    use CompilesRoutes;

    /** @var array<string, string> */
    private array $laravelVerbs = [];

    #[Before]
    public function rememberLaravelVerbs(): void
    {
        $this->laravelVerbs = IlluminateResourceRegistrar::verbs();
        MetricTicketResource::reset();
    }

    #[After]
    public function restoreLaravelVerbs(): void
    {
        (new ReflectionProperty(IlluminateResourceRegistrar::class, 'verbs'))->setValue(null, $this->laravelVerbs);
        MetricTicketResource::reset();
    }

    #[Test]
    public function it_gives_each_metric_a_route_with_its_middleware_before_the_route_of_unknown_metrics(): void
    {
        Rest::resource('metric-tickets', MetricTicketController::class)
            ->middleware('verified')
            ->middlewareFor('metric', 'auth')
            ->register();

        $base = [EnforceExpectsJson::class, 'verified', 'auth'];

        $this->assertSame([
            'metric-tickets.metric.ticket-backlog' => ['POST metric-tickets/metrics/ticket-backlog', ['metric' => 'ticket-backlog'], [...$base, 'throttle:60,1'], []],
            'metric-tickets.metric.tickets-by-status' => ['POST metric-tickets/metrics/tickets-by-status', ['metric' => 'tickets-by-status'], $base, []],
            'metric-tickets.metric.guest-ticket-backlog' => ['POST metric-tickets/metrics/guest-ticket-backlog', ['metric' => 'guest-ticket-backlog'], $base, ['auth']],
            'metric-tickets.metric.reported-ticket-backlog' => ['POST metric-tickets/metrics/reported-ticket-backlog', ['metric' => 'reported-ticket-backlog'], $base, []],
            'metric-tickets.metric.unchecked-ticket-backlog' => ['POST metric-tickets/metrics/unchecked-ticket-backlog', ['metric' => 'unchecked-ticket-backlog'], $base, []],
            'metric-tickets.metric.quiet-ticket-backlog' => ['POST metric-tickets/metrics/quiet-ticket-backlog', ['metric' => 'quiet-ticket-backlog'], $base, []],
            'metric-tickets.metric.typed-ticket-backlog' => ['POST metric-tickets/metrics/typed-ticket-backlog', ['metric' => 'typed-ticket-backlog'], $base, []],
            'metric-tickets.metric' => ['POST metric-tickets/metrics/{metric}', [], $base, []],
        ], $this->metricRoutes());
    }

    #[Test]
    public function it_reads_the_actions_and_the_metrics_of_one_resource_once_when_the_routes_register(): void
    {
        Rest::resource('metric-tickets', MetricTicketController::class)->register();

        $this->assertCount(2, MetricTicketResource::$reads);
        $this->assertSame(['actions', 'metrics'], array_map(fn (string $read): string => strtok($read, ' ') ?: '', MetricTicketResource::$reads));
        $this->assertSame(explode(' ', MetricTicketResource::$reads[0])[1], explode(' ', MetricTicketResource::$reads[1])[1]);
    }

    #[Test]
    public function it_reads_no_metric_without_the_metric_endpoint(): void
    {
        Rest::resource('metric-tickets', MetricTicketController::class)
            ->except('metric', 'operate')
            ->register();

        $this->assertSame([], MetricTicketResource::$reads);
        $this->assertSame([], $this->metricRoutes());
    }

    #[Test]
    public function it_rejects_two_metrics_with_one_uri_key(): void
    {
        MetricTicketResource::$duplicated = true;
        $before = $this->app['router']->getRoutes()->count();

        try {
            Rest::resource('metric-tickets', MetricTicketController::class)->register();
            $this->fail('The registration was not rejected.');
        } catch (InvalidResourceRouteException $exception) {
            $this->assertSame('Two endpoints of the resource use the route [POST metric-tickets/metrics/ticket-backlog].', $exception->getMessage());
        }

        $this->assertSame($before, $this->app['router']->getRoutes()->count());
    }

    #[Test]
    public function it_rejects_a_metric_type_that_is_not_a_lowercase_slug(): void
    {
        MetricTicketResource::$misdeclared = true;

        $this->expectExceptionObject(InvalidMetricException::invalidType(SlugTypedMetric::class, 'Gauge'));

        Rest::resource('metric-tickets', MetricTicketController::class)->register();
    }

    #[Test]
    public function it_serves_each_metric_from_its_compiled_route(): void
    {
        $this->seedTickets();
        Rest::resource('metric-tickets', MetricTicketController::class)->register();
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $this->useCompiledRoutes();

        $route = $this->app['router']->getRoutes()->getByName('metric-tickets.metric.ticket-backlog');

        $this->assertSame('metric-tickets/metrics/ticket-backlog', $route?->uri());
        $this->assertSame(['metric' => 'ticket-backlog'], $route->defaults);
        $this->postJson('/metric-tickets/metrics/ticket-backlog')
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 4, 'max' => 10]]);
        $this->postJson('/metric-tickets/metrics/revenue')->assertNotFound();
    }

    /**
     * @param  array<string, string>  $verbs
     * @param  Closure(PendingResourceRegistration): mixed  $configure
     * @param  array<string, string>  $expected
     */
    #[Test]
    #[DataProvider('namings')]
    public function it_names_the_metric_routes_after_the_metric_route(array $verbs, Closure $configure, array $expected): void
    {
        RouteFacade::resourceVerbs($verbs);
        $pending = Rest::resource('metric-tickets', MetricTicketController::class)->only('metric');
        $configure($pending);
        $pending->register();

        $routes = array_map(fn (array $route): string => $route[0], $this->metricRoutes());

        $this->assertSame($expected, array_slice($routes, 0, 1) + array_slice($routes, -1, 1));
    }

    /** @return iterable<string, array{array<string, string>, Closure(PendingResourceRegistration): mixed, array<string, string>}> */
    public static function namings(): iterable
    {
        yield 'a verb' => [['metrics' => 'stats'], fn (PendingResourceRegistration $pending) => $pending, [
            'metric-tickets.metric.ticket-backlog' => 'POST metric-tickets/stats/ticket-backlog',
            'metric-tickets.metric' => 'POST metric-tickets/stats/{metric}',
        ]];
        yield 'a name prefix' => [[], fn (PendingResourceRegistration $pending) => $pending->names('desk'), [
            'desk.metric.ticket-backlog' => 'POST metric-tickets/metrics/ticket-backlog',
            'desk.metric' => 'POST metric-tickets/metrics/{metric}',
        ]];
        yield 'a metric name' => [[], fn (PendingResourceRegistration $pending) => $pending->name('metric', 'helpdesk.stats'), [
            'helpdesk.stats.ticket-backlog' => 'POST metric-tickets/metrics/ticket-backlog',
            'helpdesk.stats' => 'POST metric-tickets/metrics/{metric}',
        ]];
    }

    /** @return array<string, array{string, array<string, mixed>, list<mixed>, list<string>}> */
    private function metricRoutes(): array
    {
        $routes = [];

        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            if ($route->getActionName() === MetricTicketController::class.'@metric') {
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
}
