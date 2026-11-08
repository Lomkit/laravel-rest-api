<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Auth\GenericUser;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\BatchedCloseOpenTickets;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CloseOpenTickets;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

class ActionBatchesTest extends HelpdeskTestCase
{
    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

    private const string OTHER = '1b2c3d4e-5f60-4a7b-8c9d-0e1f2a3b4c5d';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    #[\Override]
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../Support/Database/migrations/2023_08_16_095030_create_job_batches_table.php');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        CloseOpenTickets::reset();
        Rest::resource('chunked-tickets', ChunkedTicketController::class)
            ->withBatchProgress()
            ->register();
        Team::create(['id' => 1, 'name' => 'Support']);

        foreach (range(1, 7) as $id) {
            Ticket::create(['id' => $id, 'team_id' => 1, 'title' => "Ticket {$id}", 'status' => 'open']);
        }

        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);
    }

    #[Test]
    public function it_answers_with_the_batch_of_a_batched_action(): void
    {
        Bus::fake();
        $this->actingAs(new GenericUser(['id' => 7]));

        $response = $this->postJson('/chunked-tickets/actions/batched-close-open-tickets', ['search' => []]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 7, 'batch' => self::BATCH, 'result' => null]]);
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->name === 'batched-close-open-tickets'
            && $batch->jobs->map(fn (QueuedAction $job): array => $job->rows->keys)->all() === [[1, 2], [3, 4], [5, 6], [7]]);
    }

    #[Test]
    public function it_dispatches_action_operated_with_the_batch_once_it_is_dispatched(): void
    {
        Queue::fake();
        Event::fake([ActionOperated::class]);
        $this->actingAs(new GenericUser(['id' => 7]));

        $this->postJson('/chunked-tickets/actions/batched-close-open-tickets', ['search' => []])->assertOk();

        Event::assertDispatchedTimes(ActionOperated::class, 1);
        Event::assertDispatched(ActionOperated::class, fn (ActionOperated $event): bool => get_object_vars($event) === [
            'resource' => ChunkedTicketResource::class,
            'action' => BatchedCloseOpenTickets::class,
            'uriKey' => 'batched-close-open-tickets',
            'fields' => [],
            'impacted' => 7,
            'batch' => self::BATCH,
        ] && Bus::findBatch(self::BATCH)?->totalJobs === 4);
    }

    #[Test]
    public function it_skips_the_jobs_of_a_cancelled_batch(): void
    {
        CloseOpenTickets::$cancel = true;

        $response = $this->postJson('/chunked-tickets/actions/batched-close-open-tickets', ['search' => []]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 7, 'batch' => self::BATCH, 'result' => null]]);
        $this->assertSame([[1, 2]], CloseOpenTickets::$runs);
        $this->assertTrue(Bus::findBatch(self::BATCH)?->cancelled());
    }

    #[Test]
    public function it_reports_the_progress_of_a_batch_to_the_user_who_started_it(): void
    {
        Queue::fake();
        $this->actingAs(new GenericUser(['id' => 7]));
        $this->postJson('/chunked-tickets/actions/batched-close-open-tickets', ['search' => []])->assertOk();
        Bus::findBatch(self::BATCH)?->recordSuccessfulJob('job-1');

        $response = $this->getJson('/chunked-tickets/actions/batched-close-open-tickets/batches/'.self::BATCH);

        $response->assertOk()->assertExactJson(['data' => [
            'id' => self::BATCH,
            'total_jobs' => 4,
            'pending_jobs' => 3,
            'failed_jobs' => 0,
            'progress' => 25,
            'finished' => false,
            'cancelled' => false,
        ]]);
    }

    #[Test]
    #[DataProvider('strangers')]
    public function it_hides_a_batch_from_anyone_but_the_user_who_started_it(?int $starter, ?int $reader, string $path): void
    {
        Queue::fake();

        if ($starter !== null) {
            $this->actingAs(new GenericUser(['id' => $starter]));
        }

        $this->postJson('/chunked-tickets/actions/batched-close-open-tickets', ['search' => []])->assertOk();
        $this->app['auth']->forgetGuards();

        if ($reader !== null) {
            $this->actingAs(new GenericUser(['id' => $reader]));
        }

        $this->getJson("/chunked-tickets/actions/{$path}")->assertNotFound();
    }

    /** @return array<string, array{?int, ?int, string}> */
    public static function strangers(): array
    {
        return [
            'another user' => [7, 8, 'batched-close-open-tickets/batches/'.self::BATCH],
            'a guest' => [7, null, 'batched-close-open-tickets/batches/'.self::BATCH],
            'the starter, through another action' => [7, 7, 'close-open-tickets/batches/'.self::BATCH],
            'the starter, with another batch' => [7, 7, 'batched-close-open-tickets/batches/'.self::OTHER],
            'the starter, through an unknown action' => [7, 7, 'merge-tickets/batches/'.self::BATCH],
            'a guest, for the batch of a guest' => [null, null, 'batched-close-open-tickets/batches/'.self::BATCH],
        ];
    }

    #[Test]
    public function it_registers_the_progress_route_only_when_asked(): void
    {
        Rest::resource('plain-tickets', ChunkedTicketController::class)->register();

        $route = Route::getRoutes()->getByName('chunked-tickets.batch');

        $this->assertNotNull($route);
        $this->assertSame('chunked-tickets/actions/{action}/batches/{batch}', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame(ChunkedTicketController::class.'@batch', $route->getActionName());
        $this->assertNull(Route::getRoutes()->getByName('plain-tickets.batch'));
    }
}
