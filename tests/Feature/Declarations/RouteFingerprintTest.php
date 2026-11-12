<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as RouteFacade;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CompilesRoutes;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeclarationLog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\PublicBacklog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\PublicOutage;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\QueuedCloseTickets;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\TicketJournal;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class RouteFingerprintTest extends DeclarationTestCase
{
    use CompilesRoutes;

    #[Test]
    public function it_runs_an_instance_whose_route_did_not_change(): void
    {
        DeskTicketResource::$moreActions = [PublicOutage::make()->withoutMiddleware('auth')];
        $this->registerBehindAuth();

        $this->postJson('/desk-tickets/actions/public-outage')->assertOk();
    }

    #[Test]
    public function it_refuses_an_instance_whose_middleware_changed_after_the_routes_registered(): void
    {
        DeskTicketResource::$moreActions = [PublicOutage::make()->withoutMiddleware('auth')];
        $this->registerBehindAuth();
        DeskTicketResource::$moreActions = [PublicOutage::make()];

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson('/desk-tickets/actions/public-outage'),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidActionException::staleRoute(PublicOutage::class)->getMessage(),
        );
    }

    #[Test]
    public function it_refuses_a_metric_instance_whose_middleware_changed_after_the_routes_registered(): void
    {
        DeskTicketResource::$moreMetrics = [PublicBacklog::make()->withoutMiddleware('auth')];
        $this->registerBehindAuth();
        DeskTicketResource::$moreMetrics = [PublicBacklog::make()];

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson('/desk-tickets/metrics/public-backlog'),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidMetricException::staleRoute(PublicBacklog::class)->getMessage(),
        );
    }

    #[Test]
    public function it_refuses_an_action_whose_middleware_changed_since_the_routes_were_cached(): void
    {
        DeskTicketResource::$byClass = false;
        DeskTicketResource::$moreActions = [PublicOutage::make()->withoutMiddleware('auth')];
        $this->registerBehindAuth();
        $this->useCompiledRoutes();
        DeskTicketResource::$moreActions = [PublicOutage::make()];

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson('/desk-tickets/actions/public-outage'),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidActionException::staleRoute(PublicOutage::class)->getMessage(),
        );
    }

    #[Test]
    public function it_serves_unchanged_actions_from_cached_routes(): void
    {
        DeskTicketResource::$moreActions = [PublicOutage::make()->withoutMiddleware('auth')];
        $this->registerBehindAuth();
        $this->useCompiledRoutes();

        $this->postJson('/desk-tickets/actions/public-outage')->assertOk();
    }

    #[Test]
    public function it_refuses_an_instance_that_changed_between_the_request_and_the_worker(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        DeskTicketResource::$moreActions = [new QueuedCloseTickets(new TicketJournal)];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        Queue::fake();
        $this->postJson('/desk-tickets/actions/queued-close-tickets', ['search' => []])->assertOk();
        $job = Queue::pushed(QueuedAction::class)->sole();
        DeskTicketResource::$moreActions = [(new QueuedCloseTickets(new TicketJournal))->middleware('can:admin')];

        $this->assertThrows(
            fn () => $this->work($job),
            fn (Throwable $exception): bool => $exception->getMessage() === InvalidActionException::staleRoute(QueuedCloseTickets::class)->getMessage(),
        );
        $this->assertSame('open', $jam->fresh()?->status);
        $this->assertSame([], DeclarationLog::$journal);
    }

    private function registerBehindAuth(): void
    {
        RouteFacade::middleware('auth')->group(function (): void {
            Rest::resource('desk-tickets', DeskTicketController::class)->register();
        });
    }

    private function work(QueuedAction $job): void
    {
        $this->app['auth']->forgetGuards();
        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));

        $this->app->call([$worker, 'handle']);
    }
}
