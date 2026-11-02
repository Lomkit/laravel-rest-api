<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DraftOnlyAuthorController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DraftOnlyAuthorResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DraftOnlyPostController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DraftOnlyPostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MutateQueryHookTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DraftOnlyPostResource::$hooked = [];
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        Rest::resource('draft-posts', DraftOnlyPostController::class)->register();
        Rest::resource('draft-only-authors', DraftOnlyAuthorController::class)->register();
    }

    #[Test]
    public function it_runs_the_mutate_query_once_per_resource_class_while_checking_the_keys(): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Annual report', 'status' => PostStatus::Draft]);
        Post::factory()->create(['id' => 2, 'title' => 'Quarterly report', 'status' => PostStatus::Draft]);

        $response = $this->postJson('/draft-posts/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['views' => 3]],
            ['operation' => 'update', 'key' => 2, 'attributes' => ['views' => 4]],
        ]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [1, 2]]);
        $this->assertSame([DraftOnlyPostResource::class, DraftOnlyPostResource::class], DraftOnlyPostResource::$hooked);
    }

    #[Test]
    public function it_runs_the_mutate_query_of_a_related_resource_while_checking_its_keys(): void
    {
        Author::factory()->create(['id' => 1]);
        Post::factory()->create(['id' => 1, 'author_id' => 1, 'title' => 'Annual report', 'status' => PostStatus::Draft]);

        $response = $this->postJson('/draft-only-authors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['posts' => [['operation' => 'update', 'key' => 1, 'attributes' => ['views' => 3]]]],
        ]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [1]]);
        $this->assertSame(3, Post::query()->findOrFail(1)->views);
        $this->assertSame([DraftOnlyAuthorResource::class], DraftOnlyPostResource::$hooked);
    }
}
