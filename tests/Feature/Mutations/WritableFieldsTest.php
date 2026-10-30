<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class WritableFieldsTest extends PackageTestCase
{
    private const string OUTLINE = '0b9e6c3a-2f4d-4e5b-8a7c-1d2e3f4a5b6c';

    private const string SKETCH = '7c1d2e3f-4a5b-4c6d-9e8f-0a1b2c3d4e5f';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Author::class, OpenPublishingPolicy::class);
    }

    #[Test]
    public function it_refuses_the_key_a_readonly_field_and_an_undeclared_key_on_create(): void
    {
        $response = $this->mutate([
            'operation' => 'create',
            'attributes' => ['id' => 5, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'grace-token', 'created_at' => '2026-01-01 09:00:00', 'nickname' => 'Amazing Grace'],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes' => ['The id attribute is not writable on create.', 'The created_at attribute is not writable on create.', 'The nickname attribute is not writable on create.'],
        ]);
        $this->assertSame(0, Author::query()->count());
    }

    #[Test]
    public function it_refuses_the_key_a_readonly_field_and_an_undeclared_key_on_update(): void
    {
        $ada = Author::factory()->create(['created_at' => '2026-01-01 09:00:00']);

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'attributes' => ['id' => $ada->id + 1, 'first_name' => 'Augusta', 'created_at' => '2026-02-01 09:00:00', 'nickname' => 'Enchantress'],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes' => ['The id attribute is not writable on update.', 'The created_at attribute is not writable on update.', 'The nickname attribute is not writable on update.'],
        ]);
        $this->assertSame([[$ada->id, 'Ada', '2026-01-01 09:00:00']], Author::query()->get()->map(fn (Author $author): array => [$author->id, $author->first_name, $author->created_at?->toDateTimeString()])->all());
    }

    #[Test]
    public function it_writes_a_creatable_key_on_create(): void
    {
        $ada = Author::factory()->create();

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['drafts' => [['operation' => 'create', 'attributes' => ['id' => self::OUTLINE, 'title' => 'Outline']]]],
        ]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$ada->id]]);
        $this->assertSame([self::OUTLINE => 'Outline'], Draft::query()->pluck('title', 'id')->all());
    }

    #[Test]
    public function it_refuses_a_creatable_key_on_update(): void
    {
        $ada = Author::factory()->create();
        Draft::factory()->for($ada)->create(['id' => self::OUTLINE, 'title' => 'Outline']);

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['drafts' => [['operation' => 'update', 'key' => self::OUTLINE, 'attributes' => ['id' => self::SKETCH, 'title' => 'Sketch']]]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.drafts.0.attributes' => ['The id attribute is not writable on update.'],
        ]);
        $this->assertSame([self::OUTLINE => 'Outline'], Draft::query()->pluck('title', 'id')->all());
    }

    #[Test]
    public function it_writes_a_field_that_is_not_updatable_on_create(): void
    {
        $ada = Author::factory()->create();

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['tags' => [['operation' => 'create', 'attributes' => ['name' => 'PHP', 'slug' => 'php']]]],
        ]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$ada->id]]);
        $this->assertSame([['name' => 'PHP', 'slug' => 'php']], $ada->tags()->get()->map(fn (Tag $tag): array => $tag->only('name', 'slug'))->all());
    }

    #[Test]
    #[DataProvider('updatingOperations')]
    public function it_refuses_a_field_that_is_not_updatable_on_update_sync_and_toggle(string $operation): void
    {
        $ada = Author::factory()->create();
        $laravel = Tag::factory()->create();

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['tags' => [['operation' => $operation, 'key' => $laravel->id, 'attributes' => ['name' => 'PHP', 'slug' => 'php']]]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.tags.0.attributes' => ['The slug attribute is not writable on update.'],
        ]);
        $this->assertSame(['Laravel', 'laravel'], [$laravel->fresh()?->name, $laravel->fresh()?->slug]);
    }

    /** @return array<string, array{string}> */
    public static function updatingOperations(): array
    {
        return ['update' => ['update'], 'sync' => ['sync'], 'toggle' => ['toggle']];
    }

    #[Test]
    #[DataProvider('updatingOperations')]
    public function it_writes_the_updatable_fields_on_update_sync_and_toggle(string $operation): void
    {
        $ada = Author::factory()->create();
        $laravel = Tag::factory()->create();

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['tags' => [['operation' => $operation, 'key' => $laravel->id, 'attributes' => ['name' => 'Laravel 13', 'archived_at' => '2026-03-01 09:00:00']]]],
        ]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$ada->id]]);
        $this->assertSame(
            [['name' => 'Laravel 13', 'slug' => 'laravel', 'archived_at' => '2026-03-01 09:00:00']],
            $ada->tags()->get()->map(fn (Tag $tag): array => $tag->only('name', 'slug', 'archived_at'))->all(),
        );
    }

    #[Test]
    public function it_refuses_a_field_that_is_not_creatable_on_create(): void
    {
        $ada = Author::factory()->create();

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['tags' => [['operation' => 'create', 'attributes' => ['name' => 'PHP', 'slug' => 'php', 'archived_at' => '2026-03-01 09:00:00']]]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.tags.0.attributes' => ['The archived_at attribute is not writable on create.'],
        ]);
        $this->assertSame(0, Tag::query()->count());
    }

    /** @param array<string, mixed> $mutation */
    private function mutate(array $mutation): TestResponse
    {
        return $this->postJson('/api/authors/mutate', ['mutate' => [$mutation]]);
    }
}
