<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class UnconvertedEmptySearchTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Author::class, OpenPublishingPolicy::class);
        $this->withoutMiddleware(ConvertEmptyStringsToNull::class);
    }

    #[Test]
    public function it_returns_the_destroyed_rows_for_an_empty_search_string(): void
    {
        $ada = Author::factory()->create();

        $response = $this->deleteJson('/api/authors', ['resources' => [$ada->id], 'search' => '']);

        $response->assertOk()->assertExactJson($this->rows($ada));
        $this->assertSoftDeleted($ada);
    }

    #[Test]
    public function it_returns_the_restored_rows_for_an_empty_search_string(): void
    {
        $ada = Author::factory()->create();
        $ada->delete();

        $response = $this->postJson('/api/authors/restore', ['resources' => [$ada->id], 'search' => '']);

        $response->assertOk()->assertExactJson($this->rows($ada));
        $this->assertNotSoftDeleted($ada);
    }

    #[Test]
    public function it_returns_the_force_destroyed_rows_for_an_empty_search_string(): void
    {
        $ada = Author::factory()->create();
        $ada->delete();

        $response = $this->deleteJson('/api/authors/force', ['resources' => [$ada->id], 'search' => '']);

        $response->assertOk()->assertExactJson($this->rows($ada));
        $this->assertModelMissing($ada);
    }

    /** @return array<string, mixed> */
    private function rows(Author $author): array
    {
        return [
            'data' => [
                ['id' => $author->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null],
            ],
            'meta' => [],
        ];
    }
}
