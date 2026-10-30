<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\ArrayWithKey;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleCompilerWildcardsTest extends TestCase
{
    #[Test]
    public function it_labels_expanded_keys_that_are_not_displayed_as_written(): void
    {
        $compiled = RuleCompiler::compile(
            ['lineItems.*.unitPrice' => ['required'], 'items.*.name' => ['string'], 'orderNote' => ['string']],
            ['lineItems' => [['quantity' => 2]], 'items' => [['name' => 'Lamp']], 'orderNote' => 'Fragile'],
        );

        $this->assertSame(['lineItems.0.unitPrice' => 'lineItems.0.unitPrice'], $compiled->labels);
    }

    #[Test]
    public function it_keeps_distinct_on_the_wildcard_key(): void
    {
        $compiled = RuleCompiler::compile(['tags.*' => ['sometimes', 'string', 'distinct:strict']], ['tags' => ['red', 'blue']]);

        $this->assertSame(['tags.0' => ['string'], 'tags.1' => ['string'], 'tags.*' => ['sometimes', 'distinct:strict']], $compiled->rules);
    }

    #[Test]
    public function it_keeps_the_rules_that_follow_distinct_on_the_wildcard_key(): void
    {
        $compiled = RuleCompiler::compile(
            ['resources' => ['array'], 'resources.*' => ['distinct', 'integer'], 'names.*' => ['required', 'distinct', 'in:Lamp,Desk']],
            ['resources' => [1, 2], 'names' => ['Lamp']],
        );

        $this->assertSame(
            ['resources' => ['array'], 'resources.0' => [], 'resources.1' => [], 'names.0' => ['required'], 'resources.*' => ['distinct', 'integer'], 'names.*' => ['distinct', 'in:Lamp,Desk']],
            $compiled->rules,
        );
    }

    #[Test]
    public function it_keeps_compilable_rules_on_the_wildcard_key(): void
    {
        $each = Rule::forEach(fn (): array => ['string']);

        $compiled = RuleCompiler::compile(['tags.*' => [$each]], ['tags' => ['red']]);

        $this->assertSame(['tags.0' => [], 'tags.*' => [$each]], $compiled->rules);
        $this->assertInstanceOf(NestedRules::class, $compiled->rules['tags.*'][0]);
    }

    #[Test]
    public function it_fills_the_wildcards_of_dependent_parameters(): void
    {
        $compiled = RuleCompiler::compile(['items.*.end' => ['gte:items.*.start', 'in:*']], ['items' => [['start' => 1, 'end' => 3]]]);

        $this->assertSame(['items.0.end' => ['gte:items.0.start', 'in:*']], $compiled->rules);
    }

    #[Test]
    public function it_labels_an_expanded_key_that_only_holds_nodes(): void
    {
        $node = new class extends RestRule
        {
            #[\Override]
            public function buildValidationRules(string $attribute, mixed $value): array
            {
                return [];
            }
        };

        $compiled = RuleCompiler::compile(['relations.*' => $node, 'relations.lineItems' => ['prohibited']], ['relations' => ['lineItems' => []]]);

        $this->assertSame(['relations.lineItems' => ['prohibited']], $compiled->rules);
        $this->assertSame(['relations.lineItems' => 'relations.lineItems'], $compiled->labels);
    }

    #[Test]
    public function it_rejects_the_entries_whose_key_a_rule_cannot_name(): void
    {
        $compiled = RuleCompiler::compile(
            ['filters' => ['array'], 'filters.*.field' => ['required']],
            ['filters' => ['*' => ['field' => 'name'], 'price' => ['field' => 'price']]],
        );

        $this->assertEquals(['filters' => ['array', new ArrayWithKey(['price'])], 'filters.price.field' => ['required']], $compiled->rules);
    }
}
