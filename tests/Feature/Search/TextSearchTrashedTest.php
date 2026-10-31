<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\SearchableDocument;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextSearchTrashedTest extends PackageTestCase
{
    private SearchableDocument $kept;

    private SearchableDocument $gone;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_leaves_the_trashed_documents_out_of_a_text_search(string $driver): void
    {
        $this->seedDocuments($driver);

        $response = $this->postJson('/api/searchable-documents/search', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson($this->page([$this->row($this->kept)]));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_adds_the_trashed_documents_to_a_text_search_with_trashed(string $driver): void
    {
        $this->seedDocuments($driver);

        $response = $this->postJson('/api/searchable-documents/search', [
            'search' => ['text' => ['value' => 'report'], 'trashed' => 'with'],
        ]);

        $response->assertOk()->assertExactJson($this->page([$this->row($this->gone), $this->row($this->kept)]));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_returns_only_the_trashed_documents_for_a_text_search_only_trashed(string $driver): void
    {
        $this->seedDocuments($driver);

        $response = $this->postJson('/api/searchable-documents/search', [
            'search' => ['text' => ['value' => 'report'], 'trashed' => 'only'],
        ]);

        $response->assertOk()->assertExactJson($this->page([$this->row($this->gone)]));
    }

    /** @return array<string, array{string}> */
    public static function drivers(): array
    {
        return [
            'collection' => ['collection'],
            'database' => ['database'],
        ];
    }

    private function seedDocuments(string $driver): void
    {
        config(['scout.driver' => $driver, 'scout.soft_delete' => true]);

        $this->kept = SearchableDocument::factory()->create(['title' => 'Report kept']);
        $this->gone = SearchableDocument::factory()->trashed()->create(['title' => 'Report gone']);
    }

    /** @return array<string, int|string> */
    private function row(SearchableDocument $document): array
    {
        return ['id' => $document->id, 'title' => $document->title];
    }

    /**
     * @param  list<array<string, int|string>>  $rows
     * @return array<string, mixed>
     */
    private function page(array $rows): array
    {
        return [
            'current_page' => 1,
            'data' => $rows,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => count($rows),
            'total' => count($rows),
            'meta' => [],
        ];
    }
}
