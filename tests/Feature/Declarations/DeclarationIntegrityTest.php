<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Closure;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\AbstractDeskAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeclarationLog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskCloseTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskRefreshTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketBacklog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\PromotedGuardAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\RenamedRefreshTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\SameRouteRefreshTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\TicketJournal;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\ToggledQueuedAction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\TraitGuardedAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class DeclarationIntegrityTest extends DeclarationTestCase
{
    #[Test]
    public function it_checks_the_kind_of_a_metric_class_read_first_as_a_metric(): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->only('metric')->register();
        DeskTicketResource::$moreActions = [DeskTicketBacklog::class];

        $this->expectExceptionObject(InvalidActionException::notAnAction(DeskTicketBacklog::class));

        Rest::resource('other-tickets', DeskTicketController::class)->only('operate')->register();
    }

    #[Test]
    public function it_checks_the_kind_of_an_action_class_read_first_as_an_action(): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->only('operate')->register();
        DeskTicketResource::$moreMetrics = [DeskCloseTickets::class];

        $this->expectExceptionObject(InvalidMetricException::notAMetric(DeskCloseTickets::class));

        Rest::resource('other-tickets', DeskTicketController::class)->only('metric')->register();
    }

    #[Test]
    #[DataProvider('undeclarableClasses')]
    public function it_refuses_a_class_that_cannot_be_built_when_the_routes_register(bool $metric, string $class, LogicException $expected): void
    {
        DeskTicketResource::$moreActions = $metric ? [] : [$class];
        DeskTicketResource::$moreMetrics = $metric ? [$class] : [];

        $this->expectExceptionObject($expected);

        Rest::resource('desk-tickets', DeskTicketController::class)->register();
    }

    /** @return array<string, array{bool, class-string, LogicException}> */
    public static function undeclarableClasses(): array
    {
        return [
            'an abstract action' => [false, AbstractDeskAction::class, InvalidActionException::notInstantiable(AbstractDeskAction::class)],
            'the action class itself' => [false, Action::class, InvalidActionException::baseListed()],
            'the metric class itself' => [true, Metric::class, InvalidMetricException::baseListed()],
        ];
    }

    #[Test]
    #[RequiresPhp('>= 8.5')]
    public function it_reads_the_middleware_that_a_trait_declares(): void
    {
        DeskTicketResource::$moreActions = [TraitGuardedAction::class];

        Rest::resource('desk-tickets', DeskTicketController::class)->only('operate')->register();

        $this->assertSame(['can:admin'], array_slice($this->deskRoutes('operate')['desk-tickets.operate.trait-guarded'][2], -1));
    }

    #[Test]
    public function it_refuses_to_run_an_action_whose_promoted_constructor_parameter_sets_its_middleware(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        DeskTicketResource::$moreActions = [PromotedGuardAction::class];
        Rest::resource('desk-tickets', DeskTicketController::class)->only('operate')->register();

        $this->assertNotContains('can:admin', $this->deskRoutes('operate')['desk-tickets.operate.promoted-guard'][2]);
        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson('/desk-tickets/actions/promoted-guard', ['search' => []]),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidActionException::changedRoute(PromotedGuardAction::class)->getMessage(),
        );
        $this->assertSame('open', $jam->fresh()?->status);
    }

    /** @param Closure(): object $binding */
    #[Test]
    #[DataProvider('bindings')]
    public function it_names_the_binding_that_returns_another_class(Closure $binding, string $message): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $this->app->bind(DeskRefreshTickets::class, $binding);

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson('/desk-tickets/actions/refresh-tickets'),
            fn (Throwable $exception): bool => $exception->getMessage() === $message,
        );
    }

    /** @return array<string, array{Closure(): object, string}> */
    public static function bindings(): array
    {
        return [
            'a foreign class' => [fn (): object => new TicketJournal, InvalidActionException::foreignBinding(DeskRefreshTickets::class, TicketJournal::class)->getMessage()],
            'a subclass with another route' => [fn (): object => new RenamedRefreshTickets, InvalidActionException::reboundRoute(DeskRefreshTickets::class, RenamedRefreshTickets::class)->getMessage()],
        ];
    }

    #[Test]
    public function it_runs_the_subclass_that_a_binding_returns_with_the_same_route(): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $this->app->bind(DeskRefreshTickets::class, fn (): object => new SameRouteRefreshTickets);

        $this->postJson('/desk-tickets/actions/refresh-tickets')->assertOk();

        $this->assertSame([SameRouteRefreshTickets::class], DeclarationLog::$built);
    }

    #[Test]
    public function it_refuses_a_changed_class_entry_in_the_queue_worker(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        DeskTicketResource::$moreActions = [ToggledQueuedAction::class];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        Queue::fake();
        $this->postJson('/desk-tickets/actions/toggled-queued', ['search' => []])->assertOk();
        $job = Queue::pushed(QueuedAction::class)->sole();
        ToggledQueuedAction::$guarded = true;

        $this->assertThrows(
            fn () => $this->work($job),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidActionException::changedRoute(ToggledQueuedAction::class)->getMessage(),
        );
        $this->assertSame('open', $jam->fresh()?->status);
        $this->assertSame([], DeclarationLog::$journal);
    }

    #[Test]
    public function it_refuses_a_changed_class_entry_in_the_batch_progress(): void
    {
        DeskTicketResource::$moreActions = [ToggledQueuedAction::class];
        Rest::resource('desk-tickets', DeskTicketController::class)->withBatchProgress()->register();
        ToggledQueuedAction::$guarded = true;

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->getJson('/desk-tickets/actions/toggled-queued/batches/9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f'),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidActionException::changedRoute(ToggledQueuedAction::class)->getMessage(),
        );
    }

    private function work(QueuedAction $job): void
    {
        $this->app['auth']->forgetGuards();
        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));

        $this->app->call([$worker, 'handle']);
    }
}
