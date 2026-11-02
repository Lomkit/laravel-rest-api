<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\VisiblePostAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationGroupFiltersTest extends PackageTestCase
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

        $ada = Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $grace = Author::factory()->create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper']);
        $alan = Author::factory()->create(['id' => 3, 'first_name' => 'Alan', 'last_name' => 'Turing']);
        Post::factory()->create(['id' => 1, 'author_id' => 1, 'title' => 'Engines', 'status' => PostStatus::Published, 'featured' => false, 'views' => 150]);
        Post::factory()->create(['id' => 2, 'author_id' => 1, 'title' => 'Notes', 'status' => PostStatus::Draft, 'featured' => true, 'views' => 5]);
        Post::factory()->create(['id' => 3, 'author_id' => 2, 'title' => 'Compilers', 'status' => PostStatus::Published, 'featured' => true, 'views' => 20]);
        Tag::factory()->create(['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics']);
        Tag::factory()->create(['id' => 2, 'name' => 'Navy', 'slug' => 'navy']);
        $ada->tags()->attach(1);
        $alan->tags()->attach(1);
        $grace->tags()->attach(2);
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<string>  $names
     */
    #[Test]
    #[DataProvider('authorFilters')]
    public function it_filters_authors_through_a_relation_group(array $filters, array $names): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => $filters, 'sorts' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertJsonPath('data.*.first_name', $names);
    }

    /** @return array<string, array{list<array<string, mixed>>, list<string>}> */
    public static function authorFilters(): array
    {
        $publishedAndFeatured = [['field' => 'status', 'value' => 'published'], ['field' => 'featured', 'value' => true]];
        $draft = [['field' => 'status', 'value' => 'draft']];
        $mathematics = [['field' => 'name', 'value' => 'Mathematics']];

        return [
            'two dotted filters on one relation' => [[['field' => 'posts.status', 'value' => 'published'], ['field' => 'posts.featured', 'value' => true]], ['Ada', 'Grace']],
            'a dotted filter that is not equal' => [[['field' => 'posts.status', 'operator' => '!=', 'value' => 'draft']], ['Ada', 'Grace']],
            'a group that matches one post' => [[['relation' => 'posts', 'exists' => true, 'nested' => $publishedAndFeatured]], ['Grace']],
            'exists by default' => [[['relation' => 'posts', 'nested' => $publishedAndFeatured]], ['Grace']],
            'no matching post' => [[['relation' => 'posts', 'exists' => false, 'nested' => $draft]], ['Alan', 'Grace']],
            'any post' => [[['relation' => 'posts']], ['Ada', 'Grace']],
            'no post' => [[['relation' => 'posts', 'exists' => false]], ['Alan']],
            'no post with an empty list' => [[['relation' => 'posts', 'exists' => false, 'nested' => []]], ['Alan']],
            'a group after an or' => [[
                ['field' => 'first_name', 'value' => 'Alan'],
                ['type' => 'or', 'relation' => 'posts', 'nested' => $publishedAndFeatured],
            ], ['Alan', 'Grace']],
            'a group after an and' => [[
                ['field' => 'first_name', 'operator' => 'in', 'value' => ['Ada', 'Alan']],
                ['relation' => 'posts', 'exists' => false, 'nested' => $draft],
            ], ['Alan']],
            'an or that stays in the group' => [[
                ['field' => 'first_name', 'operator' => 'in', 'value' => ['Ada', 'Grace']],
                ['relation' => 'posts', 'nested' => [...$draft, ['field' => 'views', 'operator' => '>', 'value' => 100, 'type' => 'or']]],
            ], ['Ada']],
            'a many-to-many relation' => [[['relation' => 'tags', 'nested' => $mathematics]], ['Ada', 'Alan']],
            'no tag of a name' => [[['relation' => 'tags', 'exists' => false, 'nested' => $mathematics]], ['Grace']],
            'a group in a group' => [[['nested' => [
                ['relation' => 'posts', 'exists' => false],
                ['field' => 'first_name', 'value' => 'Ada', 'type' => 'or'],
            ]]], ['Ada', 'Alan']],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<string>  $names
     */
    #[Test]
    #[DataProvider('visiblePostFilters')]
    public function it_applies_the_search_query_of_the_related_resource(array $filters, array $names): void
    {
        Rest::resource('visible-post-authors', VisiblePostAuthorController::class)->register();

        $response = $this->postJson('/visible-post-authors/search', ['search' => ['filters' => $filters, 'sorts' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertJsonPath('data.*.first_name', $names);
    }

    /** @return array<string, array{list<array<string, mixed>>, list<string>}> */
    public static function visiblePostFilters(): array
    {
        $featured = [['field' => 'featured', 'value' => true]];

        return [
            'a visible featured post' => [[['relation' => 'posts', 'nested' => $featured]], ['Grace']],
            'no visible featured post' => [[['relation' => 'posts', 'exists' => false, 'nested' => $featured]], ['Ada', 'Alan']],
        ];
    }

    #[Test]
    public function it_nests_relation_groups_up_to_the_configured_depth(): void
    {
        config(['rest.limits.depths.filters' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [['relation' => 'author', 'nested' => [['relation' => 'tags', 'nested' => [['field' => 'name', 'value' => 'Mathematics']]]]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1, 2]);
    }

    #[Test]
    public function it_applies_a_relation_group_in_the_search_of_an_action(): void
    {
        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => ['filters' => [
            ['relation' => 'author', 'nested' => [['field' => 'first_name', 'value' => 'Ada']]],
            ['field' => 'status', 'value' => 'draft'],
        ]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
        $this->assertSame(PostStatus::Published, Post::query()
            ->findOrFail(2)
            ->status);
    }
}
