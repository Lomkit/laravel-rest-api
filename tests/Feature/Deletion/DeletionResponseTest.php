<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DeletionResponseTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Author::class, OpenPublishingPolicy::class);
    }

    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_search_on_destroy(mixed $search, string $message): void
    {
        $ada = Author::factory()->create();

        $response = $this->deleteJson('/api/authors', ['resources' => [$ada->id], 'search' => $search]);

        $this->assertSearchRefused($response, $message);
        $this->assertNotSoftDeleted($ada);
    }

    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_search_on_restore(mixed $search, string $message): void
    {
        $ada = Author::factory()->create();
        $ada->delete();

        $response = $this->postJson('/api/authors/restore', ['resources' => [$ada->id], 'search' => $search]);

        $this->assertSearchRefused($response, $message);
        $this->assertSoftDeleted($ada);
    }

    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_search_on_force_destroy(mixed $search, string $message): void
    {
        $ada = Author::factory()->create();
        $ada->delete();

        $response = $this->deleteJson('/api/authors/force', ['resources' => [$ada->id], 'search' => $search]);

        $this->assertSearchRefused($response, $message);
        $this->assertSoftDeleted($ada);
    }

    /** @return array<string, array{mixed, string}> */
    public static function searches(): array
    {
        $prohibited = 'The search field is prohibited.';
        $notAnArray = 'The search field must be an array.';

        return [
            'a write only field in selects' => [['selects' => [['field' => 'api_token']]], $prohibited],
            'a selectable field in selects' => [['selects' => [['field' => 'first_name']]], $prohibited],
            'an aggregate alias naming a write only field' => [['aggregates' => [['relation' => 'posts', 'type' => 'count', 'alias' => 'api_token']]], $prohibited],
            'gates' => [['gates' => ['view', 'update', 'delete']], $prohibited],
            'a string' => ['view', $prohibited],
            'null' => [null, $notAnArray],
            'an empty string' => ['', $notAnArray],
            'a blank string' => [' ', $notAnArray],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('payloadsWithoutSearch')]
    public function it_returns_the_selectable_fields_of_the_destroyed_rows(array $payload): void
    {
        $ada = Author::factory()->create(['settings' => ['theme' => 'dark']]);

        $response = $this->deleteJson('/api/authors', ['resources' => [$ada->id], ...$payload]);

        $response->assertOk()->assertExactJson($this->rows($ada));
        $this->assertSoftDeleted($ada);
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('payloadsWithoutSearch')]
    public function it_returns_the_selectable_fields_of_the_restored_rows(array $payload): void
    {
        $ada = Author::factory()->create(['settings' => ['theme' => 'dark']]);
        $ada->delete();

        $response = $this->postJson('/api/authors/restore', ['resources' => [$ada->id], ...$payload]);

        $response->assertOk()->assertExactJson($this->rows($ada));
        $this->assertNotSoftDeleted($ada);
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('payloadsWithoutSearch')]
    public function it_returns_the_selectable_fields_of_the_force_destroyed_rows(array $payload): void
    {
        $ada = Author::factory()->create(['settings' => ['theme' => 'dark']]);
        $ada->delete();

        $response = $this->deleteJson('/api/authors/force', ['resources' => [$ada->id], ...$payload]);

        $response->assertOk()->assertExactJson($this->rows($ada));
        $this->assertModelMissing($ada);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function payloadsWithoutSearch(): array
    {
        return [
            'without search' => [[]],
            'with an empty search' => [['search' => []]],
        ];
    }

    private function assertSearchRefused(TestResponse $response, string $message): void
    {
        $response->assertUnprocessable()->assertExactJson([
            'message' => $message,
            'errors' => ['search' => [$message]],
        ]);
    }

    /** @return array<string, mixed> */
    private function rows(Author $author): array
    {
        return [
            'data' => [
                ['id' => $author->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => ['theme' => 'dark']],
            ],
            'meta' => [],
        ];
    }
}
