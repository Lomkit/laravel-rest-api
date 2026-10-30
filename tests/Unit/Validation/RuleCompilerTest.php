<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Closure;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleCompilerTest extends TestCase
{
    /** @var list<array{string, mixed}> */
    private array $visits = [];

    #[Test]
    public function it_drops_a_sometimes_key_that_is_absent(): void
    {
        $this->assertSame([], RuleCompiler::compile(['limit' => ['sometimes', 'integer']], [])->rules);
    }

    #[Test]
    public function it_strips_sometimes_from_a_key_that_is_present(): void
    {
        $this->assertSame(['limit' => ['integer']], RuleCompiler::compile(['limit' => 'sometimes|integer'], ['limit' => 'ten'])->rules);
    }

    #[Test]
    public function it_resolves_sometimes_against_every_rule_of_a_key(): void
    {
        $rules = ['items.0.price' => ['required'], 'items.*.price' => ['sometimes', 'integer']];

        $this->assertSame([], RuleCompiler::compile($rules, ['items' => [[]]])->rules);
        $this->assertSame(['items.0.price' => ['required', 'integer']], RuleCompiler::compile($rules, ['items' => [['price' => 3]]])->rules);
    }

    #[Test]
    public function it_compiles_the_node_of_an_entry_whose_key_holds_a_dot(): void
    {
        $compiled = RuleCompiler::compile(['items.*' => $this->limitNode()], ['items' => ['desk.lamp' => ['limit' => 2]]]);

        $this->assertSame([['items.desk\.lamp', ['limit' => 2]]], $this->visits);
        $this->assertSame(['items.desk\.lamp.limit' => ['integer']], $compiled->rules);
    }

    #[Test]
    public function it_compiles_a_node_with_the_nested_value_and_not_a_root_key_holding_its_path(): void
    {
        $compiled = RuleCompiler::compile(['search.filters' => [$this->limitNode()]], ['search' => ['filters' => ['limit' => 1]], 'search.filters' => 'boom']);

        $this->assertSame([['search.filters', ['limit' => 1]]], $this->visits);
        $this->assertSame(['search.filters.limit' => ['integer']], $compiled->rules);
    }

    #[Test]
    public function it_compiles_a_node_with_the_value_of_its_key(): void
    {
        $compiled = RuleCompiler::compile(['search' => ['array', $this->limitNode()]], ['search' => ['limit' => 10]]);

        $this->assertSame([['search', ['limit' => 10]]], $this->visits);
        $this->assertSame(['search' => ['array'], 'search.limit' => ['integer']], $compiled->rules);
    }

    #[Test]
    public function it_skips_the_nodes_of_an_absent_key(): void
    {
        $this->assertSame(['search' => ['array']], RuleCompiler::compile(['search' => ['array', $this->limitNode()]], [])->rules);
        $this->assertSame([], $this->visits);
    }

    #[Test]
    public function it_skips_the_nodes_of_an_empty_string(): void
    {
        $this->assertSame(['search' => ['array']], RuleCompiler::compile(['search' => ['array', $this->limitNode()]], ['search' => ' '])->rules);
        $this->assertSame([], $this->visits);
    }

    #[Test]
    public function it_skips_the_nodes_of_a_null_value_under_nullable(): void
    {
        $this->assertSame(['search' => ['nullable']], RuleCompiler::compile(['search' => ['nullable', $this->limitNode()]], ['search' => null])->rules);
        $this->assertSame([], $this->visits);
    }

    #[Test]
    public function it_requires_an_array_where_a_node_receives_another_value(): void
    {
        $this->assertSame(['search' => ['array']], RuleCompiler::compile(['search' => [$this->limitNode()]], ['search' => 'boom'])->rules);
        $this->assertSame(['search' => ['array']], RuleCompiler::compile(['search' => [$this->limitNode()]], ['search' => null])->rules);
        $this->assertSame(['search' => ['array']], RuleCompiler::compile(['search' => ['sometimes', $this->limitNode()]], ['search' => 'boom'])->rules);
        $this->assertSame([], $this->visits);
    }

    #[Test]
    public function it_requires_an_array_once_when_the_key_already_does(): void
    {
        $this->assertSame(['search' => ['array']], RuleCompiler::compile(['search' => ['array', $this->limitNode()]], ['search' => 'boom'])->rules);
    }

    #[Test]
    public function it_requires_an_array_on_a_list_entry_that_is_not_an_object(): void
    {
        $compiled = RuleCompiler::compile(['items' => ['array'], 'items.*' => $this->limitNode()], ['items' => ['Lamp', ['limit' => 2]]]);

        $this->assertSame(['items' => ['array'], 'items.0' => ['array'], 'items.1.limit' => ['integer']], $compiled->rules);
        $this->assertSame([['items.1', ['limit' => 2]]], $this->visits);
    }

    #[Test]
    public function it_inserts_the_rules_of_a_node_right_after_its_key(): void
    {
        $filter = $this->node(fn (string $attribute): array => [$attribute.'.field' => ['required']]);
        $search = $this->node(fn (string $attribute): array => [$attribute.'.filters.*' => $filter, $attribute.'.limit' => ['integer']]);

        $compiled = RuleCompiler::compile(
            ['search' => ['array', $search], 'search.page' => ['integer']],
            ['search' => ['filters' => [['field' => 'name'], []], 'page' => 2]],
        );

        $this->assertSame(['search', 'search.limit', 'search.filters.0.field', 'search.filters.1.field', 'search.page'], array_keys($compiled->rules));
    }

    #[Test]
    public function it_labels_the_rules_a_node_adds_as_expanded(): void
    {
        $entry = $this->compilingNode(function (RuleCompiler $into, string $attribute): void {
            $into->addExpanded([$attribute.'.unitPrice' => ['required']]);
        });

        $compiled = RuleCompiler::compile(['lineItem' => $entry], ['lineItem' => ['quantity' => 2]]);

        $this->assertSame(['lineItem.unitPrice' => ['required']], $compiled->rules);
        $this->assertSame(['lineItem.unitPrice' => 'lineItem.unitPrice'], $compiled->labels);
    }

    #[Test]
    public function it_validates_the_values_a_node_projects(): void
    {
        $inputs = $this->compilingNode(function (RuleCompiler $into, string $attribute): void {
            $into->project($attribute.'.limit', 25);
            $into->label($attribute.'.limit', 'limit');
            $into->add([$attribute.'.limit' => ['sometimes', 'integer'], $attribute.'.page' => ['sometimes', 'integer']]);
        });

        $compiled = RuleCompiler::compile(['fields' => $inputs], ['fields' => [['name' => 'limit', 'value' => 25]]]);

        $this->assertSame(['fields.limit' => ['integer']], $compiled->rules);
        $this->assertSame(['fields.limit' => 'limit'], $compiled->labels);
        $this->assertSame(['fields' => [['name' => 'limit', 'value' => 25], 'limit' => 25]], $compiled->data(['fields' => [['name' => 'limit', 'value' => 25]]]));
    }

    private function limitNode(): RestRule
    {
        return $this->node(fn (string $attribute): array => [$attribute.'.limit' => ['integer']]);
    }

    /** @param Closure(string, mixed): array<string, mixed> $rules */
    private function node(Closure $rules): RestRule
    {
        $visit = function (string $attribute, mixed $value): void {
            $this->visits[] = [$attribute, $value];
        };

        return new class($rules, $visit) extends RestRule
        {
            public function __construct(private readonly Closure $rules, private readonly Closure $visit) {}

            #[\Override]
            public function buildValidationRules(string $attribute, mixed $value): array
            {
                ($this->visit)($attribute, $value);

                return ($this->rules)($attribute, $value);
            }
        };
    }

    /** @param Closure(RuleCompiler, string, mixed): void $compile */
    private function compilingNode(Closure $compile): RestRule
    {
        return new class($compile) extends RestRule
        {
            public function __construct(private readonly Closure $compile) {}

            #[\Override]
            public function compile(RuleCompiler $into, string $attribute, mixed $value): void
            {
                ($this->compile)($into, $attribute, $value);
            }

            #[\Override]
            public function buildValidationRules(string $attribute, mixed $value): array
            {
                return [];
            }
        };
    }
}
