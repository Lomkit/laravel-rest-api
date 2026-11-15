<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Limits;

use Closure;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\ResourceLimits;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CappedBookLimitsResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstNarrowing;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondNarrowing;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class LimitNarrowingTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::reset();
    }

    #[\Override]
    protected function tearDown(): void
    {
        self::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_keeps_the_limits_without_an_extension(): void
    {
        $this->assertSame([[10, 25, 50], 50, 10, null], self::values($this->limits(BookResource::class)));
    }

    #[Test]
    public function it_keeps_the_limits_with_an_extension_that_narrows_nothing(): void
    {
        $this->assertSame([[10, 25, 50], 50, 10, null], self::values($this->limits(BookResource::class, static function (): void {})));
    }

    #[Test]
    public function it_intersects_the_allowed_sizes(): void
    {
        $limits = $this->limits(BookResource::class, static fn (LimitsContext $context): mixed => $context->allowSizes([10, 50, 100]));

        $this->assertSame([[10, 50], 50, 10, null], self::values($limits));
    }

    #[Test]
    public function it_lowers_the_default_to_the_largest_size_left(): void
    {
        $limits = $this->limits(BookResource::class, static fn (LimitsContext $context): mixed => $context->maxSize(30));

        $this->assertSame([[10, 25], 25, 10, null], self::values($limits));
        $this->assertSame(intdiv(PHP_INT_MAX, 25) + 1, $limits->maxPage);
    }

    #[Test]
    public function it_takes_the_smallest_max_total_and_drops_the_sizes_above_it(): void
    {
        $uncapped = $this->limits(BookResource::class, static fn (LimitsContext $context): mixed => $context->maxTotal(30));
        $capped = $this->limits(CappedBookLimitsResource::class, static fn (LimitsContext $context): mixed => $context->maxTotal(100));
        $lowered = $this->limits(CappedBookLimitsResource::class, static fn (LimitsContext $context): mixed => $context->maxTotal(20));

        $this->assertSame([[10, 25], 25, 10, 30], self::values($uncapped));
        $this->assertSame([[1, 10, 25, 50], 25, 10, 60], self::values($capped));
        $this->assertSame([[1, 10], 10, 10, 20], self::values($lowered));
    }

    #[Test]
    public function it_lowers_the_include_limit_to_an_allowed_size(): void
    {
        $between = $this->limits(CappedBookLimitsResource::class, static fn (LimitsContext $context): mixed => $context->includeLimit(5));
        $above = $this->limits(CappedBookLimitsResource::class, static fn (LimitsContext $context): mixed => $context->includeLimit(30));
        $removed = $this->limits(CappedBookLimitsResource::class, static fn (LimitsContext $context): mixed => $context->allowSizes([1, 25]));

        $this->assertSame([1, 10, 1], [$between->includeLimit, $above->includeLimit, $removed->includeLimit]);
    }

    #[Test]
    public function it_refuses_sizes_all_above_the_default(): void
    {
        $this->expectExceptionObject(InvalidQueryExtensionException::defaultAboveSizes(CappedBookLimitsResource::class, ['first' => FirstNarrowing::class], 25, [50]));

        $this->limits(CappedBookLimitsResource::class, static fn (LimitsContext $context): mixed => $context->allowSizes([50]));
    }

    #[Test]
    public function it_refuses_an_empty_intersection(): void
    {
        $this->expectExceptionObject(InvalidQueryExtensionException::noPageSize(BookResource::class, ['first' => FirstNarrowing::class]));

        $this->limits(BookResource::class, static fn (LimitsContext $context): mixed => $context->allowSizes([5]));
    }

    #[Test]
    public function it_names_each_narrowing_extension_by_name_and_class(): void
    {
        FirstNarrowing::$narrow = static fn (LimitsContext $context): mixed => $context->allowSizes([5]);
        SecondNarrowing::$narrow = static fn (LimitsContext $context): mixed => $context->maxSize(1);
        config(['rest.extensions' => [SecondNarrowing::class, FirstNarrowing::class]]);

        try {
            $this->context()->resource(BookResource::class)->definition()->limits();
            $this->fail('The narrowing was accepted.');
        } catch (InvalidQueryExtensionException $exception) {
            $this->assertSame('The [first] extension '.FirstNarrowing::class.' and the [second] extension '.SecondNarrowing::class.' narrow the page sizes of '.BookResource::class.' to none. Keep at least one size of its limits() that every allowSizes() lists, at or below every maxSize() and maxTotal() they set.', $exception->getMessage());
            $this->assertSame([true, false], [$exception->concerns(FirstNarrowing::class), $exception->concerns(SecondNarrowing::class)]);
        }
    }

    #[Test]
    public function it_refuses_a_max_total_below_every_size(): void
    {
        $this->expectExceptionObject(InvalidQueryExtensionException::noPageSize(BookResource::class, ['first' => FirstNarrowing::class]));

        $this->limits(BookResource::class, static fn (LimitsContext $context): mixed => $context->maxTotal(5));
    }

    #[Test]
    public function it_refuses_an_include_limit_below_every_size(): void
    {
        $this->expectExceptionObject(InvalidQueryExtensionException::includeLimitAboveSizes(BookResource::class, ['first' => FirstNarrowing::class], 5, [10, 25, 50]));

        $this->limits(BookResource::class, static fn (LimitsContext $context): mixed => $context->includeLimit(5));
    }

    #[Test]
    #[DataProvider('belowOne')]
    public function it_refuses_a_value_below_one(Closure $narrow, string $method, int $value): void
    {
        $this->expectExceptionObject(InvalidQueryExtensionException::invalidLimit('first', FirstNarrowing::class, BookResource::class, $method, $value));

        $this->limits(BookResource::class, $narrow);
    }

    /** @return array<string, array{Closure(LimitsContext): mixed, string, int}> */
    public static function belowOne(): array
    {
        return [
            'a size of zero' => [static fn (LimitsContext $context): mixed => $context->allowSizes([10, 0]), 'allowSizes', 0],
            'a negative max size' => [static fn (LimitsContext $context): mixed => $context->maxSize(-1), 'maxSize', -1],
            'a max total of zero' => [static fn (LimitsContext $context): mixed => $context->maxTotal(0), 'maxTotal', 0],
            'an include limit of zero' => [static fn (LimitsContext $context): mixed => $context->includeLimit(0), 'includeLimit', 0],
        ];
    }

    #[Test]
    public function it_gives_the_declared_limits_to_each_extension(): void
    {
        $seen = [];
        FirstNarrowing::$narrow = static function (LimitsContext $context) use (&$seen): void {
            $seen[] = ['first', ...self::values($context->declared)];
            $context->allowSizes([1, 10]);
            $context->maxTotal(20);
        };
        SecondNarrowing::$narrow = static function (LimitsContext $context) use (&$seen): void {
            $seen[] = ['second', ...self::values($context->declared)];
        };
        config(['rest.extensions' => [FirstNarrowing::class, SecondNarrowing::class]]);

        $this->context()->resource(CappedBookLimitsResource::class)->definition()->limits();

        $this->assertSame([
            ['first', [1, 10, 25, 50], 25, 10, 60],
            ['second', [1, 10, 25, 50], 25, 10, 60],
        ], $seen);
    }

    #[Test]
    public function it_refuses_an_extension_that_reads_the_narrowed_limits(): void
    {
        $this->expectExceptionObject(InvalidQueryExtensionException::limitsReadWhileNarrowing('first', FirstNarrowing::class, BookResource::class));

        $this->limits(BookResource::class, static fn (LimitsContext $context): mixed => $context->resource->definition()->limits());
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_gives_the_same_limits_in_any_extension_order(array $extensions): void
    {
        FirstNarrowing::$narrow = static function (LimitsContext $context): void {
            $context->allowSizes([1, 10, 25]);
            $context->maxTotal(40);
        };
        SecondNarrowing::$narrow = static function (LimitsContext $context): void {
            $context->maxSize(10);
            $context->maxTotal(50);
            $context->includeLimit(5);
        };
        config(['rest.extensions' => $extensions]);

        $limits = $this->context()->resource(CappedBookLimitsResource::class)->definition()->limits();

        $this->assertSame([[1, 10], 10, 1, 40], self::values($limits));
    }

    /** @return array<string, array{list<class-string>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [[FirstNarrowing::class, SecondNarrowing::class]],
            'in reverse order' => [[SecondNarrowing::class, FirstNarrowing::class]],
        ];
    }

    /** @param class-string<Resource> $resource */
    private function limits(string $resource, ?Closure $narrow = null): ResourceLimits
    {
        if ($narrow !== null) {
            FirstNarrowing::$narrow = $narrow;
            config(['rest.extensions' => [FirstNarrowing::class]]);
        }

        return $this->context()->resource($resource)->definition()->limits();
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/books/search', 'POST'), $this->app);
    }

    /** @return array{list<int>, int, int, ?int} */
    private static function values(ResourceLimits $limits): array
    {
        return [$limits->allowed, $limits->defaultLimit, $limits->includeLimit, $limits->maxTotal];
    }

    private static function reset(): void
    {
        FirstNarrowing::$narrow = null;
        SecondNarrowing::$narrow = null;
    }
}
