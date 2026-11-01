<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Rules\MaxItems;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\RuleCompiler;
use Lomkit\Rest\Validation\Violation;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NodeBudgetTest extends TestCase
{
    #[Test]
    public function it_compiles_nothing_under_a_list_over_its_maximum(): void
    {
        $rules = RuleCompiler::compile(['items' => ['array', new MaxItems(2)], 'items.*' => ['integer']], ['items' => [1, 'x', 3]], $budget = new NodeBudget)->rules;

        $this->assertSame(['items'], array_keys($rules));
        $this->assertRejected(Violation::tooMany('items', 2), $rules['items'][0]);
        $this->assertSame('array', $rules['items'][1]);
        $this->assertTrue($budget->refused('items'));
    }

    #[Test]
    public function it_compiles_a_list_at_its_maximum(): void
    {
        $rules = RuleCompiler::compile(['items' => ['array', new MaxItems(2)], 'items.*' => ['integer']], ['items' => [1, 2]])->rules;

        $this->assertSame(['items', 'items.0', 'items.1'], array_keys($rules));
    }

    #[Test]
    public function it_refuses_the_list_that_goes_over_the_total_of_entries(): void
    {
        $rules = RuleCompiler::compile($this->lists(['a', 'b'], new MaxItems(10)), ['a' => [1, 2], 'b' => [3, 4]], new NodeBudget(3))->rules;

        $this->assertSame(['a', 'b', 'a.0', 'a.1'], array_keys($rules));
        $this->assertRejected(Violation::tooManyEntries('b', 3), $rules['b'][0]);
    }

    #[Test]
    public function it_reports_each_total_once(): void
    {
        $budget = new NodeBudget(2);
        $rules = RuleCompiler::compile($this->lists(['a', 'b', 'c'], new MaxItems(10)), ['a' => [1, 2], 'b' => [3], 'c' => [4]], $budget)->rules;

        $this->assertTrue($budget->refused('b'));
        $this->assertTrue($budget->refused('c'));
        $this->assertSame(['array'], $rules['a']);
        $this->assertRejected(Violation::tooManyEntries('b', 2), $rules['b'][0]);
        $this->assertSame(['array'], $rules['c']);
    }

    #[Test]
    public function it_counts_filters_toward_their_own_total(): void
    {
        $budget = new NodeBudget(10, 2);
        $rules = RuleCompiler::compile(
            ['f' => ['array', MaxItems::filters(10)], 's' => ['array', new MaxItems(10)]],
            ['f' => [1, 2, 3], 's' => [1, 2, 3]],
            $budget,
        )->rules;

        $this->assertTrue($budget->refused('f'));
        $this->assertRejected(Violation::tooManyFilters('f', 2), $rules['f'][0]);
        $this->assertFalse($budget->refused('s'));
        $this->assertSame(['array'], $rules['s']);
    }

    #[Test]
    public function it_puts_the_rejection_first_in_the_rules_of_its_key(): void
    {
        $violations = new Violations;
        $violations->add(new Violation('b', 'validation.list'));
        $violations->add(new Violation('c', 'validation.distinct'));
        $violations->add(new Violation('a', 'validation.max.array', ['max' => '1']));

        $rules = $violations->applyTo(['a' => ['array'], 'b' => []]);

        $this->assertSame(['a', 'b', 'c'], array_keys($rules));
        $this->assertCount(2, $rules['a']);
        $this->assertRejected(new Violation('a', 'validation.max.array', ['max' => '1']), $rules['a'][0]);
        $this->assertSame('array', $rules['a'][1]);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, list<mixed>>
     */
    private function lists(array $keys, MaxItems $max): array
    {
        $rules = [];

        foreach ($keys as $key) {
            $rules[$key] = ['array', $max];
            $rules[$key.'.*'] = ['integer'];
        }

        return $rules;
    }

    private function assertRejected(Violation $violation, mixed $rule): void
    {
        $this->assertInstanceOf(Rejected::class, $rule);
        $this->assertEquals($violation, $rule->violation);
    }
}
