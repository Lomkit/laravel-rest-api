<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Concerns;

use Lomkit\Rest\Tests\Fixtures\Archive\Document;

trait SeedsDocuments
{
    private function seedDocuments(?string ...$deletedAt): void
    {
        foreach (array_values($deletedAt) as $index => $at) {
            Document::query()->insert(['id' => $index + 1, 'title' => ['Annual report', 'Budget', 'Charter'][$index] ?? 'Report '.($index + 1), 'deleted_at' => $at]);
        }
    }

    /** @return list<array{int, ?string}> */
    private function rows(): array
    {
        return Document::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [$document->id, $document->deleted_at?->format('Y-m-d H:i:s')])
            ->all();
    }
}
