<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\ConditionalPostController;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\ContextualAuthorController;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\ContextualPostController;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\NestedConditionPostController;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\RecordsMutation;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MutationContextTest extends PackageTestCase
{
    use AssertsExactErrors;

    private const array DRAFT = ['status' => 'draft', 'published_on' => '2026-01-01'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RecordsMutation::$seen = [];
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        Rest::resource('contextual-posts', ContextualPostController::class)->register();
        Rest::resource('conditional-posts', ConditionalPostController::class)->register();
        Rest::resource('contextual-authors', ContextualAuthorController::class)->register();
        Rest::resource('nested-condition-posts', NestedConditionPostController::class)->register();
    }

    #[Test]
    public function it_ignores_the_record_that_an_entry_updates(): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Annual report']);
        Post::factory()->create(['id' => 2, 'title' => 'Quarterly report']);

        $response = $this->postJson('/contextual-posts/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Annual report']],
            ['operation' => 'update', 'key' => 2, 'attributes' => ['title' => 'Annual report']],
        ]]);

        $this->assertExactErrors($response, ['mutate.1.attributes.title' => ['The mutate.1.attributes.title has already been taken.']]);
        $titles = Post::query()
            ->orderBy('id')
            ->pluck('title')
            ->all();
        $this->assertSame(['Annual report', 'Quarterly report'], $titles);
    }

    #[Test]
    public function it_refuses_a_value_taken_by_another_record_on_create(): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Annual report']);

        $response = $this->postJson('/contextual-posts/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => [...self::DRAFT, 'title' => 'Annual report']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.title' => ['The mutate.0.attributes.title has already been taken.']]);
        $this->assertSame(1, Post::query()->count());
    }

    #[Test]
    public function it_resolves_a_conditional_rule_against_the_attributes_of_its_entry(): void
    {
        $response = $this->postJson('/conditional-posts/mutate', ['featured' => true, 'mutate' => [
            ['operation' => 'create', 'attributes' => [...self::DRAFT, 'title' => 'Annual report', 'featured' => true]],
            ['operation' => 'create', 'attributes' => [...self::DRAFT, 'title' => 'Quarterly report', 'featured' => false]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.summary' => ['The mutate.0.attributes.summary field is required.']]);
        $this->assertSame(0, Post::query()->count());
    }

    #[Test]
    public function it_resolves_the_rules_that_a_contextual_or_conditional_rule_returns(): void
    {
        $response = $this->postJson('/nested-condition-posts/mutate', ['featured' => true, 'mutate' => [
            ['operation' => 'create', 'attributes' => [...self::DRAFT, 'title' => 'Annual report', 'featured' => true, 'rating' => 5]],
            ['operation' => 'create', 'attributes' => [...self::DRAFT, 'title' => 'Quarterly report', 'featured' => false, 'rating' => 5]],
        ]]);

        $this->assertExactErrors($response, [
            'mutate.0.attributes.summary' => ['The mutate.0.attributes.summary field is required.'],
            'mutate.0.attributes.rating' => ['The mutate.0.attributes.rating field must not be greater than 0.'],
        ]);
        $this->assertSame(0, Post::query()->count());
    }

    #[Test]
    public function it_gives_each_entry_its_own_mutation_context(): void
    {
        Author::factory()->create(['id' => 1]);
        $quarterly = Post::factory()->create(['author_id' => 1, 'title' => 'Quarterly report']);

        $response = $this->postJson('/contextual-authors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['posts' => [
                ['operation' => 'create', 'attributes' => [...self::DRAFT, 'title' => 'Annual report', 'views' => 3]],
                ['operation' => 'update', 'key' => $quarterly->id, 'attributes' => ['views' => 4]],
            ]],
        ]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [1]]);
        $this->assertSame([['create', null, 1], ['update', $quarterly->id, 1]], RecordsMutation::$seen);
        $this->assertSame(4, $quarterly->fresh()?->views);
    }
}
