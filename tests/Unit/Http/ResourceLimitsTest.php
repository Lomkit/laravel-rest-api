<?php

namespace Lomkit\Rest\Tests\Unit\Http;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\ResourceLimits;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPageBookResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceLimitsTest extends TestCase
{
    #[Test]
    public function it_reads_the_limits_and_the_defaults_of_a_resource(): void
    {
        $limits = ResourceLimits::of(new BookResource, [10, 25, 50]);

        $this->assertSame([[10, 25, 50], 50, 10], [$limits->allowed, $limits->defaultLimit, $limits->includeLimit]);
    }

    #[Test]
    public function it_refuses_a_default_limit_that_the_limits_do_not_hold(): void
    {
        $this->expectExceptionObject(new InvalidResourceException('The resource ['.UnlistedPageBookResource::class.'] sets $defaultLimit to 32, which is not one of its limits() [10, 25, 50]. Return 32 from limits(), or set $defaultLimit to one of them.'));

        ResourceLimits::of(new UnlistedPageBookResource, [10, 25, 50]);
    }

    #[Test]
    public function it_refuses_an_include_limit_that_the_limits_do_not_hold(): void
    {
        $this->expectExceptionObject(new InvalidResourceException('The resource ['.BookResource::class.'] sets $includeLimit to 10, which is not one of its limits() [25, 50]. Return 10 from limits(), or set $includeLimit to one of them.'));

        ResourceLimits::of(new BookResource, [25, 50]);
    }

    #[Test]
    public function it_reads_the_maximum_total_of_a_resource(): void
    {
        $resource = new BookResource;
        $resource->maxTotal = 50;

        $this->assertSame([null, 50], [ResourceLimits::of(new BookResource, [10, 25, 50])->maxTotal, ResourceLimits::of($resource, [10, 25, 50])->maxTotal]);
    }

    /** @param list<int> $allowed */
    #[Test]
    #[DataProvider('largestPages')]
    public function it_bounds_the_page_so_that_its_offset_stays_an_integer(array $allowed, int $maxPage): void
    {
        $resource = new class extends BookResource
        {
            public int $defaultLimit = 1;

            public int $includeLimit = 1;
        };

        $this->assertSame($maxPage, ResourceLimits::of($resource, $allowed)->maxPage);
    }

    /** @return array<string, array{list<int>, int}> */
    public static function largestPages(): array
    {
        return [
            'the default limits' => [[1, 10, 25, 50], 184467440737095517],
            'a larger limit' => [[1, 300], 30744573456182587],
            'a limit that divides the largest integer' => [[1, 511], 18049651735527938],
            'a single limit of one' => [[1], 9223372036854775807],
        ];
    }

    #[Test]
    #[DataProvider('maxTotalsBelowTheLargestLimit')]
    public function it_refuses_a_maximum_total_below_the_largest_limit(int $maxTotal): void
    {
        $resource = new BookResource;
        $resource->maxTotal = $maxTotal;

        $this->expectExceptionObject(new InvalidResourceException('The resource ['.BookResource::class."] sets \$maxTotal to {$maxTotal}, which is below its largest limit 50. Set \$maxTotal to 50 or more, or to null to count every row."));

        ResourceLimits::of($resource, [10, 25, 50]);
    }

    /** @return array<string, array{int}> */
    public static function maxTotalsBelowTheLargestLimit(): array
    {
        return [
            'zero' => [0],
            'one below the largest limit' => [49],
        ];
    }
}
