<?php

namespace Lomkit\Rest\Tests\Unit\Query;

use Lomkit\Rest\Query\QueryPart;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;

class QueryPartTest extends TestCase
{
    #[Test]
    public function it_marks_the_query_parts_internal(): void
    {
        $this->assertStringContainsString('@internal', (string) (new ReflectionEnum(QueryPart::class))->getDocComment());
    }

    #[Test]
    public function it_lists_only_the_parts_a_restriction_guard_refuses(): void
    {
        $this->assertSame(
            ['Join', 'Order', 'Limit', 'Offset', 'Columns', 'Distinct', 'Lock', 'Group', 'Having', 'Union', 'EagerLoad', 'From', 'Callback', 'AfterCallback', 'Scope'],
            array_column(QueryPart::cases(), 'name'),
        );
        $this->assertFalse(class_exists('Lomkit\Rest\Query\OuterQueryShape'));
    }
}
