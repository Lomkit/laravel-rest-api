<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class QueryHooksTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_searches_within_the_search_query_hook(): void
    {
        $current = Document::factory()->create(['title' => 'Annual report']);
        Document::factory()->create(['title' => 'Old ledger', 'archived' => true]);

        $this->postJson('/api/current-documents/search')->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [
                ['id' => $current->id, 'title' => 'Annual report'],
            ],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 1,
            'total' => 1,
            'meta' => [],
        ]);
    }

    #[Test]
    public function it_destroys_within_the_destroy_query_hook(): void
    {
        $current = Document::factory()->create(['title' => 'Annual report']);
        $archived = Document::factory()->create(['title' => 'Old ledger', 'archived' => true]);

        $response = $this->deleteJson('/api/current-documents', ['resources' => [$current->id, $archived->id]]);

        $response->assertOk()->assertExactJson([
            'data' => [
                ['id' => $current->id, 'title' => 'Annual report'],
            ],
            'meta' => [],
        ]);
        $this->assertSame([$archived->id], Document::query()->pluck('id')->all());
    }

    #[Test]
    public function it_restores_within_the_restore_query_hook(): void
    {
        $current = Document::factory()->create(['title' => 'Annual report', 'deleted_at' => '2026-01-01 00:00:00']);
        $archived = Document::factory()->create(['title' => 'Old ledger', 'archived' => true, 'deleted_at' => '2026-01-01 00:00:00']);

        $response = $this->postJson('/api/current-documents/restore', ['resources' => [$current->id, $archived->id]]);

        $response->assertOk()->assertExactJson([
            'data' => [
                ['id' => $current->id, 'title' => 'Annual report'],
            ],
            'meta' => [],
        ]);
        $this->assertSame([$current->id], Document::query()->pluck('id')->all());
    }

    #[Test]
    public function it_force_destroys_within_the_force_destroy_query_hook(): void
    {
        $current = Document::factory()->create(['title' => 'Annual report', 'deleted_at' => '2026-01-01 00:00:00']);
        $archived = Document::factory()->create(['title' => 'Old ledger', 'archived' => true, 'deleted_at' => '2026-01-01 00:00:00']);

        $response = $this->deleteJson('/api/current-documents/force', ['resources' => [$current->id, $archived->id]]);

        $response->assertOk()->assertExactJson([
            'data' => [
                ['id' => $current->id, 'title' => 'Annual report'],
            ],
            'meta' => [],
        ]);
        $this->assertSame([$archived->id], Document::withTrashed()->pluck('id')->all());
    }
}
