<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnconvertedBlankStringsTest extends PackageTestCase
{
    private const string POSTS = '/api/posts/search';

    private const string CATEGORIES = '/api/categories/search';

    private const string AUTHORS = '/api/authors/search';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    #[DataProvider('blankKeys')]
    public function it_asks_for_a_value_on_a_blank_key_of_the_search(string $uri, mixed $search, string $key): void
    {
        $message = "The {$key} field must have a value.";

        $response = $this->unconverted()->postJson($uri, ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function blankKeys(): array
    {
        $include = fn (array $body): array => ['includes' => [['relation' => 'author', ...$body]]];
        $aggregate = fn (array $body): array => ['aggregates' => [['relation' => 'products', 'type' => 'count', ...$body]]];

        return [
            'blank scopes' => [self::POSTS, ['scopes' => '  '], 'search.scopes'],
            'blank sorts' => [self::POSTS, ['sorts' => ''], 'search.sorts'],
            'blank selects' => [self::POSTS, ['selects' => ''], 'search.selects'],
            'blank aggregates' => [self::CATEGORIES, ['aggregates' => ''], 'search.aggregates'],
            'blank includes' => [self::POSTS, ['includes' => ''], 'search.includes'],
            'blank instructions' => [self::POSTS, ['instructions' => ''], 'search.instructions'],
            'blank gates' => [self::POSTS, ['gates' => ''], 'search.gates'],
            'a blank nested group' => [self::POSTS, ['filters' => [['field' => 'views', 'value' => 1, 'nested' => '']]], 'search.filters.0.nested'],
            'a blank scope' => [self::POSTS, ['scopes' => ['']], 'search.scopes.0'],
            'a blank select' => [self::POSTS, ['selects' => ['  ']], 'search.selects.0'],
            'a blank aggregate' => [self::CATEGORIES, ['aggregates' => ['']], 'search.aggregates.0'],
            'a blank instruction' => [self::POSTS, ['instructions' => ['']], 'search.instructions.0'],
            'a blank nested filter' => [self::POSTS, ['filters' => [['nested' => ['']]]], 'search.filters.0.nested.0'],
            'a blank limit' => [self::POSTS, ['limit' => '  '], 'search.limit'],
            'a blank page' => [self::POSTS, ['page' => ''], 'search.page'],
            'a blank trashed value' => [self::AUTHORS, ['trashed' => ''], 'search.trashed'],
            'a blank trashed value without soft deletes' => [self::POSTS, ['trashed' => '  '], 'search.trashed'],
            'a blank operator' => [self::POSTS, ['filters' => [['field' => 'views', 'operator' => '', 'value' => 1]]], 'search.filters.0.operator'],
            'a blank filter type' => [self::POSTS, ['filters' => [['field' => 'views', 'value' => 1, 'type' => ' ']]], 'search.filters.0.type'],
            'a blank nested filter type' => [
                self::POSTS, ['filters' => [['nested' => [['field' => 'views', 'value' => 1, 'type' => '']]]]], 'search.filters.0.nested.0.type',
            ],
            'a blank direction' => [self::POSTS, ['sorts' => [['field' => 'views', 'direction' => '']]], 'search.sorts.0.direction'],
            'blank scope parameters' => [self::POSTS, ['scopes' => [['name' => 'popular', 'parameters' => '']]], 'search.scopes.0.parameters'],
            'blank instruction fields' => [self::POSTS, ['instructions' => [['name' => 'highlight-posts', 'fields' => '']]], 'search.instructions.0.fields'],
            'a blank aggregate alias' => [self::CATEGORIES, $aggregate(['alias' => '']), 'search.aggregates.0.alias'],
            'blank filters in an aggregate' => [self::CATEGORIES, $aggregate(['filters' => '']), 'search.aggregates.0.filters'],
            'a blank include alias' => [self::POSTS, $include(['alias' => '']), 'search.includes.0.alias'],
            'a blank limit in an include' => [self::POSTS, $include(['limit' => '']), 'search.includes.0.limit'],
            'blank filters in an include' => [self::POSTS, $include(['filters' => '']), 'search.includes.0.filters'],
            'a blank filter in an include' => [self::POSTS, $include(['filters' => ['']]), 'search.includes.0.filters.0'],
            'a blank direction in an include' => [
                self::POSTS, $include(['sorts' => [['field' => 'first_name', 'direction' => '']]]), 'search.includes.0.sorts.0.direction',
            ],
            'a blank include in an include' => [self::POSTS, $include(['includes' => ['']]), 'search.includes.0.includes.0'],
            'a blank trashed value in an include' => [self::POSTS, $include(['trashed' => '']), 'search.includes.0.trashed'],
            'blank includes at the include depth limit' => [
                self::POSTS, $include(['includes' => [['relation' => 'posts', 'includes' => [['relation' => 'author', 'includes' => '']]]]]), 'search.includes.0.includes.0.includes.0.includes',
            ],
        ];
    }

    #[Test]
    #[DataProvider('actionSearches')]
    public function it_asks_for_a_value_on_a_blank_key_of_the_search_of_an_action(mixed $search, string $key): void
    {
        $message = "The {$key} field must have a value.";

        $response = $this->unconverted()->postJson('/api/posts/actions/publish-posts', ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{mixed, string}> */
    public static function actionSearches(): array
    {
        return [
            'a blank search' => ['', 'search'],
            'blank filters' => [['filters' => ' '], 'search.filters'],
            'a blank operator' => [['filters' => [['field' => 'views', 'operator' => '', 'value' => 1]]], 'search.filters.0.operator'],
            'a blank trashed value' => [['trashed' => ''], 'search.trashed'],
        ];
    }

    /** @param array<string, list<string>> $errors */
    #[Test]
    #[DataProvider('convertedBlanks')]
    public function it_keeps_the_messages_of_blank_strings_read_as_null(string $uri, mixed $search, array $errors): void
    {
        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, mixed, array<string, list<string>>}> */
    public static function convertedBlanks(): array
    {
        return [
            'a blank search' => [self::POSTS, ' ', ['search' => ['The search field must be an array.']]],
            'blank filters' => [self::POSTS, ['filters' => ''], ['search.filters' => ['The search.filters field must be an array.']]],
            'a blank direction' => [
                self::POSTS, ['sorts' => [['field' => 'views', 'direction' => '']]], ['search.sorts.0.direction' => ['The selected search.sorts.0.direction is invalid.']],
            ],
            'a blank sort field' => [self::POSTS, ['sorts' => [['field' => '']]], ['search.sorts.0.field' => [
                'The selected search.sorts.0.field is invalid.',
                'The search.sorts.0.field field is required.',
            ]]],
        ];
    }

    private function unconverted(): self
    {
        return $this->withoutMiddleware([ConvertEmptyStringsToNull::class, TrimStrings::class]);
    }
}
