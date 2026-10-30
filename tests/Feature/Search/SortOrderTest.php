<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SortOrderTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  list<array<string, string>>  $sorts
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('successiveSorts')]
    public function it_orders_by_each_sort_in_turn(array $sorts, array $titles): void
    {
        $this->seedPosts();

        $response = $this->searchPosts($sorts);

        $response->assertOk()->assertExactJson($this->page(array_map(fn (string $title): array => ['title' => $title], $titles)));
    }

    /** @return array<string, array{list<array<string, string>>, list<string>}> */
    public static function successiveSorts(): array
    {
        return [
            'ties broken in ascending order' => [
                [['field' => 'views', 'direction' => 'desc'], ['field' => 'title', 'direction' => 'asc']],
                ['Spring launch', 'Quarterly report', 'Annual report', 'Team offsite'],
            ],
            'ties broken in descending order' => [
                [['field' => 'views', 'direction' => 'desc'], ['field' => 'title', 'direction' => 'desc']],
                ['Spring launch', 'Quarterly report', 'Team offsite', 'Annual report'],
            ],
            'no ties to break' => [
                [['field' => 'title', 'direction' => 'desc'], ['field' => 'views', 'direction' => 'asc']],
                ['Team offsite', 'Spring launch', 'Quarterly report', 'Annual report'],
            ],
        ];
    }

    #[Test]
    public function it_sorts_in_ascending_order_without_a_direction(): void
    {
        $this->seedPosts();

        $response = $this->searchPosts([['field' => 'title']]);

        $response->assertOk()->assertExactJson($this->page([
            ['title' => 'Annual report'],
            ['title' => 'Quarterly report'],
            ['title' => 'Spring launch'],
            ['title' => 'Team offsite'],
        ]));
    }

    #[Test]
    #[DataProvider('unknownDirections')]
    public function it_rejects_an_unknown_direction(string $direction): void
    {
        $response = $this->searchPosts([['field' => 'title', 'direction' => $direction]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.sorts.0.direction is invalid.',
            'errors' => [
                'search.sorts.0.direction' => ['The selected search.sorts.0.direction is invalid.'],
            ],
        ]);
    }

    /** @return array<string, array{string}> */
    public static function unknownDirections(): array
    {
        return [
            'a word' => ['up'],
            'a long form' => ['ascending'],
            'upper case' => ['DESC'],
        ];
    }

    #[Test]
    public function it_orders_by_the_default_order_without_sorts(): void
    {
        $this->seedPosts();

        $response = $this->postJson('/api/posts/search', ['search' => ['selects' => [['field' => 'title']]]]);

        $response->assertOk()->assertExactJson($this->page([
            ['title' => 'Quarterly report'],
            ['title' => 'Annual report'],
            ['title' => 'Spring launch'],
            ['title' => 'Team offsite'],
        ]));
    }

    #[Test]
    public function it_applies_the_sorts_of_an_include(): void
    {
        $ada = Author::factory()->create();
        Post::factory()->for($ada)->create(['title' => 'Team offsite']);
        Post::factory()->for($ada)->create(['title' => 'Annual report']);
        Post::factory()->for($ada)->create(['title' => 'Spring launch']);

        $response = $this->postJson('/api/authors/search', [
            'search' => [
                'selects' => [['field' => 'last_name']],
                'includes' => [['relation' => 'posts', 'sorts' => [['field' => 'title']], 'selects' => [['field' => 'title']]]],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['last_name' => 'Lovelace', 'posts' => [['title' => 'Annual report'], ['title' => 'Spring launch'], ['title' => 'Team offsite']]],
        ]));
    }

    private function seedPosts(): void
    {
        Post::factory()->create(['title' => 'Team offsite', 'views' => 30]);
        Post::factory()->create(['title' => 'Spring launch', 'views' => 120]);
        Post::factory()->create(['title' => 'Annual report', 'views' => 30]);
        Post::factory()->create(['title' => 'Quarterly report', 'views' => 75]);
    }

    /** @param list<array<string, string>> $sorts */
    private function searchPosts(array $sorts): TestResponse
    {
        return $this->postJson('/api/posts/search', [
            'search' => ['sorts' => $sorts, 'selects' => [['field' => 'title']]],
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
