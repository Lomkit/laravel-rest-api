<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineGrammarTest extends EngineSearchTestCase
{
    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('searchesOutsideTheEngineGrammar')]
    public function it_refuses_what_the_engine_does_not_run_before_searching(string $resource, array $search, array $errors): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));

        $response = $this->search($resource, ['text' => ['value' => 'report'], ...$search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame([], $this->engine->searches);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function searchesOutsideTheEngineGrammar(): array
    {
        return [
            'an or filter' => ['vouchers', ['filters' => [['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 1, 'type' => 'or']]], [
                'search.filters.1.type' => ['The selected search.filters.1.type is invalid.'],
            ]],
            'a group of filters' => ['vouchers', ['filters' => [['nested' => [['field' => 'number', 'value' => 3]]]]], [
                'search.filters.0.field' => ['The search.filters.0.field field is required.'],
                'search.filters.0.nested' => ['The search.filters.0.nested field is prohibited.'],
            ]],
            'a field the engine does not filter' => ['vouchers', ['filters' => [['field' => 'title', 'value' => 'Report alpha']]], [
                'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
            ]],
            'an attribute of the index perimeter' => ['team-vouchers', ['filters' => [['field' => 'team', 'value' => 'blue']]], [
                'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
            ]],
            'a relation path' => ['vouchers', ['filters' => [['field' => 'lines.label', 'value' => 'l1a']]], [
                'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
            ]],
            'a pattern operator' => ['vouchers', ['filters' => [['field' => 'code', 'operator' => 'like', 'value' => 'V-%']]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'a range on a text' => ['vouchers', ['filters' => [['field' => 'code', 'operator' => '>', 'value' => 'V-a']]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'a range on a boolean' => ['vouchers', ['filters' => [['field' => 'active', 'operator' => '<', 'value' => true]]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'a null check' => ['vouchers', ['filters' => [['field' => 'number', 'operator' => 'is null']]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'a null value' => ['vouchers', ['filters' => [['field' => 'number', 'value' => null]]], [
                'search.filters.0.value' => ['The search.filters.0.value field is required.'],
            ]],
            'an empty list' => ['vouchers', ['filters' => [['field' => 'number', 'operator' => 'in', 'value' => []]]], [
                'search.filters.0.value' => ['The search.filters.0.value field must have at least 1 items.'],
            ]],
            'a null in a list' => ['vouchers', ['filters' => [['field' => 'code', 'operator' => 'not in', 'value' => ['V-a', null]]]], [
                'search.filters.0.value.1' => ['The search.filters.0.value.1 field is required.'],
            ]],
            'a value of the wrong type' => ['vouchers', ['filters' => [['field' => 'price', 'value' => 'cheap']]], [
                'search.filters.0.value' => ['The search.filters.0.value field must be a number.'],
            ]],
            'a list member of the wrong type' => ['vouchers', ['filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 2.5]]]], [
                'search.filters.0.value.1' => ['The search.filters.0.value.1 field must be an integer.'],
            ]],
            'a sort the engine does not run' => ['vouchers', ['sorts' => [['field' => 'title']]], [
                'search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.'],
            ]],
            'the engine order in ascending order' => ['vouchers', ['sorts' => [['field' => '@relevance', 'direction' => 'asc']]], [
                'search.sorts.0.direction' => ['The selected search.sorts.0.direction is invalid.'],
            ]],
            'the engine order next to another sort' => ['vouchers', ['sorts' => [['field' => '@relevance'], ['field' => 'price']]], [
                'search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.'],
            ]],
            'a mode the engine does not run' => ['vouchers', ['text' => ['value' => 'report', 'mode' => 'phrase']], [
                'search.text.mode' => ['The selected search.text.mode is invalid.'],
            ]],
            'a scope' => ['vouchers', ['scopes' => [['name' => 'cheap']]], [
                'search.scopes' => ['The search.scopes field is prohibited.'],
            ]],
            'trashed models the index does not keep' => ['vouchers', ['trashed' => 'with'], [
                'search.trashed' => ['The search.trashed field is prohibited.'],
            ]],
            'page zero' => ['vouchers', ['page' => 0], [
                'search.page' => ['The search.page field must be at least 1.'],
            ]],
            'an include filter on an undeclared field' => ['vouchers', ['includes' => [['relation' => 'lines', 'filters' => [['field' => 'created_at', 'value' => '2026-01-01']]]]], [
                'search.includes.0.filters.0.field' => ['The selected search.includes.0.filters.0.field is invalid.'],
            ]],
            'an aggregate without relation' => ['vouchers', ['aggregates' => [['type' => 'count']]], [
                'search.aggregates.0.relation' => ['The search.aggregates.0.relation field is required.'],
            ]],
        ];
    }

    #[Test]
    public function it_refuses_a_number_the_engine_cannot_compare(): void
    {
        $response = $this->call('POST', '/api/vouchers/search', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"search":{"text":{"value":"report"},"filters":[{"field":"price","value":1e400}]}}');

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.value' => ['The search.filters.0.value field holds a value the search engine cannot compare.'],
        ]);
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_validates_a_relation_filter_of_an_engine_search_without_checking_the_relation(): void
    {
        Gate::before(fn (?Authenticatable $user, string $ability, array $arguments): ?bool => ($arguments[0] ?? null) === VoucherLine::class ? false : null);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'lines.label', 'value' => 'l1a']]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.filters.0.field is invalid.',
            'errors' => ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']],
        ]);
    }

    #[Test]
    #[DataProvider('queryBuilderMethods')]
    public function it_refuses_an_include_named_after_a_query_builder_method(string $method): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'includes' => [['relation' => $method]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.includes.0.relation' => ['The relation is not valid or allowed for this resource.'],
        ]);
        $this->assertSame(6, Voucher::withTrashed()->count());
        $this->assertSame([], $this->engine->searches);
    }

    /** @return array<string, array{string}> */
    public static function queryBuilderMethods(): array
    {
        return [
            'truncate' => ['truncate'],
            'delete' => ['delete'],
            'update' => ['update'],
        ];
    }
}
