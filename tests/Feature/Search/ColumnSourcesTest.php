<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\MeasuredPostController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\SignedAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ColumnSourcesTest extends PackageTestCase
{
    private const string SIGNED = 'select authors.first_name, authors.last_name, authors.id, authors.api_token from authors where authors.deleted_at is null order by authors.id desc limit 50 offset 0';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('signed-authors', SignedAuthorController::class)->register();
        Rest::resource('measured-posts', MeasuredPostController::class)->register();
        Author::query()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada', 'settings' => ['theme' => 'dark']]);
        Author::query()->create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-grace']);
        Post::query()->create(['id' => 1, 'author_id' => 1, 'title' => 'Annual report', 'status' => 'published', 'published_on' => '2026-01-05']);
        Post::query()->create(['id' => 2, 'author_id' => 2, 'title' => 'Notes', 'status' => 'draft', 'published_on' => '2026-01-06']);
        DB::enableQueryLog();
    }

    #[Test]
    public function it_loads_the_columns_that_a_returned_field_requires_and_that_the_resource_always_selects(): void
    {
        $response = $this->postJson('/signed-authors/search', ['search' => ['selects' => [['field' => 'signature']]]]);

        $response->assertOk()->assertJsonPath('data', [['signature' => 'Grace Hopper', 'token_hint' => 'grace'], ['signature' => 'Ada Lovelace', 'token_hint' => 'ada']]);
        $this->assertSame(self::SIGNED, $this->loggedQuery(0));
    }

    #[Test]
    public function it_leaves_out_the_columns_that_a_field_requires_when_the_field_is_not_returned(): void
    {
        $response = $this->postJson('/signed-authors/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 2, 'token_hint' => 'grace'], ['id' => 1, 'token_hint' => 'ada']]);
        $this->assertSame('select authors.id, authors.api_token from authors where authors.deleted_at is null order by authors.id desc limit 50 offset 0', $this->loggedQuery(0));
    }

    #[Test]
    public function it_loads_every_column_when_the_column_selection_is_off(): void
    {
        config(['rest.search.select_columns' => false]);

        $response = $this->postJson('/signed-authors/search', ['search' => ['selects' => [['field' => 'signature']]]]);

        $response->assertOk()->assertJsonPath('data', [['signature' => 'Grace Hopper', 'token_hint' => 'grace'], ['signature' => 'Ada Lovelace', 'token_hint' => 'ada']]);
        $this->assertSame('select * from authors where authors.deleted_at is null order by authors.id desc limit 50 offset 0', $this->loggedQuery(0));
    }

    #[Test]
    public function it_selects_an_aggregate_next_to_the_selected_columns_only(): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => ['selects' => [['field' => 'id']], 'aggregates' => [['relation' => 'posts', 'type' => 'count']]]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 2, 'posts_count' => 1], ['id' => 1, 'posts_count' => 1]]);
        $this->assertSame('select authors.id, (select count(*) from posts where authors.id = posts.author_id) as @aggregate_0 from authors where authors.deleted_at is null order by authors.id desc limit 50 offset 0', $this->loggedQuery(0));
    }

    #[Test]
    public function it_sorts_on_a_field_of_a_relation_without_selecting_its_key(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => ['selects' => [['field' => 'id']], 'sorts' => [['field' => 'author.last_name']]]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 2], ['id' => 1]]);
        $this->assertSame('select posts.id from posts order by (select max(authors.last_name) from authors where posts.author_id = authors.id and authors.deleted_at is null) asc limit 50 offset 0', $this->loggedQuery(0));
    }

    #[Test]
    public function it_adds_the_selected_columns_to_the_columns_that_an_instruction_selects(): void
    {
        $response = $this->postJson('/measured-posts/search', ['search' => ['selects' => [['field' => 'title_length']], 'instructions' => [['name' => 'title-lengths']]]]);

        $response->assertOk()->assertJsonPath('data', [['title_length' => 5], ['title_length' => 13]]);
        $this->assertSame('select length(posts.title) as title_length, posts.id from posts order by posts.id desc limit 50 offset 0', $this->loggedQuery(0));
    }

    #[Test]
    public function it_loads_every_column_of_the_rows_of_an_action(): void
    {
        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => ['filters' => [['field' => 'id', 'value' => 2]]]]);

        $response->assertOk()->assertJsonPath('data.impacted', 1);
        $this->assertSame(['select count(*) as aggregate from posts where (posts.id = ?)', 'select * from posts where (posts.id = ?) and posts.id is not null order by posts.id asc limit 100'], [$this->loggedQuery(0), $this->loggedQuery(1)]);
    }

    private function loggedQuery(int $index): string
    {
        return str_replace(['"', '`'], '', DB::getQueryLog()[$index]['query']);
    }
}
