<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use InvalidArgumentException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Search\Plan\DefaultOrderClause;
use Lomkit\Rest\Tests\Feature\Search\Stubs\EnumOrderedPostResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\SidewaysOrderedPostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DefaultOrderClauseTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_hands_a_sort_direction_case_to_the_query_builder(): void
    {
        $this->assertSame('select * from posts order by posts.views desc', $this->ordered(EnumOrderedPostResource::class));
    }

    #[Test]
    public function it_leaves_an_unknown_direction_to_the_query_builder(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Order direction must be a SortDirection, "asc" or "desc".'));

        $this->ordered(SidewaysOrderedPostResource::class);
    }

    /** @param class-string<Resource> $class */
    private function ordered(string $class): string
    {
        $context = new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);
        $query = Post::query();

        (new DefaultOrderClause($context->resource($class)))->apply($query);

        return str_replace(['"', '`'], '', $query->toRawSql());
    }
}
