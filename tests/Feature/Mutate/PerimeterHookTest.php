<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ListedPostAuthorController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ListedPostAuthorResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ListedPostController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ListedPostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PerimeterHookTest extends PackageTestCase
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

        ListedPostResource::$hooked = [];
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        Rest::resource('listed-posts', ListedPostController::class)->register();
        Rest::resource('listed-post-authors', ListedPostAuthorController::class)->register();
    }

    #[Test]
    public function it_runs_the_perimeter_once_per_resource_class_while_checking_the_keys(): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Quarterly report', 'status' => PostStatus::Draft]);
        Post::factory()->create(['id' => 2, 'title' => 'Yearly report', 'status' => PostStatus::Draft]);

        $response = $this->postJson('/listed-posts/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['views' => 3]],
            ['operation' => 'update', 'key' => 2, 'attributes' => ['views' => 4]],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => 1], ['operation' => 'update', 'key' => 2]]]);
        $this->assertSame([ListedPostResource::class.' mutate'], ListedPostResource::$hooked);
    }

    #[Test]
    public function it_runs_the_perimeter_of_a_related_resource_once_while_checking_its_keys(): void
    {
        Author::factory()->create(['id' => 1]);
        Post::factory()->create(['id' => 1, 'author_id' => 1, 'title' => 'Quarterly report', 'status' => PostStatus::Draft]);
        Post::factory()->create(['id' => 2, 'author_id' => 1, 'title' => 'Yearly report', 'status' => PostStatus::Draft]);

        $response = $this->postJson('/listed-post-authors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['posts' => [
                ['operation' => 'update', 'key' => 1, 'attributes' => ['views' => 3]],
                ['operation' => 'update', 'key' => 2, 'attributes' => ['views' => 4]],
            ]],
        ]]]);

        $response->assertOk();
        $this->assertSame([ListedPostAuthorResource::class.' mutate'], ListedPostResource::$hooked);
    }

    #[Test]
    public function it_runs_no_perimeter_for_a_mutation_that_names_no_key(): void
    {
        $response = $this->postJson('/listed-posts/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Quarterly report', 'status' => 'draft', 'published_on' => '2026-01-01']],
        ]]);

        $response->assertOk();
        $this->assertSame([], ListedPostResource::$hooked);
    }
}
