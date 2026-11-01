<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ListLimitsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('listsOverTheirLimit')]
    public function it_refuses_a_list_over_its_limit(string $limit, string $method, string $uri, array $payload, string $key): void
    {
        config(["rest.limits.{$limit}" => 2]);
        $message = "The {$key} field must not have more than 2 items.";

        $response = $this->json($method, $uri, $payload);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, string, string, array<string, mixed>, string}> */
    public static function listsOverTheirLimit(): array
    {
        $field = self::three(['field' => 'secret']);
        $name = self::three(['name' => 'secret']);

        return [
            'filters' => ['filters', 'POST', '/api/posts/search', ['search' => ['filters' => $field]], 'search.filters'],
            'a nested group' => ['filters', 'POST', '/api/posts/search', ['search' => ['filters' => [['nested' => $field]]]], 'search.filters.0.nested'],
            'the values of an in filter' => ['values', 'POST', '/api/posts/search', ['search' => ['filters' => [
                ['field' => 'views', 'operator' => 'in', 'value' => [1, 2, 3]],
            ]]], 'search.filters.0.value'],
            'sorts' => ['sorts', 'POST', '/api/posts/search', ['search' => ['sorts' => $field]], 'search.sorts'],
            'selects' => ['selects', 'POST', '/api/posts/search', ['search' => ['selects' => $field]], 'search.selects'],
            'scopes' => ['scopes', 'POST', '/api/posts/search', ['search' => ['scopes' => $name]], 'search.scopes'],
            'gates' => ['gates', 'POST', '/api/posts/search', ['search' => ['gates' => ['view', 'view', 'view']]], 'search.gates'],
            'includes' => ['includes', 'POST', '/api/posts/search', ['search' => ['includes' => self::three(['relation' => 'secret'])]], 'search.includes'],
            'the filters of an include' => ['filters', 'POST', '/api/posts/search', ['search' => ['includes' => [
                ['relation' => 'author', 'filters' => $field],
            ]]], 'search.includes.0.filters'],
            'instructions' => ['instructions', 'POST', '/api/posts/search', ['search' => ['instructions' => $name]], 'search.instructions'],
            'the fields of an instruction' => ['fields', 'POST', '/api/posts/search', ['search' => ['instructions' => [
                ['name' => 'highlight-posts', 'fields' => $name],
            ]]], 'search.instructions.0.fields'],
            'the fields of an action' => ['fields', 'POST', '/api/posts/actions/publish-posts', ['fields' => $name], 'fields'],
            'aggregates' => ['aggregates', 'POST', '/api/categories/search', ['search' => [
                'aggregates' => self::three(['relation' => 'secret', 'type' => 'count']),
            ]], 'search.aggregates'],
            'the filters of an aggregate' => ['filters', 'POST', '/api/categories/search', ['search' => ['aggregates' => [
                ['relation' => 'products', 'type' => 'count', 'filters' => $field],
            ]]], 'search.aggregates.0.filters'],
            'mutate' => ['mutate', 'POST', '/api/categories/mutate', ['mutate' => self::three(['operation' => 'secret'])], 'mutate'],
            'the entries of a relation' => ['relation_entries', 'POST', '/api/categories/mutate', ['mutate' => [[
                'operation' => 'create',
                'attributes' => ['name' => 'Lighting'],
                'relations' => ['products' => self::three(['operation' => 'secret'])],
            ]]], 'mutate.0.relations.products'],
            'the keys of a mutation' => ['values', 'POST', '/api/categories/mutate', ['mutate' => [
                ['operation' => 'update', 'key' => [1, 2, 3], 'attributes' => ['name' => 'Lighting']],
            ]], 'mutate.0.key'],
            'resources' => ['resources', 'DELETE', '/api/authors', ['resources' => [1, 2, 3]], 'resources'],
        ];
    }

    #[Test]
    public function it_accepts_a_list_at_its_limit(): void
    {
        config(['rest.limits.filters' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['field' => 'views', 'value' => 1],
            ['field' => 'views', 'value' => 1],
        ]]]);

        $response->assertOk();
    }

    #[Test]
    #[DataProvider('ranges')]
    public function it_refuses_the_values_of_a_range_over_their_limit(string $operator): void
    {
        config(['rest.limits.values' => 2]);
        $limit = 'The search.filters.0.value field must not have more than 2 items.';

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['field' => 'views', 'operator' => $operator, 'value' => [1, 2, 'many']],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $limit.' (and 1 more error)', 'errors' => [
            'search.filters.0.value' => [$limit, 'The search.filters.0.value field must contain 2 items.'],
        ]]);
    }

    /** @return array<string, array{string}> */
    public static function ranges(): array
    {
        return ['between' => ['between'], 'not between' => ['not between']];
    }

    #[Test]
    public function it_applies_the_default_limit_of_filters(): void
    {
        $filter = ['field' => 'views', 'value' => 1];
        $message = 'The search.filters field must not have more than 100 items.';

        $this->postJson('/api/posts/search', ['search' => ['filters' => array_fill(0, 101, $filter)]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.filters' => [$message]]]);

        $this->postJson('/api/posts/search', ['search' => ['filters' => array_fill(0, 100, $filter)]])->assertOk();
    }

    #[Test]
    public function it_accepts_every_gate_at_the_default_limit(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => [
            'gates' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
        ]]);

        $response->assertOk();
    }

    #[Test]
    public function it_reads_no_entry_of_a_list_over_its_limit(): void
    {
        $message = 'The search.filters field must not have more than 100 items.';

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => array_fill(0, 5000, ['field' => 'secret'])]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters' => [$message]]]);
    }

    #[Test]
    public function it_reports_a_relation_list_in_the_order_of_the_payload(): void
    {
        config(['rest.limits.relation_entries' => 2]);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            [
                'operation' => 'create',
                'attributes' => ['name' => 'Lighting'],
                'relations' => ['products' => self::three(['operation' => 'secret'])],
            ],
            ['attributes' => ['name' => 'Desks']],
        ]]);

        $response->assertUnprocessable();
        $this->assertSame(['mutate.0.relations.products', 'mutate.1.operation'], array_keys((array) $response->json('errors')));
        $response->assertJsonPath('message', 'The mutate.0.relations.products field must not have more than 2 items. (and 1 more error)');
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<array<string, mixed>>
     */
    private static function three(array $entry): array
    {
        return [$entry, $entry, $entry];
    }
}
