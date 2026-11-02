<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Violation;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NodeBudgetTest extends TestCase
{
    #[Test]
    public function it_opens_nothing_under_a_list_over_its_maximum(): void
    {
        $budget = new NodeBudget;

        $this->assertFalse($budget->admit('items', 3, 2, false));
        $this->assertTrue($budget->refused('items'));
        $this->assertSame([], $budget->open(['items.*' => ['integer']]));

        $rules = $budget->applyTo(['items' => ['array']]);

        $this->assertSame(['items'], array_keys($rules));
        $this->assertRejected(Violation::tooMany('items', 2), $rules['items'][0]);
        $this->assertSame('array', $rules['items'][1]);
    }

    #[Test]
    public function it_opens_a_list_at_its_maximum(): void
    {
        $budget = new NodeBudget;

        $this->assertTrue($budget->admit('items', 2, 2, false));
        $this->assertFalse($budget->refused('items'));
        $this->assertSame(['items.*' => ['integer']], $budget->open(['items.*' => ['integer']]));
        $this->assertSame(['items' => ['array']], $budget->applyTo(['items' => ['array']]));
    }

    #[Test]
    public function it_refuses_the_list_that_goes_over_the_total_of_entries(): void
    {
        $budget = new NodeBudget(3);

        $this->assertTrue($budget->admit('a', 2, 10, false));
        $this->assertFalse($budget->admit('b', 2, 10, false));
        $this->assertSame(['a.*' => ['integer']], $budget->open(['a.*' => ['integer'], 'b.*' => ['integer']]));

        $rules = $budget->applyTo(['a' => ['array'], 'b' => ['array']]);

        $this->assertSame(['array'], $rules['a']);
        $this->assertRejected(Violation::tooManyEntries('b', 3), $rules['b'][0]);
        $this->assertSame('array', $rules['b'][1]);
    }

    #[Test]
    public function it_reports_each_total_once(): void
    {
        $budget = new NodeBudget(2);

        $this->assertTrue($budget->admit('a', 2, 10, false));
        $this->assertFalse($budget->admit('b', 1, 10, false));
        $this->assertFalse($budget->admit('c', 1, 10, false));
        $this->assertTrue($budget->refused('b'));
        $this->assertTrue($budget->refused('c'));

        $rules = $budget->applyTo(['a' => ['array'], 'b' => ['array'], 'c' => ['array']]);

        $this->assertSame(['array'], $rules['a']);
        $this->assertRejected(Violation::tooManyEntries('b', 2), $rules['b'][0]);
        $this->assertSame(['array'], $rules['c']);
    }

    #[Test]
    public function it_counts_filters_toward_their_own_total(): void
    {
        $budget = new NodeBudget(10, 2);

        $this->assertFalse($budget->admit('f', 3, 10, true));
        $this->assertTrue($budget->admit('s', 3, 10, false));
        $this->assertTrue($budget->refused('f'));
        $this->assertFalse($budget->refused('s'));

        $rules = $budget->applyTo(['f' => ['array'], 's' => ['array']]);

        $this->assertRejected(Violation::tooManyFilters('f', 2), $rules['f'][0]);
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

    private function assertRejected(Violation $violation, mixed $rule): void
    {
        $this->assertInstanceOf(Rejected::class, $rule);
        $this->assertEquals($violation, $rule->violation);
    }
}
