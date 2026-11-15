<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Writes;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Deletion\Concerns\SeedsDocuments;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WrittenDocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

trait WritesDocuments
{
    use SeedsDocuments;

    private const string TRASHED = '2026-01-01 00:00:00';

    private const array MUTATE = ['mutate' => [
        ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Annual summary']],
        ['operation' => 'create', 'attributes' => ['title' => 'Minutes']],
    ]];

    private const array DOCUMENTS = ['data' => [
        ['id' => 1, 'title' => 'Annual report', 'archived' => false],
        ['id' => 2, 'title' => 'Budget', 'archived' => false],
    ], 'meta' => []];

    private const array IMPACTED = ['data' => ['impacted' => 2, 'result' => null]];

    private ArchiveUser $grace;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    /** @return array<string, array{string, string, ?string, Endpoint, list<string>}> */
    public static function rowBatches(): array
    {
        return [
            'destroy' => ['DELETE', '/written-documents', null, Endpoint::Destroy, ['destroyingAll 1,2', 'sql update documents', 'sql update documents', 'destroyedAll 1,2']],
            'restore' => ['POST', '/written-documents/restore', self::TRASHED, Endpoint::Restore, ['restoringAll 1,2', 'sql update documents', 'sql update documents', 'restoredAll 1,2']],
            'force destroy' => ['DELETE', '/written-documents/force', self::TRASHED, Endpoint::ForceDestroy, ['forceDestroyingAll 1,2', 'sql delete documents', 'sql delete documents', 'forceDestroyedAll 1,2']],
        ];
    }

    /** @param class-string $extension */
    private function registerWrittenDocuments(string $extension): void
    {
        config(['rest.extensions' => [$extension]]);
        Rest::resource('written-documents', WrittenDocumentController::class)
            ->withSoftDeletes()
            ->register();
        ArchiveUser::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $this->grace = ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.com', 'password' => 'secret']);
        $this->actingAs($this->grace);
    }

    /** @return array{operations: list<array{operation: string, key: int}>} */
    private static function operations(int $minutes): array
    {
        return ['operations' => [['operation' => 'update', 'key' => 1], ['operation' => 'create', 'key' => $minutes]]];
    }

    private function minutes(): ?int
    {
        return Document::withTrashed()
            ->where('title', 'Minutes')
            ->value('id');
    }

    /** @return list<string> */
    private function writeLines(): array
    {
        return array_values(array_filter(EventLog::$lines, fn (string $line): bool => ! str_starts_with($line, 'sql select')));
    }

    /** @return list<array{int, string, bool}> */
    private function documents(): array
    {
        return Document::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [$document->id, $document->title, $document->archived])
            ->all();
    }
}
