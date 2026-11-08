<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationGroupValidationTest extends PackageTestCase
{
    private const array VIEWS = ['field' => 'views', 'value' => 1];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidGroups')]
    public function it_refuses_an_invalid_relation_group(array $filters, array $errors): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => $filters]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{list<array<string, mixed>>, array<string, list<string>>}> */
    public static function invalidGroups(): array
    {
        $unknownRelation = ['search.filters.0.relation' => ['The selected search.filters.0.relation is invalid.']];
        $notBoolean = ['search.filters.0.exists' => ['The search.filters.0.exists field must be true or false.']];

        return [
            'an unknown relation' => [[['relation' => 'comments', 'nested' => [['field' => 'status', 'value' => 'draft']]]], $unknownRelation],
            'the nested of an unknown relation' => [[['relation' => 'comments', 'nested' => 'draft']], $unknownRelation],
            'a dotted relation' => [[['relation' => 'posts.author']], $unknownRelation],
            'a relation that is not a string' => [[['relation' => ['posts']]], [
                'search.filters.0.relation' => ['The search.filters.0.relation field must be a string.', 'The selected search.filters.0.relation is invalid.'],
            ]],
            'a null relation' => [[['relation' => null, 'nested' => [['field' => 'status', 'value' => 'draft']]]], [
                'search.filters.0.relation' => ['The search.filters.0.relation field is required.'],
            ]],
            'a condition next to the relation' => [[['relation' => 'posts', 'field' => 'status', 'operator' => '=', 'value' => 'draft']], [
                'search.filters.0.field' => ['The search.filters.0.field field is prohibited.'],
                'search.filters.0.operator' => ['The search.filters.0.operator field is prohibited.'],
                'search.filters.0.value' => ['The search.filters.0.value field is prohibited.'],
            ]],
            'types next to the relation' => [[['relation' => 'posts', 'types' => ['post' => []]]], [
                'search.filters.0.types' => ['The search.filters.0.types field is prohibited.'],
            ]],
            'exists as a string' => [[['relation' => 'posts', 'exists' => 'no']], $notBoolean],
            'exists as a number' => [[['relation' => 'posts', 'exists' => 0]], $notBoolean],
            'a null exists' => [[['relation' => 'posts', 'exists' => null]], $notBoolean],
            'exists without a relation' => [[['field' => 'first_name', 'value' => 'Ada', 'exists' => false]], [
                'search.filters.0.exists' => ['The search.filters.0.exists field is prohibited.'],
            ]],
            'a type other than and or or' => [[['relation' => 'posts', 'type' => 'xor']], [
                'search.filters.0.type' => ['The selected search.filters.0.type is invalid.'],
            ]],
            'nested as an object' => [[['relation' => 'posts', 'nested' => ['a' => self::VIEWS]]], [
                'search.filters.0.nested' => ['The search.filters.0.nested field must be a list.'],
            ]],
            'nested as a string' => [[['relation' => 'posts', 'nested' => 'draft']], [
                'search.filters.0.nested' => ['The search.filters.0.nested field must be an array.'],
            ]],
            'a null nested' => [[['relation' => 'posts', 'nested' => null]], [
                'search.filters.0.nested' => ['The search.filters.0.nested field must be an array.'],
            ]],
            'errors in the order of the group keys' => [[['nested' => 'draft', 'type' => 'xor', 'exists' => 'no', 'relation' => 'posts', 'typ' => 1]], [
                ...$notBoolean,
                'search.filters.0.type' => ['The selected search.filters.0.type is invalid.'],
                'search.filters.0.nested' => ['The search.filters.0.nested field must be an array.'],
            ]],
            'an unknown key next to a wrong relation' => [[['exists' => 'no', 'relation' => 'comments', 'typ' => 1]], [
                ...$unknownRelation,
                ...$notBoolean,
            ]],
            'a field of the resource itself' => [[['relation' => 'posts', 'nested' => [['field' => 'first_name', 'value' => 'Ada']]]], [
                'search.filters.0.nested.0.field' => ['The selected search.filters.0.nested.0.field is invalid.'],
            ]],
            'an operator that the related field refuses' => [[['relation' => 'posts', 'nested' => [['field' => 'status', 'operator' => 'like', 'value' => 'dr%']]]], [
                'search.filters.0.nested.0.operator' => ['The selected search.filters.0.nested.0.operator is invalid.'],
            ]],
            'a value of the wrong type' => [[['relation' => 'posts', 'nested' => [['field' => 'views', 'value' => 'many']]]], [
                'search.filters.0.nested.0.value' => ['The search.filters.0.nested.0.value field must be an integer.'],
            ]],
        ];
    }

    /** @param list<array<string, mixed>> $filters */
    #[Test]
    #[DataProvider('tooDeepGroups')]
    public function it_counts_the_filters_of_a_relation_group_as_one_level(array $filters): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => $filters]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.nested.0.nested' => ['The search.filters.0.nested.0.nested field is prohibited.'],
        ]);
    }

    /** @return array<string, array{list<array<string, mixed>>}> */
    public static function tooDeepGroups(): array
    {
        return [
            'a group in a relation group' => [[['relation' => 'posts', 'nested' => [['nested' => [self::VIEWS]]]]]],
            'a relation group in a relation group' => [[['relation' => 'posts', 'nested' => [['relation' => 'author', 'nested' => [['field' => 'first_name', 'value' => 'Ada']]]]]]],
            'a relation group in a group' => [[['nested' => [['relation' => 'posts', 'nested' => [self::VIEWS]]]]]],
        ];
    }

    #[Test]
    public function it_applies_the_filter_size_to_the_filters_of_a_relation_group(): void
    {
        config(['rest.limits.sizes.filters' => 2]);
        $message = 'The search.filters.0.nested field must not have more than 2 items.';

        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => [
            ['relation' => 'posts', 'nested' => [self::VIEWS, self::VIEWS, self::VIEWS]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.nested' => [$message]]]);
    }

    #[Test]
    public function it_applies_no_filter_size_to_the_filters_of_an_unknown_relation(): void
    {
        config(['rest.limits.sizes.filters' => 2]);

        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => [
            ['relation' => 'comments', 'nested' => [self::VIEWS, self::VIEWS, self::VIEWS]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.relation' => ['The selected search.filters.0.relation is invalid.'],
        ]);
    }

    #[Test]
    public function it_counts_the_filters_of_a_relation_group_toward_the_total(): void
    {
        config(['rest.limits.totals.filters' => 3]);
        $message = 'The search.filters.0.nested field goes over the limit of 3 filters in one search.';

        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => [
            ['relation' => 'posts', 'nested' => [self::VIEWS, self::VIEWS, self::VIEWS]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.nested' => [$message]]]);
    }
}
