<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceRestored;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Deletion\Concerns\SeedsDocuments;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\DeletionTrace;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\TracingDocumentController;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\TracingDocumentPolicy;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\TracingDocumentResource;
use Lomkit\Rest\Tests\Feature\Events\Stubs\QueuedWriteListener;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
#[WithMigration('queue')]
class DeletionEventsTest extends PackageTestCase
{
    use SeedsDocuments;

    private const string TRASHED = '2026-01-01 00:00:00';

    private const array EVENTS = [ResourceDestroyed::class, ResourceRestored::class, ResourceForceDestroyed::class];

    /** @var list<ResourceDestroyed|ResourceRestored|ResourceForceDestroyed> */
    private array $events = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-01 09:00:00');
        DeletionTrace::reset();
        QueuedWriteListener::$events = [];
        Gate::policy(Document::class, TracingDocumentPolicy::class);
        Rest::resource('tracing-documents', TracingDocumentController::class)
            ->withSoftDeletes()
            ->register();
        Event::listen(self::EVENTS, function (ResourceDestroyed|ResourceRestored|ResourceForceDestroyed $event): void {
            $this->events[] = $event;
            DeletionTrace::$lines[] = class_basename($event).' '.implode(',', $event instanceof ResourceRestored ? $event->models->modelKeys() : $event->keys);
        });
    }

    /** @return array<string, array{string, string, ?string, list<string>, array<string, mixed>}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/tracing-documents', null, [
                'destroyedAll 3,1', 'committed deleted 3', 'committed deleted 1', 'commit level 1', 'ResourceDestroyed 3,1',
            ], ['resource' => TracingDocumentResource::class, 'model' => Document::class, 'keys' => [3, 1]]],
            'restore' => ['POST', '/tracing-documents/restore', self::TRASHED, [
                'restoredAll 3,1', 'committed restored 3', 'committed restored 1', 'commit level 1', 'ResourceRestored 3,1',
            ], ['resource' => TracingDocumentResource::class, 'models' => [[3, 'Charter', null], [1, 'Annual report', null]]]],
            'force destroy' => ['DELETE', '/tracing-documents/force', self::TRASHED, [
                'forceDestroyedAll 3,1', 'committed deleted 3', 'committed forceDeleted 3', 'committed deleted 1', 'committed forceDeleted 1', 'commit level 1', 'ResourceForceDestroyed 3,1',
            ], ['resource' => TracingDocumentResource::class, 'model' => Document::class, 'keys' => [3, 1]]],
        ];
    }

    /**
     * @param  list<string>  $tail
     * @param  array<string, mixed>  $payload
     */
    #[Test]
    #[DataProvider('endpoints')]
    public function it_dispatches_the_event_of_the_endpoint_with_its_rows_once_they_are_committed(string $method, string $uri, ?string $deletedAt, array $tail, array $payload): void
    {
        $this->seedDocuments($deletedAt, $deletedAt, $deletedAt);
        DeletionTrace::listen();

        $response = $this->json($method, $uri, ['resources' => [3, 1]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3, 1]);
        $this->assertSame($tail, array_slice(DeletionTrace::$lines, -count($tail)));
        $this->assertCount(1, $this->events);
        $this->assertSame($payload, $this->payload($this->events[0]));
    }

    /**
     * @param  list<string>  $tail
     * @param  array<string, mixed>  $payload
     */
    #[Test]
    #[DataProvider('endpoints')]
    public function it_dispatches_an_event_that_serializes(string $method, string $uri, ?string $deletedAt, array $tail, array $payload): void
    {
        $this->seedDocuments($deletedAt, $deletedAt, $deletedAt);

        $this->json($method, $uri, ['resources' => [3, 1]])->assertOk();

        $this->assertCount(1, $this->events);
        $this->assertSame($payload, $this->payload(unserialize(serialize($this->events[0]))));
    }

    /**
     * @param  list<string>  $tail
     * @param  array<string, mixed>  $payload
     */
    #[Test]
    #[DataProvider('endpoints')]
    #[WithConfig('queue.default', 'database')]
    public function it_hands_the_event_to_a_queued_listener_in_the_worker(string $method, string $uri, ?string $deletedAt, array $tail, array $payload): void
    {
        $this->seedDocuments($deletedAt, $deletedAt, $deletedAt);
        Event::listen(self::EVENTS, QueuedWriteListener::class);

        $this->json($method, $uri, ['resources' => [3, 1]])->assertOk();
        $this->assertSame([], QueuedWriteListener::$events);
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(1, QueuedWriteListener::$events);
        $this->assertNotSame($this->events[0], QueuedWriteListener::$events[0]);
        $this->assertSame($payload, $this->payload(QueuedWriteListener::$events[0]));
    }

    /** @return array<string, array{string, string, ?string, string}> */
    public static function failures(): array
    {
        return [
            'destroyedAll' => ['DELETE', '/tracing-documents', null, 'destroyedAll 1,2'],
            'restored' => ['POST', '/tracing-documents/restore', self::TRASHED, 'restored 2'],
            'forceDestroying' => ['DELETE', '/tracing-documents/force', self::TRASHED, 'forceDestroying 1'],
        ];
    }

    #[Test]
    #[DataProvider('failures')]
    public function it_dispatches_no_event_when_the_rows_roll_back(string $method, string $uri, ?string $deletedAt, string $failure): void
    {
        $this->seedDocuments($deletedAt, $deletedAt);
        DeletionTrace::$failure = $failure;

        $response = $this->json($method, $uri, ['resources' => [1, 2]]);

        $response->assertInternalServerError();
        $this->assertSame([], $this->events);
    }

    #[Test]
    public function it_dispatches_the_event_when_the_transaction_of_the_application_commits(): void
    {
        $this->seedDocuments(null, null);
        DB::beginTransaction();

        $this->deleteJson('/tracing-documents', ['resources' => [2]])->assertOk();
        DeletionTrace::$lines[] = 'application commit';
        DB::commit();

        $this->assertSame(['destroyedAll 2', 'application commit', 'ResourceDestroyed 2'], array_slice(DeletionTrace::$lines, -3));
        $this->assertCount(1, $this->events);
    }

    #[Test]
    public function it_dispatches_no_event_when_the_transaction_of_the_application_rolls_back(): void
    {
        $this->seedDocuments(null, null);
        DB::beginTransaction();

        $this->deleteJson('/tracing-documents', ['resources' => [2]])->assertOk();
        DB::rollBack();

        $this->assertSame([], $this->events);
        $this->assertSame([[1, null], [2, null]], $this->rows());
    }

    /** @return array<string, mixed> */
    private function payload(object $event): array
    {
        if (! $event instanceof ResourceRestored) {
            return get_object_vars($event);
        }

        return [
            'resource' => $event->resource,
            'models' => $event->models->map(fn (Document $document): array => [$document->id, $document->title, $document->deleted_at])->all(),
        ];
    }
}
