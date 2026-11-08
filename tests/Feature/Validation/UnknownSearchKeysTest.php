<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnknownSearchKeysTest extends PackageTestCase
{
    use CapturesQueries;

    private const string POSTS = '/api/posts/search';

    private const string CATEGORIES = '/api/categories/search';

    private const array POPULAR = ['field' => 'views', 'operator' => '>', 'value' => 5];

    private const array BY_ID = [['field' => 'id']];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $ada = Author::factory()->create(['id' => 1]);
        $grace = Author::factory()->create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper']);
        Post::factory()->create(['id' => 1, 'author_id' => $ada->getKey(), 'title' => 'Annual report', 'views' => 10, 'featured' => true]);
        Post::factory()->create(['id' => 2, 'author_id' => $grace->getKey(), 'title' => 'Draft notes', 'views' => 3]);
        Post::factory()->create(['id' => 3, 'author_id' => $grace->getKey(), 'title' => 'Release plan', 'views' => 40]);
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp']);
    }

    /**
     * @param  array<string, mixed>  $sent
     * @param  array<string, mixed>  $read
     */
    #[Test]
    #[DataProvider('unknownKeys')]
    public function it_answers_as_if_an_unknown_key_were_absent(string $uri, array $sent, array $read): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->captureQueries();

        $expected = $this->postJson($uri, ['search' => $read])->assertOk();
        $calls = $received->calls();
        $statements = $this->statements;
        $this->statements = [];
        $response = $this->postJson($uri, ['search' => $sent]);

        $response->assertOk()->assertExactJson($expected->json());
        $this->assertSame($statements, $this->statements);
        $this->assertSame([...$calls, ...$calls], $received->calls());
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, mixed>}> */
    public static function unknownKeys(): array
    {
        $popular = ['filters' => [self::POPULAR], 'sorts' => self::BY_ID];

        return [
            'in the search' => [self::POSTS, [...$popular, 'filter' => [['field' => 'views', 'value' => 1]]], $popular],
            'in a filter' => [self::POSTS, ['filters' => [[...self::POPULAR, 'operater' => '<']], 'sorts' => self::BY_ID], $popular],
            'in a nested filter' => [
                self::POSTS,
                ['filters' => [['nested' => [[...self::POPULAR, 'typ' => 'or']]]], 'sorts' => self::BY_ID],
                ['filters' => [['nested' => [self::POPULAR]]], 'sorts' => self::BY_ID],
            ],
            'in a relation group' => [
                self::POSTS,
                ['filters' => [['relation' => 'author', 'nested' => [['field' => 'first_name', 'value' => 'Grace']], 'exist' => false]], 'sorts' => self::BY_ID],
                ['filters' => [['relation' => 'author', 'nested' => [['field' => 'first_name', 'value' => 'Grace']]]], 'sorts' => self::BY_ID],
            ],
            'in a sort' => [self::POSTS, ['sorts' => [['field' => 'views', 'direction' => 'desc', 'order' => 'asc']]], ['sorts' => [['field' => 'views', 'direction' => 'desc']]]],
            'in a select' => [self::POSTS, [...$popular, 'selects' => [['field' => 'title', 'as' => 'headline']]], [...$popular, 'selects' => [['field' => 'title']]]],
            'in a scope' => [
                self::POSTS,
                ['scopes' => [['name' => 'popular', 'parameters' => [5], 'params' => [50]]], 'sorts' => self::BY_ID],
                ['scopes' => [['name' => 'popular', 'parameters' => [5]]], 'sorts' => self::BY_ID],
            ],
            'in an instruction' => [
                self::POSTS,
                ['instructions' => [['name' => 'highlight-posts', 'field' => [['name' => 'featured', 'value' => false]]]], 'sorts' => self::BY_ID],
                ['instructions' => [['name' => 'highlight-posts']], 'sorts' => self::BY_ID],
            ],
            'in an instruction field' => [
                self::POSTS,
                ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'featured', 'value' => false, 'default' => true]]]], 'sorts' => self::BY_ID],
                ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'featured', 'value' => false]]]], 'sorts' => self::BY_ID],
            ],
            'an instruction field that is not declared' => [
                self::POSTS,
                ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'pinned', 'value' => 1], ['name' => 'featured', 'value' => false], ['name' => 'pinned']]]], 'sorts' => self::BY_ID],
                ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'featured', 'value' => false]]]], 'sorts' => self::BY_ID],
            ],
            'in an include' => [
                self::POSTS,
                [...$popular, 'includes' => [['relation' => 'author', 'filter' => [['field' => 'first_name', 'value' => 'Grace']]]]],
                [...$popular, 'includes' => [['relation' => 'author']]],
            ],
            'in an aggregate' => [
                self::CATEGORIES,
                ['aggregates' => [['relation' => 'products', 'type' => 'count', 'as' => 'total']]],
                ['aggregates' => [['relation' => 'products', 'type' => 'count']]],
            ],
            'named with an underscore' => [self::POSTS, ['filters' => [[...self::POPULAR, 'sort_by' => 'views']], 'sorts' => self::BY_ID], $popular],
            'holding a wildcard, a dot or a final backslash' => [self::POSTS, ['filters' => [[...self::POPULAR, '*' => 1, 'a.b' => 2, 'a\\' => 3]], 'sorts' => self::BY_ID], $popular],
        ];
    }

    #[Test]
    public function it_runs_a_filter_that_holds_an_unknown_key_as_the_filter_alone(): void
    {
        $this->captureQueries();

        $response = $this->postJson(self::POSTS, ['search' => [
            'filters' => [[...self::POPULAR, 'operater' => '<']],
            'sorts' => self::BY_ID,
            'selects' => [['field' => 'title']],
        ]]);

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [['title' => 'Annual report'], ['title' => 'Release plan']],
            'from' => 1,
            'last_page' => 1,
            'meta' => [],
            'per_page' => 50,
            'to' => 2,
            'total' => 2,
        ]);
        $this->assertSame([
            ['select posts.title, posts.id from posts where (posts.views > ?) order by posts.id asc limit 50 offset 0', [5]],
        ], $this->statements);
    }

    #[Test]
    public function it_still_validates_the_known_keys_next_to_an_unknown_key(): void
    {
        $response = $this->postJson(self::POSTS, ['search' => ['filters' => [['field' => 'secret', 'value' => 1, 'operater' => '>']]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.filters.0.field is invalid.',
            'errors' => ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']],
        ]);
    }

    #[Test]
    public function it_still_refuses_a_page_and_types_in_an_include(): void
    {
        $response = $this->postJson(self::POSTS, ['search' => ['includes' => [['relation' => 'author', 'page' => 0, 'types' => [], 'filter' => []]]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.includes.0.page' => ['The search.includes.0.page field is prohibited.'],
            'search.includes.0.types' => ['The search.includes.0.types field is prohibited.'],
        ]);
    }
}
