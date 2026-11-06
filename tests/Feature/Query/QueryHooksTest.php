<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class QueryHooksTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_searches_within_the_perimeter(): void
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

        $response = $this->deleteJson('/api/current-documents', ['resources' => [$current->id]]);

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
        Document::factory()->create(['title' => 'Old ledger', 'archived' => true, 'deleted_at' => '2026-01-01 00:00:00']);

        $response = $this->postJson('/api/current-documents/restore', ['resources' => [$current->id]]);

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

        $response = $this->deleteJson('/api/current-documents/force', ['resources' => [$current->id]]);

        $response->assertOk()->assertExactJson([
            'data' => [
                ['id' => $current->id, 'title' => 'Annual report'],
            ],
            'meta' => [],
        ]);
        $this->assertSame([$archived->id], Document::withTrashed()->pluck('id')->all());
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_refuses_a_key_outside_the_query_hook(string $method, string $uri, ?string $deletedAt): void
    {
        $current = Document::factory()->create(['id' => 1, 'title' => 'Annual report', 'deleted_at' => $deletedAt]);
        $archived = Document::factory()->create(['id' => 2, 'title' => 'Old ledger', 'archived' => true, 'deleted_at' => $deletedAt]);

        $response = $this->json($method, $uri, ['resources' => [$current->id, $archived->id]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected resources.1 is invalid.',
            'errors' => ['resources.1' => ['The selected resources.1 is invalid.']],
        ]);
        $this->assertDatabaseHas('documents', ['id' => 1, 'deleted_at' => $deletedAt]);
        $this->assertDatabaseHas('documents', ['id' => 2, 'deleted_at' => $deletedAt]);
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/api/current-documents', null],
            'restore' => ['POST', '/api/current-documents/restore', '2026-01-01 00:00:00'],
            'force destroy' => ['DELETE', '/api/current-documents/force', '2026-01-01 00:00:00'],
        ];
    }
}
