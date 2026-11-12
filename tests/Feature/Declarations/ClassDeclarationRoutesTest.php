<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Closure;
use Illuminate\Support\Facades\Route as RouteFacade;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeclarationLog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\NamedByMethodAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\NamedByMethodMetric;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\UriKeyedAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\UriKeyedMetric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ClassDeclarationRoutesTest extends DeclarationTestCase
{
    #[Test]
    #[DataProvider('forms')]
    public function it_gives_classes_and_instances_the_same_routes(bool $byClass): void
    {
        DeskTicketResource::$byClass = $byClass;

        Rest::resource('desk-tickets', DeskTicketController::class)
            ->middleware('verified')
            ->middlewareFor('operate', 'auth')
            ->middlewareFor('metric', 'auth')
            ->register();

        $base = [EnforceExpectsJson::class, 'verified', 'auth'];

        $this->assertSame([
            'desk-tickets.operate.close-tickets' => ['POST desk-tickets/actions/close-tickets', ['action' => 'close-tickets'], [...$base, 'throttle:1,1'], []],
            'desk-tickets.operate.refresh-tickets' => ['POST desk-tickets/actions/refresh-tickets', ['action' => 'refresh-tickets'], $base, []],
            'desk-tickets.operate.report-outage' => ['POST desk-tickets/actions/report-outage', ['action' => 'report-outage'], $base, ['auth', 'verified']],
            'desk-tickets.operate.throttled-watch-tickets' => ['POST desk-tickets/actions/throttled-watch-tickets', ['action' => 'throttled-watch-tickets'], [...$base, 'throttle:60,1'], ['verified']],
            'desk-tickets.operate' => ['POST desk-tickets/actions/{action}', [], $base, []],
        ], $this->deskRoutes('operate'));
        $this->assertSame([
            'desk-tickets.metric.ticket-backlog' => ['POST desk-tickets/metrics/ticket-backlog', ['metric' => 'ticket-backlog'], [...$base, 'throttle:60,1'], []],
            'desk-tickets.metric.tickets-by-status' => ['POST desk-tickets/metrics/tickets-by-status', ['metric' => 'tickets-by-status'], $base, []],
            'desk-tickets.metric.guest-ticket-backlog' => ['POST desk-tickets/metrics/guest-ticket-backlog', ['metric' => 'guest-ticket-backlog'], $base, ['auth']],
            'desk-tickets.metric' => ['POST desk-tickets/metrics/{metric}', [], $base, []],
        ], $this->deskRoutes('metric'));
    }

    /** @return array<string, array{bool}> */
    public static function forms(): array
    {
        return [
            'instances' => [false],
            'classes' => [true],
        ];
    }

    /**
     * @param  array<string, string>  $verbs
     * @param  Closure(PendingResourceRegistration): mixed  $configure
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('namings')]
    public function it_names_class_routes_as_instance_routes(bool $byClass, array $verbs, Closure $configure, array $expected): void
    {
        DeskTicketResource::$byClass = $byClass;
        RouteFacade::resourceVerbs($verbs);
        $pending = Rest::resource('desk-tickets', DeskTicketController::class)->only('operate', 'metric');
        $configure($pending);
        $pending->register();

        $routes = [...$this->deskRoutes('operate'), ...$this->deskRoutes('metric')];

        $this->assertSame($expected, array_map(fn (array $route): string => $route[0], $routes));
    }

    /** @return iterable<string, array{bool, array<string, string>, Closure(PendingResourceRegistration): mixed, array<string, string>}> */
    public static function namings(): iterable
    {
        foreach (self::forms() as $form => [$byClass]) {
            yield "{$form}, verbs" => [$byClass, ['actions' => 'run', 'metrics' => 'stats'], fn (PendingResourceRegistration $pending) => $pending, [
                'desk-tickets.operate.close-tickets' => 'POST desk-tickets/run/close-tickets',
                'desk-tickets.operate.refresh-tickets' => 'POST desk-tickets/run/refresh-tickets',
                'desk-tickets.operate.report-outage' => 'POST desk-tickets/run/report-outage',
                'desk-tickets.operate.throttled-watch-tickets' => 'POST desk-tickets/run/throttled-watch-tickets',
                'desk-tickets.operate' => 'POST desk-tickets/run/{action}',
                'desk-tickets.metric.ticket-backlog' => 'POST desk-tickets/stats/ticket-backlog',
                'desk-tickets.metric.tickets-by-status' => 'POST desk-tickets/stats/tickets-by-status',
                'desk-tickets.metric.guest-ticket-backlog' => 'POST desk-tickets/stats/guest-ticket-backlog',
                'desk-tickets.metric' => 'POST desk-tickets/stats/{metric}',
            ]];
            yield "{$form}, a name prefix" => [$byClass, [], fn (PendingResourceRegistration $pending) => $pending->names('desk'), [
                'desk.operate.close-tickets' => 'POST desk-tickets/actions/close-tickets',
                'desk.operate.refresh-tickets' => 'POST desk-tickets/actions/refresh-tickets',
                'desk.operate.report-outage' => 'POST desk-tickets/actions/report-outage',
                'desk.operate.throttled-watch-tickets' => 'POST desk-tickets/actions/throttled-watch-tickets',
                'desk.operate' => 'POST desk-tickets/actions/{action}',
                'desk.metric.ticket-backlog' => 'POST desk-tickets/metrics/ticket-backlog',
                'desk.metric.tickets-by-status' => 'POST desk-tickets/metrics/tickets-by-status',
                'desk.metric.guest-ticket-backlog' => 'POST desk-tickets/metrics/guest-ticket-backlog',
                'desk.metric' => 'POST desk-tickets/metrics/{metric}',
            ]];
            yield "{$form}, endpoint names" => [$byClass, [], fn (PendingResourceRegistration $pending) => $pending->name('operate', 'helpdesk.run')->name('metric', 'helpdesk.stats'), [
                'helpdesk.run.close-tickets' => 'POST desk-tickets/actions/close-tickets',
                'helpdesk.run.refresh-tickets' => 'POST desk-tickets/actions/refresh-tickets',
                'helpdesk.run.report-outage' => 'POST desk-tickets/actions/report-outage',
                'helpdesk.run.throttled-watch-tickets' => 'POST desk-tickets/actions/throttled-watch-tickets',
                'helpdesk.run' => 'POST desk-tickets/actions/{action}',
                'helpdesk.stats.ticket-backlog' => 'POST desk-tickets/metrics/ticket-backlog',
                'helpdesk.stats.tickets-by-status' => 'POST desk-tickets/metrics/tickets-by-status',
                'helpdesk.stats.guest-ticket-backlog' => 'POST desk-tickets/metrics/guest-ticket-backlog',
                'helpdesk.stats' => 'POST desk-tickets/metrics/{metric}',
            ]];
        }
    }

    #[Test]
    public function it_registers_class_declarations_without_building_them(): void
    {
        $resolved = [];
        $this->app->beforeResolving(function (string $abstract) use (&$resolved): void {
            $resolved[] = $abstract;
        });

        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        Rest::resource('tickets', TicketController::class)->only('search')->register();
        Rest::resource('teams', TeamController::class)->only('operate', 'metric')->register();

        $this->assertSame([], DeclarationLog::$built);
        $this->assertSame(['request', DeskTicketResource::class, 'request', 'Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource'], $resolved);
    }

    #[Test]
    #[DataProvider('dynamicKeys')]
    public function it_rejects_a_class_entry_with_a_dynamic_key(bool $metric, string $class, string $message): void
    {
        DeskTicketResource::$moreActions = $metric ? [] : [$class];
        DeskTicketResource::$moreMetrics = $metric ? [$class] : [];

        $this->expectExceptionObject($metric ? new InvalidMetricException($message) : new InvalidActionException($message));

        Rest::resource('desk-tickets', DeskTicketController::class)->register();
    }

    /** @return array<string, array{bool, class-string, string}> */
    public static function dynamicKeys(): array
    {
        $action = ': its route key must be read without building it. Set $name, or list the action as an instance.';
        $metric = ': its route key must be read without building it. Set $name, or list the metric as an instance.';

        return [
            'an action that overrides name()' => [false, NamedByMethodAction::class, NamedByMethodAction::class.' is listed by its class, but it overrides name()'.$action],
            'an action that overrides uriKey()' => [false, UriKeyedAction::class, UriKeyedAction::class.' is listed by its class, but it overrides uriKey()'.$action],
            'a metric that overrides name()' => [true, NamedByMethodMetric::class, NamedByMethodMetric::class.' is listed by its class, but it overrides name()'.$metric],
            'a metric that overrides uriKey()' => [true, UriKeyedMetric::class, UriKeyedMetric::class.' is listed by its class, but it overrides uriKey()'.$metric],
        ];
    }

    #[Test]
    public function it_accepts_an_instance_whose_key_comes_from_a_method(): void
    {
        DeskTicketResource::$moreActions = [UriKeyedAction::make()];

        Rest::resource('desk-tickets', DeskTicketController::class)->only('operate')->register();

        $this->assertArrayHasKey('desk-tickets.operate.refresh', $this->deskRoutes('operate'));
    }

    #[Test]
    #[DataProvider('foreignClasses')]
    public function it_rejects_a_class_entry_of_another_kind(bool $metric, string $class, string $message): void
    {
        DeskTicketResource::$moreActions = $metric ? [] : [$class];
        DeskTicketResource::$moreMetrics = $metric ? [$class] : [];

        $this->expectExceptionObject($metric ? new InvalidMetricException($message) : new InvalidActionException($message));

        Rest::resource('desk-tickets', DeskTicketController::class)->register();
    }

    /** @return array<string, array{bool, class-string, string}> */
    public static function foreignClasses(): array
    {
        return [
            'a metric in actions()' => [false, TicketBacklog::class, '['.TicketBacklog::class.'] is listed in actions(), but it does not extend Lomkit\Rest\Actions\Action.'],
            'an action in metrics()' => [true, CloseTickets::class, '['.CloseTickets::class.'] is listed in metrics(), but it does not extend Lomkit\Rest\Metrics\Metric.'],
        ];
    }

    #[Test]
    public function it_rejects_a_class_and_an_instance_with_one_key(): void
    {
        DeskTicketResource::$moreActions = [CloseTickets::make()];

        $this->expectExceptionObject(InvalidResourceRouteException::duplicateUri('POST desk-tickets/actions/close-tickets'));

        Rest::resource('desk-tickets', DeskTicketController::class)->register();
    }
}
