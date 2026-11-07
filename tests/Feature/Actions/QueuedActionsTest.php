<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Actions\QueuedRows;
use Lomkit\Rest\Authorization\UserReference;
use Lomkit\Rest\Exceptions\QueuedActionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\DocumentPriority;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ExportDocuments;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\QueuedRuns;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RoutedExportDocuments;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffArchivePolicy;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffDocumentController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffDocumentResource;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration('queue')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
class QueuedActionsTest extends PackageTestCase
{
    use CapturesQueries;

    private const array DUE = ['fields' => [['name' => 'due_at', 'value' => '2026-12-01 10:00:00']]];

    private ArchiveUser $grace;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        QueuedRuns::$runs = [];
        StaffDocumentResource::$exports = true;
        Rest::resource('staff-documents', StaffDocumentController::class)
            ->withSoftDeletes()
            ->register();
        Gate::policy(Document::class, StaffArchivePolicy::class);
        ArchiveUser::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $this->grace = ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@staff.example', 'password' => 'secret']);

        foreach ([1 => 'Budget', 2 => 'Contract', 3 => 'Invoice', 4 => 'Payroll'] as $id => $title) {
            Document::create(['id' => $id, 'title' => $title]);
        }
    }

    #[Test]
    public function it_queues_the_identities_of_the_action_and_not_its_objects(): void
    {
        $this->actingAs($this->grace);

        $job = $this->queued('export-documents', ['fields' => [['name' => 'due_at', 'value' => '2026-12-01 10:00:00'], ['name' => 'note', 'value' => null]]]);

        $this->assertSame(
            [StaffDocumentResource::class, ExportDocuments::class, 'export-documents', ['due_at' => '2026-12-01 10:00:00', 'note' => null], [4, 3, 2, 1], null, true],
            [$job->resource, $job->action, $job->uriKey, $job->fields, $job->rows->keys, $job->rows->trashed, $job->rows->perimeter],
        );
        $this->assertSame(['web', ArchiveUser::class, 2], [$job->user->guard, $job->user->class, $job->user->id]);
        preg_match_all('/O:\d+:"([^"]+)"/', serialize($job), $objects);
        $this->assertSame([QueuedAction::class, QueuedRows::class, UserReference::class], $objects[1]);
    }

    #[Test]
    public function it_runs_the_queued_action_as_the_user_of_the_request(): void
    {
        $this->actingAs($this->grace);
        $job = $this->queued('export-documents', ['fields' => [['name' => 'due_at', 'value' => '2026-12-01 10:00:00'], ['name' => 'note', 'value' => null]]]);

        $this->work($job);

        $run = QueuedRuns::$runs[0];
        $this->assertCount(1, QueuedRuns::$runs);
        $this->assertSame([2, 'web', [4, 3, 2, 1], true, null], [$run['user'], $run['guard'], $run['keys'], $run['shred'], $run['batch']]);
        $this->assertEquals(['due_at' => CarbonImmutable::parse('2026-12-01 10:00:00'), 'priority' => DocumentPriority::Normal, 'note' => null], $run['fields']);
        $this->assertSame(DocumentPriority::Normal, $run['fields']['priority']);
    }

    #[Test]
    public function it_reads_the_rows_again_through_the_perimeter_of_the_user(): void
    {
        $this->actingAs($this->grace);
        $job = $this->queued('export-documents', self::DUE);
        Document::query()
            ->whereKey(1)
            ->delete();
        Document::query()
            ->whereKey(2)
            ->update(['archived' => true]);
        DB::table('documents')
            ->where('id', 3)
            ->delete();
        $this->captureQueries();

        $this->work($job);

        $this->assertSame([4], QueuedRuns::$runs[0]['keys']);
        $this->assertSame([
            ['select * from documents where (archived = ?) and documents.id in (4, 3, 2, 1) and documents.deleted_at is null', [false]],
        ], $this->readsOf('documents'));
    }

    #[Test]
    public function it_reads_the_rows_of_a_search_engine_target_without_the_perimeter_query(): void
    {
        $rows = QueuedRows::of(Document::query()
            ->orderByDesc('id')
            ->get()
            ->all(), null, false);
        Document::query()
            ->whereKey(2)
            ->update(['archived' => true]);
        $this->captureQueries();

        $this->work(new QueuedAction(StaffDocumentResource::class, ExportDocuments::class, 'export-documents', ['due_at' => '2026-12-01 10:00:00'], $rows, UserReference::of('web', $this->grace)));

        $this->assertSame([4, 3, 2, 1], QueuedRuns::$runs[0]['keys']);
        $this->assertSame([
            ['select * from documents where documents.id in (4, 3, 2, 1) and documents.deleted_at is null', []],
        ], $this->readsOf('documents'));
    }

    #[Test]
    public function it_handles_no_chunk_whose_rows_are_all_gone(): void
    {
        $this->actingAs($this->grace);
        $job = $this->queued('export-documents', self::DUE);
        Document::query()->update(['archived' => true]);

        $this->work($job);

        $this->assertSame([], QueuedRuns::$runs);
    }

    /** @param list<int> $keys */
    #[Test]
    #[DataProvider('trashedSearches')]
    public function it_reads_the_trashed_rows_that_the_search_asked_for(string $trashed, bool $restore, ?Trashed $carried, array $keys): void
    {
        Document::query()
            ->whereKey(1)
            ->delete();
        $this->actingAs($this->grace);
        $job = $this->queued('export-documents', [...self::DUE, 'search' => ['trashed' => $trashed]]);

        if ($restore) {
            Document::withTrashed()
                ->whereKey(1)
                ->restore();
        }

        $this->work($job);

        $this->assertSame($carried, $job->rows->trashed);
        $this->assertSame($keys, array_merge(...array_column(QueuedRuns::$runs, 'keys')));
    }

    /** @return array<string, array{string, bool, Trashed, list<int>}> */
    public static function trashedSearches(): array
    {
        return [
            'a search with the trashed rows' => ['with', false, Trashed::With, [4, 3, 2, 1]],
            'a search of the trashed rows, restored before the worker' => ['only', true, Trashed::Only, []],
        ];
    }

    #[Test]
    public function it_fails_when_the_user_no_longer_exists(): void
    {
        $this->actingAs($this->grace);
        $job = $this->queued('export-documents', self::DUE);
        $this->grace->delete();

        $this->expectExceptionObject(new QueuedActionException('The queued action cannot find the user [Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser] with the identifier [2] through the [web] guard.'));

        $this->work($job);
    }

    #[Test]
    public function it_runs_an_action_without_abilities_as_a_guest(): void
    {
        $job = $this->queued('guest-export-documents', self::DUE);

        $this->work($job);

        $this->assertSame(['web', null, null], [$job->user->guard, $job->user->class, $job->user->id]);
        $this->assertSame([null, [3, 2, 1], false], [QueuedRuns::$runs[0]['user'], QueuedRuns::$runs[0]['keys'], QueuedRuns::$runs[0]['shred']]);
    }

    #[Test]
    public function it_fails_when_the_resource_no_longer_declares_the_action(): void
    {
        $this->actingAs($this->grace);
        $job = $this->queued('export-documents', self::DUE);
        StaffDocumentResource::$exports = false;

        $this->expectExceptionObject(new QueuedActionException('The resource [Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffDocumentResource] no longer declares the action [Lomkit\Rest\Tests\Feature\Actions\Stubs\ExportDocuments] under the key [export-documents].'));

        $this->work($job);
    }

    #[Test]
    public function it_sends_the_job_with_the_queue_attributes_of_the_action(): void
    {
        $this->actingAs($this->grace);

        $job = $this->queued('routed-export-documents', self::DUE);

        Queue::assertPushedOn('exports', QueuedAction::class);
        $this->assertSame(
            ['database', 'exports', 60, 3, 30, [5, 10], 2, true, RoutedExportDocuments::class],
            [$job->connection, $job->queue, $job->delay, $job->tries, $job->timeout, $job->backoff, $job->maxExceptions, $job->failOnTimeout, $job->displayName()],
        );
    }

    /** @param class-string $routed */
    #[Test]
    #[DataProvider('queueRoutes')]
    public function it_sends_the_job_where_the_queue_routes_of_the_action_class_say(string $action, string $routed, string $queue, string $connection): void
    {
        Queue::route($routed, 'routed', 'redis');
        $this->actingAs($this->grace);

        $job = $this->queued($action, self::DUE);

        Queue::assertPushedOn($queue, QueuedAction::class);
        $this->assertSame([$connection, $queue], [$job->connection, $job->queue]);
    }

    /** @return array<string, array{string, class-string, string, string}> */
    public static function queueRoutes(): array
    {
        return [
            'a route for the action class' => ['export-documents', ExportDocuments::class, 'routed', 'redis'],
            'an attribute over a route' => ['routed-export-documents', RoutedExportDocuments::class, 'exports', 'database'],
        ];
    }

    #[Test]
    #[WithConfig('queue.default', 'database')]
    public function it_releases_the_job_from_the_action_and_counts_its_attempts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00'));
        $this->actingAs($this->grace);
        $this->postJson('/staff-documents/actions/retried-export-documents', self::DUE)->assertOk();
        $this->app['auth']->forgetGuards();

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->travel(10)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame([['attempts' => 1, 'keys' => [4, 3, 2, 1]], ['attempts' => 2, 'keys' => [4, 3, 2, 1]]], QueuedRuns::$runs);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[Test]
    public function it_gives_the_batch_to_a_batched_action_in_the_worker(): void
    {
        $this->actingAs($this->grace);

        $this->postJson('/staff-documents/actions/batched-export-documents', self::DUE)->assertOk();

        $this->assertSame([[2, 'batched-export-documents']], array_map(fn (array $run): array => [$run['user'], $run['batch']], QueuedRuns::$runs));
    }

    #[Test]
    #[WithConfig('auth.guards.staff', ['driver' => 'session', 'provider' => 'users'])]
    public function it_runs_the_worker_under_the_guard_of_the_request(): void
    {
        $this->actingAs($this->grace, 'staff');
        $job = $this->queued('export-documents', self::DUE);
        Auth::shouldUse('web');

        $this->work($job);

        $this->assertSame(['staff', 2], [QueuedRuns::$runs[0]['guard'], QueuedRuns::$runs[0]['user']]);
        $this->assertSame('web', Auth::getDefaultDriver());
    }

    #[Test]
    public function it_runs_a_queued_action_on_the_sync_queue(): void
    {
        $this->actingAs($this->grace);

        $response = $this->postJson('/staff-documents/actions/export-documents', self::DUE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4]]);
        $this->assertSame([[2, [4, 3, 2, 1], true]], array_map(fn (array $run): array => [$run['user'], $run['keys'], $run['shred']], QueuedRuns::$runs));
    }

    #[Test]
    public function it_runs_a_queued_action_without_rows(): void
    {
        $this->actingAs($this->grace);

        $response = $this->postJson('/staff-documents/actions/standalone-export-documents', self::DUE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);
        $this->assertSame([[2, [], null]], array_map(fn (array $run): array => [$run['user'], $run['keys'], $run['shred']], QueuedRuns::$runs));
    }

    /** @param array<string, mixed> $body */
    private function queued(string $action, array $body): QueuedAction
    {
        Queue::fake();

        $this->postJson("/staff-documents/actions/{$action}", $body)->assertOk();

        return Queue::pushed(QueuedAction::class)->sole();
    }

    private function work(QueuedAction $job): void
    {
        $this->app['auth']->forgetGuards();
        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));

        $this->app->call([$worker, 'handle']);
    }
}
