<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Stubs\MatchedPostsAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PHPUnit\Framework\Attributes\Test;

class FullTextIncludeTest extends FullTextTestCase
{
    #[Test]
    public function it_matches_the_text_of_an_include_within_the_limit_of_each_parent(): void
    {
        $this->requiresFullText();
        Rest::resource('matched-posts-authors', MatchedPostsAuthorController::class)->register();
        Author::query()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada']);
        Author::query()->create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-grace']);

        foreach (range(1, 24) as $id) {
            $title = match (true) {
                $id <= 12 => 'Annual report '.$id,
                $id === 13 => 'Quarterly report',
                default => 'Garden notes '.$id,
            };
            Post::query()->create(['id' => $id, 'author_id' => $id <= 12 ? 1 : 2, 'title' => $title, 'status' => 'published', 'published_on' => '2026-01-05']);
        }

        DB::enableQueryLog();

        $response = $this->postJson('/matched-posts-authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [[
                'relation' => 'posts',
                'selects' => [['field' => 'id']],
                'instructions' => [['name' => 'matching-title', 'fields' => [['name' => 'words', 'value' => 'report']]]],
            ]],
        ]]);

        $response->assertOk();
        $this->assertSame([[13], range(12, 3)], array_map(fn (array $author): array => array_column($author['posts'], 'id'), $response->json('data')));
        $this->assertStringContainsString('row_number() over (partition by', DB::getQueryLog()[2]['query']);
    }
}
