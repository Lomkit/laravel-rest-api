<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SharedDocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class HookGroupsTest extends PackageTestCase
{
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

        Rest::resource('shared-documents', SharedDocumentController::class)
            ->withSoftDeletes()
            ->register();
    }

    /** @param list<array{int, bool}> $after */
    #[Test]
    #[DataProvider('endpoints')]
    public function it_reaches_only_the_named_row_when_the_perimeter_holds_an_or(string $method, string $uri, ?string $deletedAt, string $read, array $after): void
    {
        foreach ([[1, 'Shared'], [2, 'Owned'], [3, 'Owned'], [4, 'Owned']] as [$id, $title]) {
            Document::query()->insert(['id' => $id, 'title' => $title, 'archived' => false, 'deleted_at' => $deletedAt]);
        }
        $this->captureQueries();

        $response = $this->json($method, $uri, ['resources' => [1]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
        $this->assertSame([[$read, ['Owned', 'Shared']]], $this->readsOf('documents'));
        $this->assertSame($after, self::documents());
    }

    /** @return array<string, array{string, string, ?string, string, list<array{int, bool}>}> */
    public static function endpoints(): array
    {
        $read = 'select * from documents where (documents.title = ? or documents.title = ?) and documents.id in (1)';
        $trashed = '2026-01-01 00:00:00';

        return [
            'destroy' => ['DELETE', '/shared-documents', null, $read.' and documents.deleted_at is null', [[1, true], [2, false], [3, false], [4, false]]],
            'restore' => ['POST', '/shared-documents/restore', $trashed, $read, [[1, false], [2, true], [3, true], [4, true]]],
            'force destroy' => ['DELETE', '/shared-documents/force', $trashed, $read, [[2, true], [3, true], [4, true]]],
        ];
    }

    /** @return list<array{int, bool}> */
    private static function documents(): array
    {
        return Document::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [(int) $document->getKey(), $document->trashed()])
            ->all();
    }
}
