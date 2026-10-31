<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PatternAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterFieldsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function unfilterableFields(): array
    {
        $filter = fn (string $field): array => ['filters' => [['field' => $field, 'value' => 'Ada']]];

        return [
            'an undeclared field' => ['/api/posts', $filter('subtitle'), 'search.filters.0.field'],
            'a field of an undeclared relation' => ['/api/posts', $filter('writer.first_name'), 'search.filters.0.field'],
            'a relation without a field' => ['/api/posts', $filter('author'), 'search.filters.0.field'],
            'a pivot segment on a relation without pivot' => ['/api/posts', $filter('author.pivot.first_name'), 'search.filters.0.field'],
            'a json field' => ['/api/posts', $filter('metadata'), 'search.filters.0.field'],
            'a json field of a relation' => ['/api/posts', $filter('author.settings'), 'search.filters.0.field'],
            'a write-only field of a relation' => ['/api/posts', $filter('author.api_token'), 'search.filters.0.field'],
            'a virtual field of a relation without a filter callback' => ['/api/posts', $filter('author.initials'), 'search.filters.0.field'],
            'a field declared not filterable' => ['/pattern-authors', $filter('first_name'), 'search.filters.0.field'],
            'a nested field declared not filterable' => ['/pattern-authors', ['filters' => [['nested' => [$filter('first_name')['filters'][0]]]]], 'search.filters.0.nested.0.field'],
            'a write-only field in an include filter' => ['/api/posts', ['includes' => [['relation' => 'author', ...$filter('api_token')]]], 'search.includes.0.filters.0.field'],
            'a field of the parent in an include filter' => ['/api/posts', ['includes' => [['relation' => 'author', ...$filter('title')]]], 'search.includes.0.filters.0.field'],
            'a json field in an aggregate filter' => ['/api/authors', ['aggregates' => [['relation' => 'posts', 'type' => 'count', ...$filter('metadata')]]], 'search.aggregates.0.filters.0.field'],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('unfilterableFields')]
    public function it_refuses_a_field_that_cannot_be_filtered(string $endpoint, array $search, string $key): void
    {
        Rest::resource('pattern-authors', PatternAuthorController::class)->register();

        $response = $this->postJson($endpoint.'/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', [$key => ["The selected {$key} is invalid."]]);
    }
}
