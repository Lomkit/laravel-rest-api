<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderTrend;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TopStatuses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TrendSplitLimitTest extends TrendTestCase
{
    /** @param array<string, mixed> $data */
    #[Test]
    #[DataProvider('limits')]
    public function it_keeps_the_largest_splits_and_folds_the_others_into_one(Metric $metric, string $key, array $data): void
    {
        $this->serve($metric);

        $response = $this->metric($key)->assertOk();

        $this->assertSame(self::trend($data), $response->getContent());
    }

    /** @return iterable<string, array{Metric, string, array<string, mixed>}> */
    public static function limits(): iterable
    {
        $weeks = ['2026-09-21' => 0, '2026-09-28' => 0, '2026-10-05' => 0, '2026-10-12' => 0, '2026-10-19' => 0];

        yield 'a tie at the limit, kept by key' => [OrderTrend::make('countByMonths', 'price', 'placed_at', 'status', [3 => '3 months'])->limit(1), 'order-trend', ['series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 4]), 'splits' => [
            ['key' => 'paid', 'label' => 'Paid', 'series' => self::points(['2026-09' => 0, '2026-10' => 0, '2026-11' => 2])],
        ], 'other' => ['label' => 'Other', 'series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 2])]]];
        yield 'the null group folded among equal totals, the kept splits by key' => [OrderTrend::make('countByWeeks', 'price', 'placed_at', 'channel', [7 => '7 periods'])->limit(2), 'order-trend', ['series' => self::points([...$weeks, '2026-10-26' => 2, '2026-11-02' => 3]), 'splits' => [
            ['key' => 'shop', 'label' => 'shop', 'series' => self::points([...$weeks, '2026-10-26' => 1, '2026-11-02' => 0])],
            ['key' => 'web', 'label' => 'web', 'series' => self::points([...$weeks, '2026-10-26' => 1, '2026-11-02' => 2])],
        ], 'other' => ['label' => 'Other', 'series' => self::points([...$weeks, '2026-10-26' => 0, '2026-11-02' => 1])]]];
        yield 'an average over the folded splits' => [OrderTrend::make('averageByMonths', 'price', 'placed_at', 'status', [3 => '3 months'])->limit(1), 'order-trend', ['series' => self::points(['2026-09' => null, '2026-10' => 20.0, '2026-11' => 9.875]), 'splits' => [
            ['key' => 'pending', 'label' => 'Pending', 'series' => self::points(['2026-09' => null, '2026-10' => 20.0, '2026-11' => 15.0])],
        ], 'other' => ['label' => 'Other', 'series' => self::points(['2026-09' => null, '2026-10' => null, '2026-11' => 24.5 / 3])]]];
        yield 'fewer splits than the limit' => [OrderTrend::make('countByMonths', 'price', 'placed_at', 'status', [3 => '3 months'])->limit(5), 'order-trend', ['series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 4]), 'splits' => [
            ['key' => 'paid', 'label' => 'Paid', 'series' => self::points(['2026-09' => 0, '2026-10' => 0, '2026-11' => 2])],
            ['key' => 'pending', 'label' => 'Pending', 'series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 1])],
            ['key' => null, 'label' => null, 'series' => self::points(['2026-09' => 0, '2026-10' => 0, '2026-11' => 1])],
        ], 'other' => null]];
        yield 'a limit declared on the class' => [TopStatuses::make(), 'top-statuses', ['series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 4]), 'splits' => [
            ['key' => 'paid', 'label' => 'Paid', 'series' => self::points(['2026-09' => 0, '2026-10' => 0, '2026-11' => 2])],
        ], 'other' => ['label' => 'Other', 'series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 2])]]];
        yield 'a limit without a split' => [OrderTrend::make('countByMonths', 'price', 'placed_at', null, [3 => '3 months'])->limit(1), 'order-trend', ['series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 4])]];
    }

    #[Test]
    public function it_renames_the_splits_and_the_other_series(): void
    {
        $this->serve(OrderTrend::make('countByMonths', 'price', 'placed_at', 'status', [3 => '3 months'], ['paid' => 'Payée'], 'Autre')->limit(1));

        $response = $this->metric('order-trend')->assertOk();

        $this->assertSame(self::trend(['series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 4]), 'splits' => [
            ['key' => 'paid', 'label' => 'Payée', 'series' => self::points(['2026-09' => 0, '2026-10' => 0, '2026-11' => 2])],
        ], 'other' => ['label' => 'Autre', 'series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 2])]]), $response->getContent());
    }

    #[Test]
    public function it_rejects_a_limit_below_one_when_the_routes_register(): void
    {
        $this->expectExceptionObject(InvalidMetricException::invalidLimit(OrderTrend::class, 0));

        $this->serve(OrderTrend::make()->limit(0));
    }
}
