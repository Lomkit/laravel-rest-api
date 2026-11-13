<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as RouteFacade;
use LogicException;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CompilesRoutes;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeclarationLog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskCloseTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskGuestTicketBacklog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskRefreshTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskReportOutage;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketBacklog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketsByStatus;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTitleMatch;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\GuardingAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\GuardingMetric;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\NinthDayMetric;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\PromotedKeyMetric;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\QueuedCloseTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\RekeyingAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\RenamingAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\SkippingAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\UnguardingMetric;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class ClassDeclarationRequestsTest extends DeclarationTestCase
{
    use CompilesRoutes;

    #[Test]
    public function it_builds_only_the_requested_action_through_the_container(): void
    {
        $tickets = $this->seedTickets();
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/actions/close-tickets', ['search' => []])
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);

        $this->assertSame([DeskCloseTickets::class], DeclarationLog::$built);
        $this->assertSame(['closed 2'], DeclarationLog::$journal);
        $this->assertSame('closed', $tickets['Printer jam']->fresh()?->status);
    }

    #[Test]
    public function it_builds_only_the_requested_metric(): void
    {
        $this->seedTickets();
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/metrics/ticket-backlog')
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 2, 'max' => 10]]);

        $this->assertSame([DeskTicketBacklog::class], DeclarationLog::$built);
    }

    #[Test]
    public function it_throttles_the_class_action_that_declares_it_and_not_the_others(): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/actions/close-tickets', ['search' => []])->assertOk();
        $this->postJson('/desk-tickets/actions/close-tickets', ['search' => []])
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Too Many Attempts.']);
        $this->postJson('/desk-tickets/actions/refresh-tickets')->assertOk();
        $this->postJson('/desk-tickets/actions/refresh-tickets')->assertOk();
    }

    #[Test]
    public function it_lets_a_guest_run_the_class_action_that_drops_the_auth_of_the_group(): void
    {
        RouteFacade::middleware('auth')->group(function (): void {
            Rest::resource('desk-tickets', DeskTicketController::class);
        });

        $this->postJson('/desk-tickets/actions/report-outage')
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->postJson('/desk-tickets/actions/refresh-tickets')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->postJson('/desk-tickets/metrics/guest-ticket-backlog')->assertOk();
        $this->postJson('/desk-tickets/metrics/ticket-backlog')->assertUnauthorized();
    }

    /**
     * @param  Closure(): mixed  $change
     * @param  array<string, string>  $body
     */
    #[Test]
    #[DataProvider('unroutedActions')]
    public function it_answers_a_class_action_without_a_route_as_an_unknown_action(Closure $change, string $key, ?Response $listing, int $status, array $body): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $change();

        if ($listing !== null) {
            $this->tickets->answers['viewAny'] = $listing;
        }

        $this->postJson("/desk-tickets/actions/{$key}")
            ->assertStatus($status)
            ->assertExactJson($body);

        $this->assertSame('open', $jam->fresh()?->status);
        $this->assertSame(['viewAny'], $this->tickets->calls);
        $this->assertSame([], DeclarationLog::$built);
    }

    /** @return iterable<string, array{Closure(): mixed, string, ?Response, int, array<string, string>}> */
    public static function unroutedActions(): iterable
    {
        $denied = Response::deny('Tickets are not listed.');

        yield 'never declared, user who can list' => [fn () => null, 'reopen-tickets', null, 404, ['message' => '']];
        yield 'never declared, user who cannot list' => [fn () => null, 'reopen-tickets', $denied, 403, ['message' => 'Tickets are not listed.']];
        yield 'declared after the routes, user who can list' => [fn () => DeskTicketResource::$lateAction = true, 'late-close-tickets', null, 404, ['message' => '']];
        yield 'declared after the routes, user who cannot list' => [fn () => DeskTicketResource::$lateAction = true, 'late-close-tickets', $denied, 403, ['message' => 'Tickets are not listed.']];
        yield 'no longer declared, user who can list' => [fn () => DeskTicketResource::$dropped = true, 'refresh-tickets', null, 404, ['message' => '']];
        yield 'no longer declared, user who cannot list' => [fn () => DeskTicketResource::$dropped = true, 'refresh-tickets', $denied, 403, ['message' => 'Tickets are not listed.']];
    }

    #[Test]
    #[DataProvider('changedEntries')]
    public function it_refuses_to_run_a_class_entry_that_changes_its_route_when_built(bool $metric, string $class, string $uri): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $this->declare($metric, $class);
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson($uri, ['search' => []]),
            fn (Throwable $exception): bool => $exception->getMessage() === self::changedRoute($metric, $class),
        );
        $this->assertSame([$class], DeclarationLog::$built);
        $this->assertSame('open', $jam->fresh()?->status);
    }

    #[Test]
    #[DataProvider('changedClasses')]
    public function it_refuses_a_class_entry_that_changes_its_route_in_the_details(bool $metric, string $class): void
    {
        $this->declare($metric, $class);
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->getJson('/desk-tickets?with=actions,metrics'),
            fn (Throwable $exception): bool => $exception->getMessage() === self::changedRoute($metric, $class),
        );
    }

    #[Test]
    #[DataProvider('changedClasses')]
    public function it_reports_a_class_entry_that_changes_its_route_in_the_check(bool $metric, string $class): void
    {
        $this->declare($metric, $class);
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->assertSame([DeskTicketResource::class.': '.self::changedRoute($metric, $class)], $this->checkProblems());
    }

    /** @return array<string, array{bool, class-string, string}> */
    public static function changedEntries(): array
    {
        return [
            'an action that renames itself' => [false, RenamingAction::class, '/desk-tickets/actions/renaming'],
            'an action that adds middleware' => [false, GuardingAction::class, '/desk-tickets/actions/guarding'],
            'a metric that drops middleware' => [true, UnguardingMetric::class, '/desk-tickets/metrics/unguarding'],
            'a metric that adds middleware' => [true, GuardingMetric::class, '/desk-tickets/metrics/guarding'],
            'an action that changes its route key' => [false, RekeyingAction::class, '/desk-tickets/actions/rekeying'],
            'a metric whose promoted constructor parameter sets its route key' => [true, PromotedKeyMetric::class, '/desk-tickets/metrics/promoted-key'],
        ];
    }

    #[Test]
    #[DataProvider('invalidEntries')]
    public function it_checks_a_class_entry_when_it_is_first_built(bool $metric, string $class, string $uri, LogicException $expected): void
    {
        $this->declare($metric, $class);
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $this->postJson('/desk-tickets/actions/refresh-tickets')->assertOk();

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson($uri, ['search' => []]),
            fn (Throwable $exception): bool => $exception::class === $expected::class && $exception->getMessage() === $expected->getMessage(),
        );
    }

    #[Test]
    #[DataProvider('invalidEntries')]
    public function it_reports_every_invalid_class_entry_in_the_check(bool $metric, string $class, string $uri, LogicException $expected): void
    {
        $this->declare($metric, $class);
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->assertSame([DeskTicketResource::class.': '.$expected->getMessage()], $this->checkProblems());
    }

    /** @return array<string, array{bool, class-string}> */
    public static function changedClasses(): array
    {
        return array_map(fn (array $entry): array => [$entry[0], $entry[1]], self::changedEntries());
    }

    /** @return array<string, array{bool, class-string, string, LogicException}> */
    public static function invalidEntries(): array
    {
        return [
            'an action' => [false, SkippingAction::class, '/desk-tickets/actions/skipping', InvalidActionException::skipWithoutAbility(SkippingAction::class)],
            'a metric' => [true, NinthDayMetric::class, '/desk-tickets/metrics/ninth-day', InvalidMetricException::invalidWeekStart(NinthDayMetric::class, 9)],
        ];
    }

    #[Test]
    public function it_builds_and_reports_every_class_entry_in_the_check(): void
    {
        RouteFacade::middleware('auth')->group(function (): void {
            Rest::resource('desk-tickets', DeskTicketController::class);
        });

        Artisan::call('rest:check', ['controllers' => [DeskTicketController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame(
            [DeskCloseTickets::class, DeskRefreshTickets::class, DeskReportOutage::class, DeskTicketBacklog::class, DeskTicketsByStatus::class, DeskGuestTicketBacklog::class, DeskTitleMatch::class],
            DeclarationLog::$built,
        );
        $this->assertSame(['actions' => 4, 'metrics' => 3, 'instructions' => 2, 'problems' => []], $report['declarations']);
        $this->assertContains('POST /desk-tickets/actions/report-outage is not behind an authentication middleware.', $report['problems']);
        $this->assertContains('POST /desk-tickets/metrics/guest-ticket-backlog is not behind an authentication middleware.', $report['problems']);
        $this->assertContains(DeskCloseTickets::class.' of '.DeskTicketResource::class." runs on rows without authorizeEach(): no policy ability is checked on each row. Return the ability to check from authorizeEach(), such as 'update', or 'view' for an action that only reads.", $report['actions']['problems']);
    }

    #[Test]
    public function it_details_the_class_entries_with_their_flags(): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->getJson('/desk-tickets?with=actions.authorized,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.actions.*.uriKey', ['close-tickets', 'refresh-tickets', 'report-outage', 'throttled-watch-tickets'])
            ->assertJsonPath('data.actions.*.authorized', [true, true, true, true])
            ->assertJsonPath('data.metrics.*.uriKey', ['ticket-backlog', 'tickets-by-status', 'guest-ticket-backlog'])
            ->assertJsonPath('data.metrics.*.authorized', [true, true, true]);
    }

    #[Test]
    public function it_serves_a_class_action_and_a_class_metric_from_compiled_routes(): void
    {
        $this->seedTickets();
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->useCompiledRoutes();

        $this->assertSame(['action' => 'close-tickets'], $this->app['router']->getRoutes()->getByName('desk-tickets.operate.close-tickets')?->defaults);
        $this->postJson('/desk-tickets/actions/close-tickets', ['search' => []])->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->postJson('/desk-tickets/metrics/ticket-backlog')->assertOk()->assertExactJson(['data' => ['type' => 'gauge', 'value' => 0, 'max' => 10]]);
        $this->postJson('/desk-tickets/actions/reopen-tickets')->assertNotFound();
        $this->assertSame([DeskCloseTickets::class, DeskTicketBacklog::class], DeclarationLog::$built);
    }

    #[Test]
    public function it_builds_a_queued_class_action_by_its_key_in_the_worker(): void
    {
        $this->seedTickets();
        DeskTicketResource::$moreActions = [QueuedCloseTickets::class];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        Queue::fake();

        $this->postJson('/desk-tickets/actions/queued-close-tickets', ['search' => []])->assertOk();
        $job = Queue::pushed(QueuedAction::class)->sole();
        DeclarationLog::reset();
        $this->app['auth']->forgetGuards();
        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));
        $this->app->call([$worker, 'handle']);

        $this->assertSame([QueuedCloseTickets::class], DeclarationLog::$built);
        $this->assertSame(['closed 2'], DeclarationLog::$journal);
    }

    /** @return list<string> */
    private function checkProblems(): array
    {
        $exitCode = Artisan::call('rest:check', ['controllers' => [DeskTicketController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame(1, $exitCode);

        return $report['declarations']['problems'];
    }

    /** @param class-string $class */
    private function declare(bool $metric, string $class): void
    {
        if ($metric) {
            DeskTicketResource::$moreMetrics = [$class];

            return;
        }

        DeskTicketResource::$moreActions = [$class];
    }

    private static function changedRoute(bool $metric, string $class): string
    {
        return $metric ? InvalidMetricException::changedRoute($class)->getMessage() : InvalidActionException::changedRoute($class)->getMessage();
    }
}
