<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\RuledPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResourceRulesTest extends PackageTestCase
{
    private const array POST = ['title' => 'Annual report', 'status' => 'draft', 'published_on' => '2026-01-01'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('ruled-posts', RuledPostController::class)->register();
    }

    #[Test]
    public function it_merges_the_resource_rules_with_the_field_rules_key_by_key(): void
    {
        $response = $this->mutate('/ruled-posts/mutate', [[
            'operation' => 'create',
            'attributes' => [...self::POST, 'title' => 'AB', 'featured' => true, 'metadata' => ['source' => 5]],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title field must be at least 3 characters.'],
            'mutate.0.attributes.summary' => ['The mutate.0.attributes.summary field is required when mutate.0.attributes.featured is true.'],
            'mutate.0.attributes.metadata.source' => ['The mutate.0.attributes.metadata.source field must be a string.'],
        ]);
        $this->assertSame(0, Post::query()->count());
    }

    #[Test]
    public function it_applies_the_resource_update_rules_on_update_only(): void
    {
        $post = Post::factory()->create(['title' => 'Quarterly report']);

        $response = $this->mutate('/ruled-posts/mutate', [
            ['operation' => 'create', 'attributes' => [...self::POST, 'views' => 2000000]],
            ['operation' => 'update', 'key' => $post->id, 'attributes' => ['views' => 2000000]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.1.attributes.views' => ['The mutate.1.attributes.views field must not be greater than 1000000.'],
        ]);
        $this->assertSame(0, $post->fresh()?->views);
    }

    #[Test]
    public function it_checks_a_unique_rule_once_before_the_write(): void
    {
        Post::factory()->create(['title' => 'Annual report']);

        $taken = $this->mutate('/ruled-posts/mutate', [['operation' => 'create', 'attributes' => self::POST]]);
        $created = $this->mutate('/ruled-posts/mutate', [['operation' => 'create', 'attributes' => [...self::POST, 'title' => 'Quarterly report']]]);

        $taken->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title has already been taken.'],
        ]);
        $quarterly = Post::query()->where('title', 'Quarterly report')->sole();
        $created->assertOk()->assertExactJson(['created' => [$quarterly->id], 'updated' => []]);
        $this->assertSame(2, Post::query()->count());
    }

    #[Test]
    #[DataProvider('pivotOperations')]
    public function it_applies_the_resource_update_rules_on_sync_and_toggle(string $operation): void
    {
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        $ada = Author::factory()->create();
        $laravel = Tag::factory()->create();

        $response = $this->postJson('/api/authors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['tags' => [['operation' => $operation, 'key' => $laravel->id, 'attributes' => ['name' => str_repeat('a', 21)]]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.tags.0.attributes.name' => ['The mutate.0.relations.tags.0.attributes.name field must not be greater than 20 characters.'],
        ]);
        $this->assertSame('Laravel', $laravel->fresh()?->name);
        $this->assertSame([], $ada->tags()->pluck('tags.id')->all());
    }

    /** @return array<string, array{string}> */
    public static function pivotOperations(): array
    {
        return ['sync' => ['sync'], 'toggle' => ['toggle']];
    }

    /** @param list<array<string, mixed>> $mutations */
    private function mutate(string $uri, array $mutations): TestResponse
    {
        return $this->postJson($uri, ['mutate' => $mutations]);
    }
}
