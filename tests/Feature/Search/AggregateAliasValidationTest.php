<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Feature\Serialization\SeedsPublishingRows;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateAliasValidationTest extends PackageTestCase
{
    use SeedsPublishingRows;

    private const string FIELD = 'conflicts with a field of this resource.';

    private const string RELATION = 'conflicts with a relation of this resource.';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('clashes')]
    public function it_refuses_an_aggregate_key_that_cannot_name_the_aggregate(array $search, array $errors): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function clashes(): array
    {
        $format = ['search.aggregates.0.alias' => ['The search.aggregates.0.alias field format is invalid.']];
        $field = ['search.aggregates.0.alias' => ['The search.aggregates.0.alias '.self::FIELD]];

        return [
            'a dash' => [self::counted('posts-total'), $format],
            'a dot' => [self::counted('posts.total'), $format],
            'a space' => [self::counted('posts total'), $format],
            'a leading digit' => [self::counted('1st'), $format],
            'more than 255 characters' => [self::counted(str_repeat('a', 256)), [
                'search.aggregates.0.alias' => ['The search.aggregates.0.alias field must not be greater than 255 characters.'],
            ]],
            'the gates key' => [self::counted('gates'), ['search.aggregates.0.alias' => ['The search.aggregates.0.alias is reserved.']]],
            'a field' => [self::counted('last_name'), $field],
            'a write only field' => [self::counted('api_token'), $field],
            'a virtual field' => [self::counted('initials'), $field],
            'a relation' => [self::counted('tags'), ['search.aggregates.0.alias' => ['The search.aggregates.0.alias '.self::RELATION]]],
            'an include alias' => [
                [...self::counted('written'), 'includes' => [['relation' => 'posts', 'alias' => 'written']]],
                [
                    'search.aggregates.0.alias' => ['The search.aggregates.0.alias is already used by an include.'],
                    'search.includes.0.alias' => ['The search.includes.0.alias is already used by an aggregate.'],
                ],
            ],
            'an include alias equal to a key without alias' => [
                ['aggregates' => [['relation' => 'posts', 'type' => 'count']], 'includes' => [['relation' => 'posts', 'alias' => 'posts_count']]],
                [
                    'search.aggregates.0.alias' => ['The search.aggregates.0.alias field is required, because the key posts_count is already used.'],
                    'search.includes.0.alias' => ['The search.includes.0.alias is already used by an aggregate.'],
                ],
            ],
            'an include without alias' => [
                [...self::counted('posts'), 'includes' => [['relation' => 'posts']]],
                [
                    'search.aggregates.0.alias' => ['The search.aggregates.0.alias '.self::RELATION],
                    'search.includes.0.alias' => ['The search.includes.0.alias field is required, because the key posts is already used.'],
                ],
            ],
            'two aliases' => [
                ['aggregates' => [
                    ['relation' => 'posts', 'type' => 'count', 'alias' => 'n'],
                    ['relation' => 'posts', 'type' => 'max', 'field' => 'views', 'alias' => 'n'],
                ]],
                [
                    'search.aggregates.0.alias' => ['The search.aggregates.0.alias is already used by another aggregate.'],
                    'search.aggregates.1.alias' => ['The search.aggregates.1.alias is already used by another aggregate.'],
                ],
            ],
            'two keys without alias' => [
                ['aggregates' => [
                    ['relation' => 'posts', 'type' => 'count'],
                    ['relation' => 'posts', 'type' => 'count', 'filters' => [['field' => 'status', 'value' => 'draft']]],
                ]],
                [
                    'search.aggregates.0.alias' => ['The search.aggregates.0.alias field is required, because the key posts_count is already used.'],
                    'search.aggregates.1.alias' => ['The search.aggregates.1.alias field is required, because the key posts_count is already used.'],
                ],
            ],
            'an alias equal to a key without alias' => [
                ['aggregates' => [
                    ['relation' => 'posts', 'type' => 'count'],
                    ['relation' => 'posts', 'type' => 'max', 'field' => 'views', 'alias' => 'posts_count'],
                ]],
                [
                    'search.aggregates.0.alias' => ['The search.aggregates.0.alias field is required, because the key posts_count is already used.'],
                    'search.aggregates.1.alias' => ['The search.aggregates.1.alias is already used by another aggregate.'],
                ],
            ],
            'an aggregate of an include' => [
                ['includes' => [['relation' => 'posts', 'aggregates' => [['relation' => 'author', 'type' => 'count', 'alias' => 'title']]]]],
                ['search.includes.0.aggregates.0.alias' => ['The search.includes.0.aggregates.0.alias '.self::FIELD]],
            ],
        ];
    }

    #[Test]
    public function it_refuses_an_include_alias_used_by_an_include_without_alias(): void
    {
        $response = $this->postJson('/api/categories/search', ['search' => [
            'includes' => [['relation' => 'featuredProducts'], ['relation' => 'products', 'alias' => 'featured_products']],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.includes.1.alias' => ['The search.includes.1.alias is already used by another include.'],
        ]);
    }

    #[Test]
    public function it_reserves_the_configured_gates_key(): void
    {
        config(['rest.gates.key' => 'abilities']);
        $this->seedPublishingRows();

        $this->postJson('/api/authors/search', ['search' => self::counted('abilities')])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['search.aggregates.0.alias' => ['The search.aggregates.0.alias is reserved.']]);

        $response = $this->postJson('/api/authors/search', ['search' => [...self::counted('gates'), 'sorts' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertJsonPath('data.*.gates', [2, 0]);
    }

    #[Test]
    public function it_accepts_an_alias_that_names_no_other_key_of_the_row(): void
    {
        $this->seedPublishingRows();

        $response = $this->postJson('/api/authors/search', ['search' => [...self::counted('written'), 'sorts' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertJsonPath('data.*.written', [2, 0]);
    }

    /** @return array{aggregates: list<array{relation: string, type: string, alias: string}>} */
    private static function counted(string $alias): array
    {
        return ['aggregates' => [['relation' => 'posts', 'type' => 'count', 'alias' => $alias]]];
    }
}
