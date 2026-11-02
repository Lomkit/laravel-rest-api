<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MalformedSearchPayloadTest extends PackageTestCase
{
    private const string POSTS = '/api/posts/search';

    private const string CATEGORIES = '/api/categories/search';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    #[DataProvider('malformedSearches')]
    public function it_rejects_a_malformed_search_on_its_key(string $uri, mixed $search, string $key, string $message): void
    {
        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, mixed, string, string}> */
    public static function malformedSearches(): array
    {
        return [
            'a search that is not an object' => [self::POSTS, 'boom', 'search', 'The search field must be an array.'],
            'filters that are not an array' => [self::POSTS, ['filters' => 'views'], 'search.filters', 'The search.filters field must be an array.'],
            'a sort that is not an object' => [self::POSTS, ['sorts' => ['views']], 'search.sorts.0', 'The search.sorts.0 field must be an array.'],
            'a scope that is not an object' => [self::POSTS, ['scopes' => ['popular']], 'search.scopes.0', 'The search.scopes.0 field must be an array.'],
            'an instruction that is not an object' => [
                self::POSTS, ['instructions' => ['highlight-posts']], 'search.instructions.0', 'The search.instructions.0 field must be an array.',
            ],
            'an aggregate that is null' => [self::CATEGORIES, ['aggregates' => [null]], 'search.aggregates.0', 'The search.aggregates.0 field must be an array.'],
            'a text without text search' => [self::POSTS, ['text' => ['value' => 'report']], 'search.text', 'The search.text field is prohibited.'],
            'trashed rows without soft deletes' => [self::POSTS, ['trashed' => 'with'], 'search.trashed', 'The search.trashed field is prohibited.'],
            'a text in an include' => [
                self::POSTS, ['includes' => [['relation' => 'author', 'text' => ['value' => 'ada']]]], 'search.includes.0.text', 'The search.includes.0.text field is prohibited.',
            ],
            'an unknown gate' => [self::POSTS, ['gates' => ['view', 'approve']], 'search.gates', 'The selected search.gates is invalid.'],
            'an unknown direction' => [
                self::POSTS, ['sorts' => [['field' => 'views', 'direction' => 'down']]], 'search.sorts.0.direction', 'The selected search.sorts.0.direction is invalid.',
            ],
            'an unknown selected field' => [
                self::POSTS, ['selects' => [['field' => 'secret']]], 'search.selects.0.field', 'The selected search.selects.0.field is invalid.',
            ],
            'a relation path to an unknown field' => [
                self::POSTS, ['filters' => [['field' => 'author.secret', 'value' => 1]]], 'search.filters.0.field', 'The selected search.filters.0.field is invalid.',
            ],
            'an unknown operator' => [
                self::POSTS,
                ['filters' => [['field' => 'views', 'operator' => 'near', 'value' => 1]]],
                'search.filters.0.operator',
                'The selected search.filters.0.operator is invalid.',
            ],
            'a list value for an equal filter' => [
                self::POSTS, ['filters' => [['field' => 'views', 'value' => [1, 2]]]], 'search.filters.0.value', 'The search.filters.0.value field must be an integer.',
            ],
            'a group that is not an array' => [
                self::POSTS, ['filters' => [['nested' => 'views']]], 'search.filters.0.nested', 'The search.filters.0.nested field must be an array.',
            ],
            'a limit the resource does not allow' => [self::POSTS, ['limit' => 5], 'search.limit', 'The selected search.limit is invalid.'],
            'an include limit the related resource does not allow' => [
                self::POSTS, ['includes' => [['relation' => 'author', 'limit' => 5]]], 'search.includes.0.limit', 'The selected search.includes.0.limit is invalid.',
            ],
            'an unknown gate in an include' => [
                self::POSTS, ['includes' => [['relation' => 'author', 'gates' => ['approve']]]], 'search.includes.0.gates', 'The selected search.includes.0.gates is invalid.',
            ],
            'scope parameters given as an object' => [
                self::POSTS,
                ['scopes' => [['name' => 'popular', 'parameters' => ['min_views' => 5]]]],
                'search.scopes.0.parameters',
                'The search.scopes.0.parameters field must be a list.',
            ],
            'a refused text, which is not read' => [
                self::POSTS, ['text' => ['value' => 'report', 'fuzzy' => true]], 'search.text', 'The search.text field is prohibited.',
            ],
            'a group refused at the depth, which is not read' => [
                self::POSTS,
                ['filters' => [['nested' => [['nested' => [['field' => 'views', 'value' => 1, 'typ' => 'or']]]]]]],
                'search.filters.0.nested.0.nested',
                'The search.filters.0.nested.0.nested field is prohibited.',
            ],
            'a text that is not an object' => [self::POSTS, ['text' => 'report'], 'search.text', 'The search.text field must be an array.'],
            'filters given as an object' => [
                self::POSTS, ['filters' => ['first' => ['field' => 'views', 'value' => 1]]], 'search.filters', 'The search.filters field must be a list.',
            ],
            'a nested group given as an object' => [
                self::POSTS,
                ['filters' => [['nested' => ['first' => ['field' => 'views', 'value' => 1]]]]],
                'search.filters.0.nested',
                'The search.filters.0.nested field must be a list.',
            ],
            'gates given as an object' => [self::POSTS, ['gates' => ['first' => 'view']], 'search.gates', 'The search.gates field must be a list.'],
            'instructions given as an object' => [
                self::POSTS, ['instructions' => ['first' => ['name' => 'highlight-posts']]], 'search.instructions', 'The search.instructions field must be a list.',
            ],
            'include filters given as an object' => [
                self::POSTS,
                ['includes' => [['relation' => 'author', 'filters' => ['first' => ['field' => 'first_name', 'value' => 'Ada']]]]],
                'search.includes.0.filters',
                'The search.includes.0.filters field must be a list.',
            ],
            'aggregate filters given as an object' => [
                self::CATEGORIES,
                ['aggregates' => [['relation' => 'products', 'type' => 'count', 'filters' => ['first' => ['field' => 'price', 'value' => 39]]]]],
                'search.aggregates.0.filters',
                'The search.aggregates.0.filters field must be a list.',
            ],
            'scopes given as an object' => [self::POSTS, ['scopes' => ['first' => ['name' => 'popular']]], 'search.scopes', 'The search.scopes field must be a list.'],
            'includes given as an object' => [
                self::POSTS, ['includes' => ['first' => ['relation' => 'author']]], 'search.includes', 'The search.includes field must be a list.',
            ],
            'aggregates given as an object' => [
                self::CATEGORIES, ['aggregates' => ['first' => ['relation' => 'products', 'type' => 'count']]], 'search.aggregates', 'The search.aggregates field must be a list.',
            ],
            'an in filter value given as an object' => [
                self::POSTS,
                ['filters' => [['field' => 'views', 'operator' => 'in', 'value' => ['a' => 'many']]]],
                'search.filters.0.value',
                'The search.filters.0.value field must be a list.',
            ],
            'instruction fields given as an object' => [
                self::POSTS,
                ['instructions' => [['name' => 'highlight-posts', 'fields' => ['a' => ['name' => 'unknown', 'value' => 1]]]]],
                'search.instructions.0.fields',
                'The search.instructions.0.fields field must be a list.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('blankShapes')]
    public function it_rejects_a_blank_string_where_an_object_or_a_list_is_expected(mixed $search, string $key): void
    {
        $message = "The {$key} field must have a value.";

        $response = $this
            ->withoutMiddleware(ConvertEmptyStringsToNull::class)
            ->postJson(self::POSTS, ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{mixed, string}> */
    public static function blankShapes(): array
    {
        return [
            'a blank search' => ['', 'search'],
            'blank filters' => [['filters' => ''], 'search.filters'],
            'a blank filter' => [['filters' => ['']], 'search.filters.0'],
            'a blank sort' => [['sorts' => ['']], 'search.sorts.0'],
            'a blank include' => [['includes' => ['']], 'search.includes.0'],
            'a blank text' => [['text' => ''], 'search.text'],
        ];
    }
}
