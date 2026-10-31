<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ListedAuthorPostController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PostingAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationFiltersTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('postFilters')]
    public function it_filters_posts_through_their_author(array $filters, array $titles): void
    {
        $this->seedPosts();

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => $filters, 'sorts' => [['field' => 'title']]]]);

        $response->assertOk()->assertJsonPath('data.*.title', $titles);
    }

    /** @return array<string, array{list<array<string, mixed>>, list<string>}> */
    public static function postFilters(): array
    {
        return [
            'a field of the author' => [[['field' => 'author.first_name', 'value' => 'Grace']], ['Compilers', 'Debugging']],
            'an operator added to the field types' => [[['field' => 'author.last_name', 'operator' => 'ilike', 'value' => 'LOVE%']], ['Engines']],
            'a list of values' => [[['field' => 'author.first_name', 'operator' => 'not in', 'value' => ['Grace']]], ['Engines', 'Machines']],
            'a custom filter of the author' => [[['field' => 'author.full_name', 'operator' => 'like', 'value' => 'Hop%']], ['Compilers', 'Debugging']],
            'a nested relation' => [[['field' => 'author.tags.name', 'value' => 'Mathematics']], ['Engines', 'Machines']],
            'a nested relation after an or' => [[
                ['field' => 'title', 'value' => 'Compilers'],
                ['field' => 'author.tags.name', 'value' => 'Mathematics', 'type' => 'or'],
            ], ['Compilers', 'Engines', 'Machines']],
            'a field of the post or of the author' => [[
                ['field' => 'views', 'value' => 40],
                ['field' => 'author.first_name', 'value' => 'Alan', 'type' => 'or'],
            ], ['Debugging', 'Machines']],
            'a group that keeps its or inside' => [[
                ['field' => 'views', 'operator' => '>=', 'value' => 20],
                ['nested' => [
                    ['field' => 'author.first_name', 'value' => 'Ada'],
                    ['field' => 'author.full_name', 'value' => 'Hopper', 'type' => 'or'],
                ]],
            ], ['Debugging', 'Engines']],
            'a group after an or' => [[
                ['field' => 'title', 'value' => 'Machines'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'author.first_name', 'value' => 'Grace'],
                    ['field' => 'views', 'operator' => '<', 'value' => 20],
                ]],
            ], ['Compilers', 'Machines']],
        ];
    }

    #[Test]
    public function it_filters_authors_through_their_posts(): void
    {
        $this->seedPosts();

        $response = $this->postJson('/api/authors/search', ['search' => [
            'filters' => [['field' => 'posts.views', 'operator' => 'between', 'value' => [25, 35]]],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.first_name', ['Ada']);
    }

    #[Test]
    public function it_applies_the_perimeter_of_the_related_resource_as_one_group(): void
    {
        Rest::resource('listed-author-posts', ListedAuthorPostController::class)->register();
        $ada = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $alan = Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Hidden']);
        $grace = Author::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hidden']);
        $engines = Post::factory()->create(['author_id' => $ada->id, 'title' => 'Engines']);
        Post::factory()->create(['author_id' => $alan->id, 'title' => 'Machines']);
        Post::factory()->create(['author_id' => $grace->id, 'title' => 'Compilers']);

        $listed = $this->postJson('/listed-author-posts/search', ['search' => ['filters' => [['field' => 'author.first_name', 'value' => 'Ada']]]]);
        $unlisted = $this->postJson('/listed-author-posts/search', ['search' => ['filters' => [['field' => 'author.first_name', 'value' => 'Alan']]]]);

        $listed->assertOk()->assertJsonPath('data.*.id', [$engines->id]);
        $unlisted->assertOk()->assertJsonPath('data', []);
    }

    #[Test]
    public function it_applies_the_perimeter_of_the_last_resource_of_a_nested_path(): void
    {
        Rest::resource('posting-authors', PostingAuthorController::class)->register();
        $ada = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $alan = Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Hidden']);
        Post::factory()->create(['author_id' => $ada->id, 'title' => 'Engines']);
        Post::factory()->create(['author_id' => $alan->id, 'title' => 'Machines']);

        $listed = $this->postJson('/posting-authors/search', ['search' => ['filters' => [['field' => 'posts.author.first_name', 'value' => 'Ada']]]]);
        $unlisted = $this->postJson('/posting-authors/search', ['search' => ['filters' => [['field' => 'posts.author.first_name', 'value' => 'Alan']]]]);

        $listed->assertOk()->assertJsonPath('data.*.id', [$ada->id]);
        $unlisted->assertOk()->assertJsonPath('data', []);
    }

    /** @return array<string, array{class-string<Model>, string, string}> */
    public static function unlistedRelations(): array
    {
        return [
            'a relation' => [Author::class, '/api/posts', 'author.first_name'],
            'a nested relation' => [Tag::class, '/api/posts', 'author.tags.name'],
        ];
    }

    /** @param class-string<Model> $model */
    #[Test]
    #[DataProvider('unlistedRelations')]
    public function it_refuses_a_filter_through_a_relation_the_user_cannot_list(string $model, string $endpoint, string $field): void
    {
        Gate::policy($model, UnlistedPolicy::class);
        DB::enableQueryLog();

        $response = $this->postJson($endpoint.'/search', ['search' => ['filters' => [['field' => $field, 'value' => 'Ada']]]]);

        $response->assertForbidden()->assertJsonPath('message', 'This list is closed.');
        $this->assertSame([], DB::getQueryLog());
    }

    private function seedPosts(): void
    {
        $ada = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $alan = Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);
        $grace = Author::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
        $mathematics = Tag::factory()->create(['name' => 'Mathematics']);
        $ada->tags()->attach($mathematics);
        $alan->tags()->attach($mathematics);

        Post::factory()->create(['author_id' => $ada->id, 'title' => 'Engines', 'views' => 30]);
        Post::factory()->create(['author_id' => $alan->id, 'title' => 'Machines', 'views' => 10]);
        Post::factory()->create(['author_id' => $grace->id, 'title' => 'Compilers', 'views' => 15]);
        Post::factory()->create(['author_id' => $grace->id, 'title' => 'Debugging', 'views' => 40]);
    }
}
