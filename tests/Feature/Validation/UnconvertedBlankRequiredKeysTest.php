<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnconvertedBlankRequiredKeysTest extends PackageTestCase
{
    private const string POSTS = '/api/posts/search';

    private const string CATEGORIES = '/api/categories/search';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    #[DataProvider('requiredKeys')]
    public function it_keeps_the_message_of_the_rule_that_requires_a_blank_key_of_the_search(string $uri, mixed $search, string $key, string $message): void
    {
        $response = $this->unconverted()->postJson($uri, ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, mixed, string, string}> */
    public static function requiredKeys(): array
    {
        return [
            'a blank filter field' => [
                self::POSTS,
                ['filters' => [['field' => '', 'value' => 1]]],
                'search.filters.0.field',
                'The search.filters.0.field field is required when search.filters.0.nested is not present.',
            ],
            'a blank sort field' => [self::POSTS, ['sorts' => [['field' => '']]], 'search.sorts.0.field', 'The search.sorts.0.field field is required.'],
            'a blank selected field' => [self::POSTS, ['selects' => [['field' => '']]], 'search.selects.0.field', 'The search.selects.0.field field is required.'],
            'a blank scope name' => [self::POSTS, ['scopes' => [['name' => '']]], 'search.scopes.0.name', 'The search.scopes.0.name field is required.'],
            'a blank instruction name' => [
                self::POSTS,
                ['instructions' => [['name' => '']]],
                'search.instructions.0.name',
                'The search.instructions.0.name field is required.',
            ],
            'a blank instruction field name' => [
                self::POSTS,
                ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => '', 'value' => true]]]]],
                'search.instructions.0.fields.0.name',
                'The search.instructions.0.fields.0.name field is required.',
            ],
            'a blank aggregate relation' => [
                self::CATEGORIES,
                ['aggregates' => [['relation' => '', 'type' => 'count']]],
                'search.aggregates.0.relation',
                'The search.aggregates.0.relation field is required.',
            ],
            'a blank aggregate type' => [
                self::CATEGORIES,
                ['aggregates' => [['relation' => 'products', 'type' => '']]],
                'search.aggregates.0.type',
                'The search.aggregates.0.type field is required.',
            ],
            'a blank aggregated field' => [
                self::CATEGORIES,
                ['aggregates' => [['relation' => 'products', 'type' => 'sum', 'field' => '']]],
                'search.aggregates.0.field',
                'The search.aggregates.0.field field is required when search.aggregates.0.type is sum.',
            ],
            'a blank filter field in an aggregate' => [
                self::CATEGORIES,
                ['aggregates' => [['relation' => 'products', 'type' => 'count', 'filters' => [['field' => '', 'value' => 1]]]]],
                'search.aggregates.0.filters.0.field',
                'The search.aggregates.0.filters.0.field field is required when search.aggregates.0.filters.0.nested is not present.',
            ],
            'a blank include relation' => [
                self::POSTS,
                ['includes' => [['relation' => '']]],
                'search.includes.0.relation',
                'The search.includes.0.relation field is required.',
            ],
        ];
    }

    #[Test]
    public function it_gives_the_required_message_to_a_blank_required_key_with_or_without_the_middleware(): void
    {
        $message = 'The search.sorts.0.field field is required.';
        $search = ['search' => ['sorts' => [['field' => '']]]];

        $converted = $this->postJson(self::POSTS, $search);
        $unconverted = $this->unconverted()->postJson(self::POSTS, $search);

        $converted->assertUnprocessable();
        $this->assertContains($message, $converted->json('errors')['search.sorts.0.field']);
        $unconverted->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.sorts.0.field' => [$message]]]);
    }

    private function unconverted(): self
    {
        return $this->withoutMiddleware([ConvertEmptyStringsToNull::class, TrimStrings::class]);
    }
}
