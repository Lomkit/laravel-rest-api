<?php

namespace Lomkit\Rest\Tests\Unit\Validation\Search;

use Lomkit\Rest\Validation\Search\RowNode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RowNodeTest extends TestCase
{
    #[Test]
    public function it_multiplies_nested_rows_and_adds_sibling_rows(): void
    {
        $tags = new RowNode('tags', 10, [[]]);
        $labels = new RowNode('labels', 5, [[]]);
        $tickets = new RowNode('tickets', 10, [[$tags, $labels]]);

        $this->assertSame(10 * 10 + 10 * 10 * 10 + 10 * 10 * 5, $tickets->rows(10));
    }

    #[Test]
    public function it_counts_the_branch_with_the_most_rows(): void
    {
        $shelves = new RowNode('shelves', 10, [[]]);
        $loan = new RowNode('loan', 1, [[]]);
        $labelable = new RowNode('labelable', 1, [[$loan], [$shelves], []]);

        $this->assertSame(10 + 100, $labelable->rows(10));
        $this->assertSame([$shelves], $labelable->heaviest(10));
    }

    #[Test]
    public function it_stops_at_the_largest_integer(): void
    {
        $below = new RowNode('below', PHP_INT_MAX, [[]]);
        $node = new RowNode('node', 3, [[$below]]);

        $this->assertSame(PHP_INT_MAX, $node->rows(2));
        $this->assertSame(PHP_INT_MAX, RowNode::plus(PHP_INT_MAX, 1));
        $this->assertSame(PHP_INT_MAX, RowNode::times(PHP_INT_MAX, 2));
        $this->assertSame(0, RowNode::times(0, PHP_INT_MAX));
    }
}
