<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CloseOpenTickets;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

class ActionJobsTest extends HelpdeskTestCase
{
    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

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
        Rest::resource('chunked-tickets', ChunkedTicketController::class)->register();
        Team::create(['id' => 1, 'name' => 'Support']);

        foreach (range(1, 7) as $id) {
            Ticket::create(['id' => $id, 'team_id' => 1, 'title' => "Ticket {$id}", 'status' => 'open']);
        }

        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<list<int>>  $jobs
     */
    #[Test]
    #[DataProvider('batches')]
    #[WithConfig('rest.limits.sizes.jobs', 3)]
    public function it_grows_the_chunks_of_a_batched_action_so_that_its_jobs_stay_at_the_limit(ActionTarget $target, array $body, array $jobs): void
    {
        Bus::fake();
        ChunkedTicketResource::$target = $target;

        $response = $this->postJson('/chunked-tickets/actions/batched-close-open-tickets', $body);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 7, 'batch' => self::BATCH, 'result' => null]]);
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->map(fn (QueuedAction $job): array => $job->rows->keys)->all() === $jobs);
    }

    /** @return array<string, array{ActionTarget, array<string, mixed>, list<list<int>>}> */
    public static function batches(): array
    {
        return [
            'a search in the order of the key' => [ActionTarget::Search, [], [[1, 2, 3], [4, 5, 6], [7]]],
            'a sorted search' => [ActionTarget::Search, ['search' => ['sorts' => [['field' => 'title', 'direction' => 'desc']]]], [[7, 6, 5], [4, 3, 2], [1]]],
            'the named rows' => [ActionTarget::Resources, ['resources' => [5, 2, 7, 1, 3, 6, 4]], [[5, 2, 7], [1, 3, 6], [4]]],
        ];
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.jobs', 4)]
    public function it_keeps_the_chunk_size_of_a_batched_action_whose_jobs_fit_in_the_limit(): void
    {
        Bus::fake();

        $this->postJson('/chunked-tickets/actions/batched-close-open-tickets')->assertOk();

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->map(fn (QueuedAction $job): array => $job->rows->keys)->all() === [[1, 2], [3, 4], [5, 6], [7]]);
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.jobs', 3)]
    public function it_grows_the_chunks_of_a_queued_action_without_a_maximum_of_resources(): void
    {
        Queue::fake();

        $response = $this->postJson('/chunked-tickets/actions/queued-close-open-tickets');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 7, 'result' => null]]);
        $this->assertSame([[1, 2, 3], [4, 5, 6], [7]], Queue::pushed(QueuedAction::class)->map(fn (QueuedAction $job): array => $job->rows->keys)->all());
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.jobs', 3)]
    public function it_keeps_the_chunk_size_of_an_action_that_is_not_queued(): void
    {
        $response = $this->postJson('/chunked-tickets/actions/close-open-tickets');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 7, 'result' => null]]);
        $this->assertSame([[1, 2], [3, 4], [5, 6], [7]], CloseOpenTickets::$runs);
    }

    #[Test]
    public function it_runs_a_batched_action_over_more_rows_than_the_limit_of_jobs_times_its_chunk_size(): void
    {
        Ticket::query()->insert(array_map(fn (int $id): array => ['id' => $id, 'team_id' => 1, 'title' => "Ticket {$id}", 'status' => 'open'], range(8, 250)));

        $response = $this->postJson('/chunked-tickets/actions/batched-close-open-tickets');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 250, 'batch' => self::BATCH, 'result' => null]]);
        $this->assertSame(array_chunk(range(1, 250), 3), CloseOpenTickets::$runs);
        $this->assertSame([84, 0], [Bus::findBatch(self::BATCH)?->totalJobs, Bus::findBatch(self::BATCH)?->pendingJobs]);
        $this->assertSame(0, Ticket::query()->where('status', 'open')->count());
    }
}
