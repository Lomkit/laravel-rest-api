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
}
