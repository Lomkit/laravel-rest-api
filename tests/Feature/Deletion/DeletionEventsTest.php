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
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class DeletionEventsTest extends PackageTestCase
{
    use SeedsDocuments;

    private const string TRASHED = '2026-01-01 00:00:00';

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
        Gate::policy(Document::class, TracingDocumentPolicy::class);
        Rest::resource('tracing-documents', TracingDocumentController::class)
            ->withSoftDeletes()
            ->register();
        Event::listen([ResourceDestroyed::class, ResourceRestored::class, ResourceForceDestroyed::class], function (ResourceDestroyed|ResourceRestored|ResourceForceDestroyed $event): void {
            $this->events[] = $event;
            DeletionTrace::$lines[] = class_basename($event).' '.implode(',', $event->models->modelKeys());
        });
    }

    /** @return array<string, array{string, string, ?string, class-string, list<string>}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/tracing-documents', null, ResourceDestroyed::class, [
                'destroyedAll 3,1', 'committed deleted 3', 'committed deleted 1', 'commit level 1', 'ResourceDestroyed 3,1',
            ]],
            'restore' => ['POST', '/tracing-documents/restore', self::TRASHED, ResourceRestored::class, [
                'restoredAll 3,1', 'committed restored 3', 'committed restored 1', 'commit level 1', 'ResourceRestored 3,1',
            ]],
            'force destroy' => ['DELETE', '/tracing-documents/force', self::TRASHED, ResourceForceDestroyed::class, [
                'forceDestroyedAll 3,1', 'committed deleted 3', 'committed forceDeleted 3', 'committed deleted 1', 'committed forceDeleted 1', 'commit level 1', 'ResourceForceDestroyed 3,1',
            ]],
        ];
    }

    /**
     * @param  class-string  $event
     * @param  list<string>  $tail
     */
    #[Test]
    #[DataProvider('endpoints')]
    public function it_dispatches_the_event_of_the_endpoint_with_its_models_once_the_rows_are_committed(string $method, string $uri, ?string $deletedAt, string $event, array $tail): void
    {
        $this->seedDocuments($deletedAt, $deletedAt, $deletedAt);
        DeletionTrace::listen();

        $response = $this->json($method, $uri, ['resources' => [3, 1]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3, 1]);
        $this->assertSame($tail, array_slice(DeletionTrace::$lines, -count($tail)));
        $this->assertCount(1, $this->events);
        $this->assertInstanceOf($event, $this->events[0]);
        $this->assertInstanceOf(TracingDocumentResource::class, $this->events[0]->resource);
        $this->assertSame([3, 1], $this->events[0]->models->modelKeys());
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
}
