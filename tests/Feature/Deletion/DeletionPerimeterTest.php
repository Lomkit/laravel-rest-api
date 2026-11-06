<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\PerimeterDocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DeletionPerimeterTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('perimeter-documents', PerimeterDocumentController::class)
            ->withSoftDeletes()
            ->register();
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_refuses_a_row_outside_the_perimeter(string $method, string $uri, ?string $deletedAt): void
    {
        Document::query()->insert(['id' => 1, 'title' => 'Hidden', 'archived' => false, 'deleted_at' => $deletedAt]);

        $response = $this->json($method, $uri, ['resources' => [1]]);

        $this->assertExactErrors($response, ['resources.0' => ['The selected resources.0 is invalid.']]);
        $this->assertSame([[1, $deletedAt]], self::documents());
    }

    #[Test]
    #[DataProvider('endpointsAndReads')]
    public function it_reads_the_rows_through_the_perimeter(string $method, string $uri, ?string $deletedAt, string $read): void
    {
        Document::query()->insert(['id' => 1, 'title' => 'Annual report', 'archived' => false, 'deleted_at' => $deletedAt]);
        $this->captureQueries();

        $this->json($method, $uri, ['resources' => [1]])->assertOk();

        $this->assertSame([[$read, ['Hidden']]], $this->readsOf('documents'));
    }

    /** @return array<string, array{string, string, ?string, string}> */
    public static function endpointsAndReads(): array
    {
        $read = 'select * from documents where (documents.title <> ?) and documents.id in (1)';

        return [
            'destroy' => [...self::endpoints()['destroy'], $read.' and documents.deleted_at is null'],
            'restore' => [...self::endpoints()['restore'], $read],
            'force destroy' => [...self::endpoints()['force destroy'], $read],
        ];
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/perimeter-documents', null],
            'restore' => ['POST', '/perimeter-documents/restore', '2026-01-01 00:00:00'],
            'force destroy' => ['DELETE', '/perimeter-documents/force', '2026-01-01 00:00:00'],
        ];
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
