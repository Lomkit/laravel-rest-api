<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnknownSearchKeysTest extends PackageTestCase
{
    use CapturesQueries;

    private const string POSTS = '/api/posts/search';

    private const string CATEGORIES = '/api/categories/search';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('unknownKeys')]
    public function it_rejects_an_unknown_key_on_its_key(string $uri, array $search, string $key): void
    {
        $message = "The {$key} field is prohibited.";

        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function unknownKeys(): array
    {
        return [
            'in a filter' => [self::POSTS, ['filters' => [['field' => 'title', 'operater' => 'like', 'value' => '%report%']]], 'search.filters.0.operater'],
            'in a nested filter' => [self::POSTS, ['filters' => [['nested' => [['field' => 'views', 'value' => 1, 'typ' => 'or']]]]], 'search.filters.0.nested.0.typ'],
            'in a sort' => [self::POSTS, ['sorts' => [['field' => 'views', 'order' => 'desc']]], 'search.sorts.0.order'],
            'in a select' => [self::POSTS, ['selects' => [['field' => 'title', 'as' => 'headline']]], 'search.selects.0.as'],
            'in a scope' => [self::POSTS, ['scopes' => [['name' => 'popular', 'params' => [5]]]], 'search.scopes.0.params'],
            'in an instruction' => [self::POSTS, ['instructions' => [['name' => 'highlight-posts', 'field' => []]]], 'search.instructions.0.field'],
            'in an instruction field' => [
                self::POSTS,
                ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'featured', 'value' => true, 'default' => false]]]]],
                'search.instructions.0.fields.0.default',
            ],
            'in an include' => [self::POSTS, ['includes' => [['relation' => 'author', 'filter' => []]]], 'search.includes.0.filter'],
            'a page in an include' => [self::POSTS, ['includes' => [['relation' => 'author', 'page' => 2]]], 'search.includes.0.page'],
            'types in an include' => [self::POSTS, ['includes' => [['relation' => 'author', 'types' => []]]], 'search.includes.0.types'],
            'in an aggregate' => [self::CATEGORIES, ['aggregates' => [['relation' => 'products', 'type' => 'count', 'as' => 'total']]], 'search.aggregates.0.as'],
            'named with an underscore' => [self::POSTS, ['filters' => [['field' => 'views', 'value' => 1, 'sort_by' => 'views']]], 'search.filters.0.sort_by'],
        ];
    }

    /** @param array<string, mixed> $unknown */
    #[Test]
    #[DataProvider('unknownRootKeys')]
    public function it_answers_as_if_an_unknown_key_of_the_search_were_absent(array $unknown): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Annual report', 'views' => 10]);
        Post::factory()->create(['id' => 2, 'title' => 'Draft notes', 'views' => 3]);
        Post::factory()->create(['id' => 3, 'title' => 'Release plan', 'views' => 40]);
        $search = ['filters' => [['field' => 'views', 'operator' => '>', 'value' => 5]], 'sorts' => [['field' => 'id']], 'selects' => [['field' => 'title']]];
        $this->captureQueries();

        $response = $this->postJson(self::POSTS, ['search' => [...$unknown, ...$search]]);

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

    /** @return array<string, array{array<string, mixed>}> */
    public static function unknownRootKeys(): array
    {
        return [
            'a misspelled list' => [['filtres' => [['field' => 'views', 'value' => 1]]]],
            'named with an underscore' => [['sort_by' => 'views']],
            'holding a wildcard, a dot or a final backslash' => [['*' => 1, 'filters.0.field' => 'secret', 'a\\' => 3]],
        ];
    }

    #[Test]
    public function it_applies_no_page_rule_in_an_include(): void
    {
        $message = 'The search.includes.0.page field is prohibited.';

        $response = $this->postJson(self::POSTS, ['search' => ['includes' => [['relation' => 'author', 'page' => 0]]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.includes.0.page' => [$message]]]);
    }

    #[Test]
    public function it_rejects_a_key_that_ends_with_a_backslash_on_its_object(): void
    {
        $message = 'The a\\ key is not valid for the search.filters.0 field.';

        $response = $this->postJson(self::POSTS, ['search' => ['filters' => [['field' => 'views', 'value' => 1, 'a\\' => 1]]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0' => [$message]]]);
    }

    #[Test]
    public function it_reports_the_unknown_keys_of_an_object_first(): void
    {
        $response = $this->postJson(self::POSTS, ['search' => ['filters' => [['operater' => 'like', 'fiedl' => 'title']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.operater' => ['The search.filters.0.operater field is prohibited.'],
            'search.filters.0.fiedl' => ['The search.filters.0.fiedl field is prohibited.'],
            'search.filters.0.field' => ['The search.filters.0.field field is required when search.filters.0.nested is not present.'],
        ]);
    }

    #[Test]
    public function it_validates_the_other_keys_of_an_object_that_holds_an_unknown_key(): void
    {
        $response = $this->postJson(self::POSTS, ['search' => ['filters' => [['field' => 'secret', 'value' => 1, 'operater' => '>']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.operater' => ['The search.filters.0.operater field is prohibited.'],
            'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
        ]);
    }
}
