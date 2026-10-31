<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Stubs\AllFieldsFullTextPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsAuthorController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsCategoryController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsProductController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\FullTextPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\FullTextPostsAuthorController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\MisindexedPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\PerimeterPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\SimpleFullTextPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\StrictFullTextPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\UnindexedFullTextPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\UnsearchablePostController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class TextSearchTestCase extends PackageTestCase
{
    private const array RESOURCES = [
        'contains-posts' => ContainsPostController::class,
        'full-text-posts' => FullTextPostController::class,
        'all-fields-full-text-posts' => AllFieldsFullTextPostController::class,
        'simple-full-text-posts' => SimpleFullTextPostController::class,
        'strict-full-text-posts' => StrictFullTextPostController::class,
        'unindexed-full-text-posts' => UnindexedFullTextPostController::class,
        'misindexed-posts' => MisindexedPostController::class,
        'unsearchable-posts' => UnsearchablePostController::class,
        'perimeter-posts' => PerimeterPostController::class,
        'contains-authors' => ContainsAuthorController::class,
        'contains-products' => ContainsProductController::class,
        'contains-categories' => ContainsCategoryController::class,
        'full-text-posts-authors' => FullTextPostsAuthorController::class,
    ];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::RESOURCES as $name => $controller) {
            Rest::resource($name, $controller)->register();
        }
    }

    /** @param array<string, mixed> $search */
    protected function search(string $resource, array $search): TestResponse
    {
        return $this->postJson("/{$resource}/search", ['search' => $search]);
    }

    /** @param array<string, mixed> $attributes */
    protected function createPost(string $title, ?string $summary = null, array $attributes = []): Post
    {
        return Post::factory()->create(['title' => $title, 'summary' => $summary, ...$attributes]);
    }

    protected function runsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
}
