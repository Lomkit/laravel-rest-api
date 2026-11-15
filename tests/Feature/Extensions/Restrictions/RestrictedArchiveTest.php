<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\KeyedDocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [HiddenRowsExtension::class])]
class RestrictedArchiveTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;
    use ResetsExtensionStubs;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    #[DataProvider('deletions')]
    public function it_refuses_to_delete_or_restore_a_row_that_a_restriction_hides(string $method, string $uri, ?string $deletedAt): void
    {
        Document::query()->insert(['id' => 1, 'title' => 'Hidden', 'archived' => false, 'deleted_at' => $deletedAt]);

        $response = $this->json($method, $uri, ['resources' => [1]]);

        $this->assertExactErrors($response, ['resources.0' => ['The selected resources.0 is invalid.']]);
        $this->assertSame([[1, $deletedAt]], self::documents());
    }

    #[Test]
    #[DataProvider('deletionReads')]
    public function it_reads_the_rows_to_delete_or_restore_through_the_restriction(string $method, string $uri, ?string $deletedAt, string $read): void
    {
        Document::query()->insert(['id' => 1, 'title' => 'Annual report', 'archived' => false, 'deleted_at' => $deletedAt]);
        $this->captureQueries();

        $this->json($method, $uri, ['resources' => [1]])->assertOk();

        $this->assertSame([[$read, ['Hidden']]], $this->readsOf('documents'));
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function deletions(): array
    {
        return [
            'destroy' => ['DELETE', '/api/documents', null],
            'restore' => ['POST', '/api/documents/restore', '2026-01-01 00:00:00'],
            'force destroy' => ['DELETE', '/api/documents/force', '2026-01-01 00:00:00'],
        ];
    }

    /** @return array<string, array{string, string, ?string, string}> */
    public static function deletionReads(): array
    {
        $read = 'select * from documents where ((documents.title != ?)) and documents.id in (1)';

        return [
            'destroy' => [...self::deletions()['destroy'], $read.' and documents.deleted_at is null'],
            'restore' => [...self::deletions()['restore'], $read],
            'force destroy' => [...self::deletions()['force destroy'], $read],
        ];
    }

    #[Test]
    public function it_refuses_an_action_on_a_key_that_a_restriction_hides(): void
    {
        Document::query()->insert([['id' => 1, 'title' => 'Budget', 'archived' => false], ['id' => 2, 'title' => 'Hidden', 'archived' => false]]);

        Rest::resource('keyed-documents', KeyedDocumentController::class)->register();

        $response = $this->postJson('/keyed-documents/actions/archive-documents', ['resources' => [1, 2]]);

        $this->assertExactErrors($response, ['resources.1' => ['The selected resources.1 is invalid.']]);
        $this->assertSame([false, false], Document::query()->orderBy('id')->pluck('archived')->all());
    }

    #[Test]
    public function it_reads_the_rows_of_a_queued_action_again_through_the_restriction(): void
    {
        Document::query()->insert([['id' => 1, 'title' => 'Budget', 'archived' => false], ['id' => 2, 'title' => 'Contract', 'archived' => false]]);
        Queue::fake();
        $this->postJson('/api/documents/actions/queued-archive-documents', ['search' => []])->assertOk();
        $job = Queue::pushed(QueuedAction::class)->sole();
        Document::query()->whereKey(2)->update(['title' => 'Hidden']);
        $this->captureQueries();

        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));
        $this->app?->call([$worker, 'handle']);

        $this->assertSame([
            ['select * from documents where ((documents.title != ?)) and documents.id in (1, 2) and documents.deleted_at is null', ['Hidden']],
        ], $this->readsOf('documents'));
        $this->assertSame([true, false], Document::query()->orderBy('id')->pluck('archived')->all());
    }

    #[Test]
    public function it_reads_the_write_reach_of_rendered_rows_through_the_restriction(): void
    {
        Document::query()->insert([
            ['id' => 1, 'title' => 'Budget', 'archived' => false, 'deleted_at' => null],
            ['id' => 2, 'title' => 'Contract', 'archived' => false, 'deleted_at' => '2026-01-01 00:00:00'],
            ['id' => 3, 'title' => 'Hidden', 'archived' => false, 'deleted_at' => null],
        ]);
        $this->captureQueries();

        $response = $this->postJson('/api/documents/search', ['search' => ['trashed' => 'with', 'gates' => ['update'], 'selects' => [['field' => 'id']]]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [2, 1])
            ->assertJsonPath('data.*.gates.authorized_to_update.allowed', [false, true]);
        $this->assertSame(
            [['select documents.id from documents where ((documents.title != ?)) and documents.id in (2, 1) and documents.deleted_at is null', ['Hidden']]],
            array_slice($this->readsOf('documents'), 1),
        );
    }

    /** @return list<array{int, ?string}> */
    private static function documents(): array
    {
        return Document::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [(int) $document->getKey(), $document->getRawOriginal('deleted_at')])
            ->all();
    }
}
