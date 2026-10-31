<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ScopesTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_binds_the_parameters_by_position_and_casts_them_to_the_field_type(): void
    {
        Post::factory()->create(['title' => 'Winter recap', 'published_on' => '2025-12-31']);
        $newYear = Post::factory()->create(['title' => 'New year', 'published_on' => '2026-01-01']);
        $february = Post::factory()->create(['title' => 'February notes', 'published_on' => '2026-02-01']);

        $response = $this->searchPosts([['name' => 'publishedSince', 'parameters' => ['2026-01-01 15:30:00']]]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $february->id, 'title' => 'February notes'],
            ['id' => $newYear->id, 'title' => 'New year'],
        ]));
    }

    #[Test]
    public function it_fills_a_missing_position_with_the_field_default(): void
    {
        Post::factory()->create(['title' => 'Quiet post', 'views' => 99]);
        $threshold = Post::factory()->create(['title' => 'Threshold post', 'views' => 100]);
        $popular = Post::factory()->create(['title' => 'Popular post', 'views' => 250]);

        $response = $this->searchPosts([['name' => 'popular']]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $popular->id, 'title' => 'Popular post'],
            ['id' => $threshold->id, 'title' => 'Threshold post'],
        ]));
    }

    #[Test]
    public function it_prefers_a_given_parameter_over_the_field_default(): void
    {
        Post::factory()->create(['title' => 'Threshold post', 'views' => 100]);
        $popular = Post::factory()->create(['title' => 'Popular post', 'views' => 250]);

        $response = $this->searchPosts([['name' => 'popular', 'parameters' => ['150']]]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $popular->id, 'title' => 'Popular post'],
        ]));
    }

    #[Test]
    public function it_applies_every_requested_scope(): void
    {
        Post::factory()->create(['title' => 'Old and popular', 'published_on' => '2025-06-01', 'views' => 300]);
        Post::factory()->create(['title' => 'Recent and quiet', 'published_on' => '2026-03-01', 'views' => 10]);
        $match = Post::factory()->create(['title' => 'Recent and popular', 'published_on' => '2026-03-01', 'views' => 300]);

        $response = $this->searchPosts([
            ['name' => 'publishedSince', 'parameters' => ['2026-01-01']],
            ['name' => 'popular'],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $match->id, 'title' => 'Recent and popular'],
        ]));
    }

    #[Test]
    public function it_passes_an_explicit_null_to_a_nullable_position(): void
    {
        Post::factory()->create(['title' => 'Quiet post', 'views' => 50]);
        $read = Post::factory()->create(['title' => 'Read post', 'views' => 150]);
        $viral = Post::factory()->create(['title' => 'Viral post', 'views' => 5000]);

        $response = $this->searchPosts([['name' => 'viewedBetween', 'parameters' => [100, null]]]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $viral->id, 'title' => 'Viral post'],
            ['id' => $read->id, 'title' => 'Read post'],
        ]));
    }

    #[Test]
    public function it_fills_an_omitted_nullable_position_with_its_default(): void
    {
        $featured = Post::factory()->create(['title' => 'Featured post', 'featured' => true]);
        Post::factory()->create(['title' => 'Plain post', 'featured' => false]);

        $response = $this->searchPosts([['name' => 'featured']]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $featured->id, 'title' => 'Featured post'],
        ]));
    }

    #[Test]
    public function it_keeps_an_explicit_null_over_the_default_of_a_nullable_position(): void
    {
        $featured = Post::factory()->create(['title' => 'Featured post', 'featured' => true]);
        $plain = Post::factory()->create(['title' => 'Plain post', 'featured' => false]);

        $response = $this->searchPosts([['name' => 'featured', 'parameters' => [null]]]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $plain->id, 'title' => 'Plain post'],
            ['id' => $featured->id, 'title' => 'Featured post'],
        ]));
    }

    /** @param list<string> $titles */
    #[Test]
    #[DataProvider('coercedIntBackedEnumParameters')]
    public function it_casts_a_parameter_the_enum_rule_accepts_to_an_int_backed_enum_case(string $parameters, array $titles): void
    {
        Post::factory()->create(['title' => 'Unread post', 'views' => 5]);
        Post::factory()->create(['title' => 'Local post', 'views' => 50]);
        Post::factory()->create(['title' => 'National post', 'views' => 500]);

        $response = $this->call('POST', '/api/posts/search', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: '{"search":{"scopes":[{"name":"reaching","parameters":'.$parameters.'}],"selects":[{"field":"title"}]}}');

        $response->assertOk()->assertJsonPath('data.*.title', $titles);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function coercedIntBackedEnumParameters(): array
    {
        return [
            'true' => ['[true]', ['National post', 'Local post']],
            'a whole float' => ['[2.0]', ['National post']],
            'a numeric string' => ['["3"]', []],
        ];
    }

    /** @param list<array<string, mixed>> $scopes */
    private function searchPosts(array $scopes): TestResponse
    {
        return $this->postJson('/api/posts/search', [
            'search' => [
                'scopes' => $scopes,
                'selects' => [['field' => 'id'], ['field' => 'title']],
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    private function page(array $data): array
    {
        return [
            'current_page' => 1,
            'data' => $data,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => count($data),
            'total' => count($data),
            'meta' => [],
        ];
    }
}
