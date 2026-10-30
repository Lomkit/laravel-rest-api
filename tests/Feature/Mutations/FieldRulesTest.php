<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class FieldRulesTest extends PackageTestCase
{
    private const array POST = ['title' => 'Annual report', 'status' => 'draft', 'published_on' => '2026-01-01'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_applies_the_base_rule_of_each_type_on_create(): void
    {
        $response = $this->mutate('/api/posts/mutate', [[
            'operation' => 'create',
            'attributes' => ['title' => ['Annual report'], 'summary' => true, 'status' => 'archived', 'featured' => 'often', 'views' => 'many', 'rating' => 'high', 'published_on' => 'soon', 'metadata' => 'none'],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title field must be a string.'],
            'mutate.0.attributes.summary' => ['The mutate.0.attributes.summary field must be a string.'],
            'mutate.0.attributes.status' => ['The selected mutate.0.attributes.status is invalid.'],
            'mutate.0.attributes.featured' => ['The mutate.0.attributes.featured field must be true or false.'],
            'mutate.0.attributes.views' => ['The mutate.0.attributes.views field must be an integer.'],
            'mutate.0.attributes.rating' => ['The mutate.0.attributes.rating field must be a number.'],
            'mutate.0.attributes.published_on' => ['The mutate.0.attributes.published on field must be a valid date.'],
            'mutate.0.attributes.metadata' => ['The mutate.0.attributes.metadata field must be an array.'],
        ]);
        $this->assertSame(0, Post::query()->count());
    }

    #[Test]
    public function it_applies_the_base_rule_of_each_type_on_update(): void
    {
        $post = Post::factory()->create();

        $response = $this->mutate('/api/posts/mutate', [[
            'operation' => 'update',
            'key' => $post->id,
            'attributes' => ['title' => ['Annual report'], 'summary' => true, 'status' => 'archived', 'views' => 'many', 'published_at' => 'later', 'metadata' => 'none'],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title field must be a string.'],
            'mutate.0.attributes.summary' => ['The mutate.0.attributes.summary field must be a string.'],
            'mutate.0.attributes.status' => ['The selected mutate.0.attributes.status is invalid.'],
            'mutate.0.attributes.views' => ['The mutate.0.attributes.views field must be an integer.'],
            'mutate.0.attributes.published_at' => ['The mutate.0.attributes.published at field must be a valid date.'],
            'mutate.0.attributes.metadata' => ['The mutate.0.attributes.metadata field must be an array.'],
        ]);
        $this->assertSame(['Annual report', null], [$post->fresh()?->title, $post->fresh()?->summary]);
    }

    #[Test]
    public function it_leaves_the_field_rules_out_when_the_attributes_are_not_an_object(): void
    {
        $response = $this->mutate('/api/posts/mutate', [['operation' => 'create', 'attributes' => 'Annual report']]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes' => ['The mutate.0.attributes field must be an array.'],
        ]);
    }

    #[Test]
    public function it_applies_the_creation_rules_when_the_attributes_are_absent(): void
    {
        $response = $this->mutate('/api/posts/mutate', [['operation' => 'create']]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title field is required.'],
            'mutate.0.attributes.status' => ['The mutate.0.attributes.status field is required.'],
            'mutate.0.attributes.published_on' => ['The mutate.0.attributes.published on field is required.'],
        ]);
        $this->assertSame(0, Post::query()->count());
    }

    #[Test]
    public function it_refuses_null_on_a_field_that_is_not_nullable(): void
    {
        $response = $this->mutate('/api/posts/mutate', [[
            'operation' => 'create',
            'attributes' => [...self::POST, 'featured' => null, 'views' => null],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.featured' => ['The mutate.0.attributes.featured field must be true or false.'],
            'mutate.0.attributes.views' => ['The mutate.0.attributes.views field must be an integer.'],
        ]);
        $this->assertSame(0, Post::query()->count());
    }

    #[Test]
    public function it_accepts_null_on_a_nullable_field_and_an_object_on_a_json_field(): void
    {
        $response = $this->mutate('/api/posts/mutate', [[
            'operation' => 'create',
            'attributes' => [...self::POST, 'summary' => null, 'rating' => null, 'published_at' => null, 'metadata' => ['source' => 'web']],
        ]]);

        $post = Post::query()->sole();
        $response->assertOk()->assertExactJson(['created' => [$post->id], 'updated' => []]);
        $this->assertSame([null, null, null, ['source' => 'web']], [$post->summary, $post->rating, $post->published_at, $post->metadata]);
    }

    #[Test]
    public function it_applies_the_creation_rules_on_create_only(): void
    {
        $post = Post::factory()->create();

        $response = $this->mutate('/api/posts/mutate', [
            ['operation' => 'create', 'attributes' => ['summary' => 'Draft']],
            ['operation' => 'update', 'key' => $post->id, 'attributes' => ['summary' => 'Revised']],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title field is required.'],
            'mutate.0.attributes.status' => ['The mutate.0.attributes.status field is required.'],
            'mutate.0.attributes.published_on' => ['The mutate.0.attributes.published on field is required.'],
        ]);
    }

    #[Test]
    public function it_applies_the_update_rules_on_update_only(): void
    {
        $post = Post::factory()->create();

        $response = $this->mutate('/api/posts/mutate', [
            ['operation' => 'create', 'attributes' => [...self::POST, 'title' => null]],
            ['operation' => 'update', 'key' => $post->id, 'attributes' => ['title' => null]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title field must be a string.', 'The mutate.0.attributes.title field is required.'],
            'mutate.1.attributes.title' => ['The mutate.1.attributes.title field must be a string.', 'The mutate.1.attributes.title field must have a value.'],
        ]);
        $this->assertSame('Annual report', $post->fresh()?->title);
    }

    #[Test]
    public function it_applies_the_shared_rules_on_create_and_update(): void
    {
        $post = Post::factory()->create();

        $response = $this->mutate('/api/posts/mutate', [
            ['operation' => 'create', 'attributes' => [...self::POST, 'title' => str_repeat('a', 61), 'views' => -1]],
            ['operation' => 'update', 'key' => $post->id, 'attributes' => ['title' => str_repeat('a', 61), 'views' => -1]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.title' => ['The mutate.0.attributes.title field must not be greater than 60 characters.'],
            'mutate.0.attributes.views' => ['The mutate.0.attributes.views field must be at least 0.'],
            'mutate.1.attributes.title' => ['The mutate.1.attributes.title field must not be greater than 60 characters.'],
            'mutate.1.attributes.views' => ['The mutate.1.attributes.views field must be at least 0.'],
        ]);
    }

    #[Test]
    public function it_compares_a_field_rule_with_the_sibling_attribute_it_names(): void
    {
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        $ada = Author::factory()->create();

        $response = $this->postJson('/api/authors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['posts' => [
                ['operation' => 'create', 'attributes' => [...self::POST, 'published_on' => '2026-03-01', 'published_at' => '2026-03-02 09:00:00']],
                ['operation' => 'create', 'attributes' => [...self::POST, 'published_on' => '2026-03-01', 'published_at' => '2026-02-01 09:00:00']],
            ]],
        ]], 'published_on' => '2026-01-01']);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.posts.1.attributes.published_at' => ['The mutate.0.relations.posts.1.attributes.published at field must be a date after or equal to mutate.0.relations.posts.1.attributes.published on.'],
        ]);
        $this->assertSame(0, Post::query()->count());
    }

    /** @param list<array<string, mixed>> $mutations */
    private function mutate(string $uri, array $mutations): TestResponse
    {
        return $this->postJson($uri, ['mutate' => $mutations]);
    }
}
