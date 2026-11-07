<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\TouchDocumentController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\TouchDocuments;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

class ImpactedRowsTest extends PackageTestCase
{
    private const array CHUNKS = [[11, 10, 9, 8], [7, 6, 5, 4], [3, 2, 1]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        TouchDocuments::reset();
        Rest::resource('touch-documents', TouchDocumentController::class)->register();

        foreach (range(1, 12) as $id) {
            Document::create(['id' => $id, 'title' => "Document {$id}", 'archived' => $id === 12]);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<list<int>>  $runs
     */
    #[Test]
    #[DataProvider('searches')]
    public function it_counts_the_rows_handed_to_the_action(array $body, array $runs, int $impacted): void
    {
        $response = $this->postJson('/touch-documents/actions/touch-documents', $body);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, 'result' => null]]);
        $this->assertSame($runs, TouchDocuments::$runs);
    }

    /** @return array<string, array{array<string, mixed>, list<list<int>>, int}> */
    public static function searches(): array
    {
        return [
            'every row of the perimeter' => [[], [[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11]], 11],
            'a limit above the matching rows' => [['search' => ['limit' => 50]], self::CHUNKS, 11],
            'a limit below the matching rows' => [['search' => ['limit' => 10]], [[11, 10, 9, 8], [7, 6, 5, 4], [3, 2]], 10],
            'a filter with a limit above its rows' => [['search' => ['filters' => [['field' => 'title', 'value' => 'Document 2']], 'limit' => 25]], [[2]], 1],
        ];
    }

    #[Test]
    public function it_counts_the_rows_put_into_jobs(): void
    {
        Queue::fake();

        $response = $this->postJson('/touch-documents/actions/queued-touch-documents', ['search' => ['limit' => 50]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 11, 'result' => null]]);
        $this->assertSame(self::CHUNKS, Queue::pushed(QueuedAction::class)->map(fn (QueuedAction $job): array => $job->rows->keys)->all());
    }

    #[Test]
    public function it_counts_the_rows_put_into_a_batch(): void
    {
        Bus::fake();
        Str::createUuidsUsingSequence([Uuid::fromString('9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f')]);

        $response = $this->postJson('/touch-documents/actions/batched-touch-documents', ['search' => ['limit' => 50]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 11, 'batch' => '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f', 'result' => null]]);
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->map(fn (QueuedAction $job): array => $job->rows->keys)->all() === self::CHUNKS);
    }
}
