<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\CompiledRules;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Violation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CompiledRulesTest extends TestCase
{
    #[Test]
    public function it_adds_the_projected_values_to_the_input(): void
    {
        $compiled = new CompiledRules([], [], ['fields.limit' => 25, 'search.fields.status' => 'draft']);

        $this->assertSame(
            ['fields' => [['name' => 'limit', 'value' => 25], 'limit' => 25], 'search' => ['fields' => ['status' => 'draft']]],
            $compiled->data(['fields' => [['name' => 'limit', 'value' => 25]]]),
        );
    }

    #[Test]
    public function it_gives_the_application_attribute_names_precedence_over_the_labels(): void
    {
        $compiled = new CompiledRules([], [
            'fields.min_views' => 'min views',
            'mutate.0.relations.lineItems.0.operation' => 'mutate.0.relations.lineItems.0.operation',
            'mutate.0.relations.lineItems.1.operation' => 'mutate.0.relations.lineItems.1.operation',
            'fields.status' => 'status',
        ], []);

        $this->assertSame(
            [
                'fields.min_views' => 'minimum views',
                'mutate.*.relations.lineItems.*.operation' => 'line operation',
                'fields.status' => 'status',
            ],
            $compiled->attributeNames(['fields.min_views' => 'minimum views', 'mutate.*.relations.lineItems.*.operation' => 'line operation']),
        );
    }

    #[Test]
    public function it_leaves_out_the_labels_of_keys_that_a_translation_names(): void
    {
        $compiled = new CompiledRules([], [
            'items.0.unit_price' => 'items.0.unit_price',
            'lineItems.0.unitPrice' => 'lineItems.0.unitPrice',
            'fields.status' => 'status',
            'fields.min_views' => 'min views',
        ], []);

        $this->assertSame(
            ['fields.min_views' => 'minimum views', 'lineItems.0.unitPrice' => 'lineItems.0.unitPrice'],
            $compiled->attributeNames(['fields.min_views' => 'minimum views'], ['items.*.unit_price' => 'unit price', 'fields.status' => 'publication status']),
        );
    }

    #[Test]
    public function it_merges_the_parsed_rules_with_the_rules_of_the_request(): void
    {
        $check = new Check(new class extends Resource {}, 'viewAny', Model::class, 'search.filters.0');
        $parsed = $this->parsed(['items' => ['array']]);
        $parsed->label('search.filters.0.sort_by', 'search.filters.0.sort_by');
        $parsed->project('search.instructions.0.fields.status', 'draft');
        $parsed->reach($check);

        $compiled = CompiledRules::of($parsed, ['items' => 'min:1', 'name' => 'string|max:3'], new NodeBudget);

        $this->assertSame(['items' => ['array', 'min:1'], 'name' => ['string', 'max:3']], $compiled->rules);
        $this->assertSame(['search.filters.0.sort_by' => 'search.filters.0.sort_by'], $compiled->labels);
        $this->assertSame(['search.instructions.0.fields.status' => 'draft'], $compiled->projections);
        $this->assertSame([$check], $compiled->reach);
    }

    #[Test]
    public function it_keeps_an_application_wildcard_key_for_laravel(): void
    {
        $compiled = CompiledRules::of($this->parsed(['items' => ['array']]), ['items.*' => ['integer']], new NodeBudget);

        $this->assertSame(['items' => ['array'], 'items.*' => ['integer']], $compiled->rules);
    }

    #[Test]
    public function it_puts_the_application_wildcard_keys_after_the_explicit_keys(): void
    {
        $compiled = CompiledRules::of($this->parsed(['items.0' => ['integer']]), ['items.*' => ['max:5'], 'name' => 'string'], new NodeBudget);

        $this->assertSame(['name', 'items.0', 'items.*'], array_keys($compiled->rules));
    }

    #[Test]
    public function it_leaves_out_an_application_wildcard_key_under_a_refused_list(): void
    {
        $budget = new NodeBudget;
        $budget->close('items');

        $compiled = CompiledRules::of($this->parsed(['items' => ['array']]), ['items.*' => ['integer']], $budget);

        $this->assertSame(['items' => ['array']], $compiled->rules);
    }

    #[Test]
    public function it_puts_the_rejections_of_the_budget_first(): void
    {
        $budget = new NodeBudget;
        $budget->admit('items', 3, 2, false);

        $compiled = CompiledRules::of($this->parsed(['items' => ['array']]), [], $budget);

        $this->assertInstanceOf(Rejected::class, $compiled->rules['items'][0]);
        $this->assertEquals(Violation::tooMany('items', 2), $compiled->rules['items'][0]->violation);
        $this->assertSame('array', $compiled->rules['items'][1]);
    }

    /** @param array<string, list<mixed>> $rules */
    private function parsed(array $rules): ParsedRules
    {
        $parsed = new ParsedRules;

        foreach ($rules as $key => $list) {
            $parsed->add($key, $list);
        }

        return $parsed;
    }
}
