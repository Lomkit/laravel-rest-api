<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Routing\Redirector;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\HighlightingPostController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class NameListRulesTest extends PackageTestCase
{
    private const array PRODUCT_FIELDS = ['id', 'category_id', 'name', 'price'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[Test]
    public function it_checks_selected_fields_against_a_one_of_rule(): void
    {
        $rules = $this->searchRequest('/api/products/search', ['selects' => [['field' => 'name']]])->rules();

        $this->assertSame(self::PRODUCT_FIELDS, $this->oneOf($rules['search.selects.0.field'])->values());
    }

    #[Test]
    public function it_checks_sorted_fields_against_a_one_of_rule(): void
    {
        $rules = $this->searchRequest('/api/products/search', ['sorts' => [['field' => 'name']]])->rules();

        $this->assertSame(self::PRODUCT_FIELDS, $this->oneOf($rules['search.sorts.0.field'])->values());
    }

    #[Test]
    public function it_checks_aggregated_fields_against_a_one_of_rule(): void
    {
        $rules = $this->searchRequest('/api/products/search', ['aggregates' => [['relation' => 'category', 'type' => 'min']]])->rules();

        $this->assertSame(['id', 'name'], $this->oneOf($rules['search.aggregates.0.field'])->values());
    }

    #[Test]
    public function it_checks_no_aggregated_field_name_without_a_known_type(): void
    {
        $missing = $this->searchRequest('/api/products/search', ['aggregates' => [['relation' => 'category']]])->rules();
        $unknown = $this->searchRequest('/api/products/search', ['aggregates' => [['relation' => 'category', 'type' => 'median']]])->rules();

        $this->assertSame([], array_filter($missing['search.aggregates.0.field'], fn (mixed $rule): bool => $rule instanceof OneOf));
        $this->assertSame([], array_filter($unknown['search.aggregates.0.field'], fn (mixed $rule): bool => $rule instanceof OneOf));
    }

    #[Test]
    public function it_checks_instruction_names_and_their_field_names_against_a_one_of_rule(): void
    {
        $rules = $this->searchRequest('/api/products/search', ['instructions' => [
            ['name' => 'priced-at', 'fields' => [['name' => 'price', 'value' => 39]]],
        ]])->rules();

        $this->assertSame(['priced-at'], $this->oneOf($rules['search.instructions.0.name'])->values());
        $this->assertSame(['price'], $this->oneOf($rules['search.instructions.0.fields.0.name'])->values());
    }

    #[Test]
    public function it_checks_scope_names_against_a_one_of_rule(): void
    {
        $rules = $this->searchRequest('/api/posts/search', ['scopes' => [['name' => 'popular']]])->rules();

        $this->assertSame(['publishedSince', 'popular', 'viewedBetween', 'featured', 'reaching'], $this->oneOf($rules['search.scopes.0.name'])->values());
    }

    #[Test]
    public function it_checks_action_field_names_against_a_one_of_rule(): void
    {
        $request = $this->operateRequest('/api/posts/actions/publish-posts', ['fields' => [['name' => 'at']]]);

        $this->assertSame(['at', 'notify'], $this->oneOf($request->rules()['fields.0.name'])->values());
    }

    #[Test]
    public function it_keeps_the_field_names_of_an_action_apart_from_those_of_an_instruction(): void
    {
        Rest::resource('highlighting-posts', HighlightingPostController::class)->register();
        $request = $this->operateRequest('/highlighting-posts/actions/highlight-posts', [
            'search' => ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'featured']]]]],
            'fields' => [['name' => 'label']],
        ]);

        $rules = $request->rules();

        $this->assertSame(['label'], $this->oneOf($rules['fields.0.name'])->values());
        $this->assertSame(['featured', 'min_views'], $this->oneOf($rules['search.instructions.0.fields.0.name'])->values());
    }

    #[Test]
    public function it_shares_one_list_between_the_entries_of_a_search(): void
    {
        $rules = $this->searchRequest('/api/products/search', ['sorts' => [['field' => 'name'], ['field' => 'price']]])->rules();

        $this->assertSame($this->oneOf($rules['search.sorts.0.field']), $this->oneOf($rules['search.sorts.1.field']));
    }

    #[Test]
    public function it_returns_the_search_rules_on_explicit_keys(): void
    {
        $rules = $this->searchRequest('/api/products/search', [
            'filters' => [['nested' => [['field' => 'name', 'value' => 'Desk lamp']]]],
            'sorts' => [['field' => 'name'], ['field' => 'price', 'direction' => 'desc']],
            'includes' => [['relation' => 'category', 'filters' => [['field' => 'name', 'value' => 'Lighting']]]],
            'instructions' => [['name' => 'priced-at', 'fields' => [['name' => 'price', 'value' => 39]]]],
        ])->rules();

        $this->assertContains('search.includes.0.filters.0.field', array_keys($rules));
        $this->assertSame([], array_values(array_filter(array_keys($rules), fn (string $key): bool => str_contains($key, '*'))));
    }

    #[Test]
    public function it_parses_the_search_once_per_request(): void
    {
        config(['rest.limits.totals.entries' => 2]);
        $request = $this->searchRequest('/api/products/search', ['sorts' => [['field' => 'name'], ['field' => 'price']]]);

        $first = $request->rules();
        $second = $request->rules();
        $request->validateResolved();

        $this->assertSame($first, $second);
        $this->assertArrayHasKey('search.sorts.1.field', $second);
        $this->assertSame(['search' => ['sorts' => [['field' => 'name'], ['field' => 'price']]]], $request->validated());
    }

    /** @param array<string, mixed> $body */
    private function operateRequest(string $uri, array $body): OperateRequest
    {
        $request = OperateRequest::create($uri, 'POST', $body)->setContainer($this->app);
        $route = $this->app['router']->getRoutes()->match($request);
        $route->getController();
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    /** @param array<string, mixed> $search */
    private function searchRequest(string $uri, array $search): SearchRequest
    {
        $request = SearchRequest::create($uri, 'POST', ['search' => $search])
            ->setContainer($this->app)
            ->setRedirector($this->app->make(Redirector::class));
        $route = $this->app['router']->getRoutes()->match($request);
        $route->getController();
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    /** @param list<mixed> $rules */
    private function oneOf(array $rules): OneOf
    {
        $oneOf = array_values(array_filter($rules, fn (mixed $rule): bool => $rule instanceof OneOf));

        $this->assertCount(1, $oneOf);

        return $oneOf[0];
    }
}
