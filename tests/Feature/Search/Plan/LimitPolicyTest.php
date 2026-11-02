<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use LogicException;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class LimitPolicyTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    #[DataProvider('policies')]
    public function it_applies_its_limit_to_a_query(LimitPolicy $policy, string $sql): void
    {
        $query = Post::query();

        $policy->apply($query);

        $this->assertSame($sql, str_replace(['"', '`'], '', $query->toRawSql()));
    }

    /** @return iterable<string, array{LimitPolicy, string}> */
    public static function policies(): iterable
    {
        yield 'an explicit limit' => [LimitPolicy::explicit(10), 'select * from posts limit 10'];
        yield 'no explicit limit' => [LimitPolicy::explicit(null), 'select * from posts'];
        yield 'no limit' => [LimitPolicy::none(), 'select * from posts'];
        yield 'a page' => [LimitPolicy::paged(25, 2), 'select * from posts'];
    }

    #[Test]
    public function it_gives_the_size_and_number_of_its_page(): void
    {
        $policy = LimitPolicy::paged(25, 2);

        $this->assertSame([25, 2], [$policy->perPage(), $policy->page()]);
    }

    #[Test]
    public function it_refuses_the_size_of_a_page_without_paging(): void
    {
        $this->expectExceptionObject(new LogicException('The plan of this search is not paged.'));

        LimitPolicy::explicit(10)->perPage();
    }

    #[Test]
    public function it_refuses_the_number_of_a_page_without_paging(): void
    {
        $this->expectExceptionObject(new LogicException('The plan of this search is not paged.'));

        LimitPolicy::none()->page();
    }
}
