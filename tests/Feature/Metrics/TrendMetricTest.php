<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Grouping\PeriodGrammar;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\ChannelsPerDay;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderTrend;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RevenuePerMonth;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\UnboundedPeriods;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TrendMetricTest extends TrendTestCase
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, int|float|null>  $points
     */
    #[Test]
    #[DataProvider('series')]
    public function it_gives_one_point_per_period_of_the_range(string $helper, string $column, string $dateColumn, array $body, array $points): void
    {
        $this->serve(OrderTrend::make($helper, $column, $dateColumn));

        $response = $this->metric('order-trend', $body)->assertOk();

        $this->assertSame(self::trend(['series' => self::points($points)]), $response->getContent());
    }

    /** @return iterable<string, array{string, string, string, array<string, mixed>, array<string, int|float|null>}> */
    public static function series(): iterable
    {
        yield 'count by months, the first range' => ['countByMonths', 'price', 'placed_at', [], ['2025-12' => 0, '2026-01' => 1, '2026-02' => 0, '2026-03' => 1, '2026-04' => 0, '2026-05' => 1, '2026-06' => 0, '2026-07' => 0, '2026-08' => 0, '2026-09' => 0, '2026-10' => 1, '2026-11' => 4]];
        yield 'sum of a number by months' => ['sumByMonths', 'price', 'placed_at', [], ['2025-12' => 0.0, '2026-01' => 100.0, '2026-02' => 0.0, '2026-03' => 10.0, '2026-04' => 0.0, '2026-05' => 30.0, '2026-06' => 0.0, '2026-07' => 0.0, '2026-08' => 0.0, '2026-09' => 0.0, '2026-10' => 20.0, '2026-11' => 39.5]];
        yield 'average of a number by months' => ['averageByMonths', 'price', 'placed_at', [], ['2025-12' => null, '2026-01' => 100.0, '2026-02' => null, '2026-03' => 10.0, '2026-04' => null, '2026-05' => 30.0, '2026-06' => null, '2026-07' => null, '2026-08' => null, '2026-09' => null, '2026-10' => 20.0, '2026-11' => 9.875]];
        yield 'sum of an integer by weeks' => ['sumByWeeks', 'quantity', 'placed_at', ['range' => 7], ['2026-09-21' => 0, '2026-09-28' => 0, '2026-10-05' => 0, '2026-10-12' => 0, '2026-10-19' => 0, '2026-10-26' => 2, '2026-11-02' => 7]];
        yield 'max of an integer by weeks' => ['maxByWeeks', 'quantity', 'placed_at', ['range' => 7], ['2026-09-21' => null, '2026-09-28' => null, '2026-10-05' => null, '2026-10-12' => null, '2026-10-19' => null, '2026-10-26' => 1, '2026-11-02' => 4]];
        yield 'count by days' => ['countByDays', 'price', 'placed_at', ['range' => 7], ['2026-11-01' => 1, '2026-11-02' => 1, '2026-11-03' => 0, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 1, '2026-11-07' => 1]];
        yield 'min of a number by days' => ['minByDays', 'price', 'placed_at', ['range' => 7], ['2026-11-01' => 12.5, '2026-11-02' => 5.0, '2026-11-03' => null, '2026-11-04' => null, '2026-11-05' => null, '2026-11-06' => 7.0, '2026-11-07' => 15.0]];
        yield 'sum by days of a date field' => ['sumByDays', 'price', 'delivered_on', ['range' => 7], ['2026-11-01' => 0.0, '2026-11-02' => 20.0, '2026-11-03' => 12.5, '2026-11-04' => 0.0, '2026-11-05' => 0.0, '2026-11-06' => 0.0, '2026-11-07' => 15.0]];
    }

    #[Test]
    public function it_starts_at_the_first_period_of_the_range_and_ends_now(): void
    {
        Order::create(['id' => 31, 'team_id' => 1, 'reference' => 'T-31', 'price' => '1.00', 'quantity' => 1, 'gift' => false, 'placed_at' => '2025-11-30 23:59:59']);
        Order::create(['id' => 32, 'team_id' => 1, 'reference' => 'T-32', 'price' => '2.00', 'quantity' => 1, 'gift' => false, 'placed_at' => '2025-12-01 00:00:00']);
        Order::create(['id' => 33, 'team_id' => 1, 'reference' => 'T-33', 'price' => '4.00', 'quantity' => 1, 'gift' => false, 'placed_at' => '2026-11-07 12:00:00']);
        $this->serve(OrderTrend::make('sumByMonths', 'price', 'placed_at', null, [3 => '3 months', 12 => '12 months']));

        $this->assertSame(self::trend(['series' => self::points(['2026-09' => 0.0, '2026-10' => 20.0, '2026-11' => 43.5])]), $this->metric('order-trend')->getContent());
        $this->assertSame(self::trend(['series' => self::points(['2025-12' => 2.0, '2026-01' => 100.0, '2026-02' => 0.0, '2026-03' => 10.0, '2026-04' => 0.0, '2026-05' => 30.0, '2026-06' => 0.0, '2026-07' => 0.0, '2026-08' => 0.0, '2026-09' => 0.0, '2026-10' => 20.0, '2026-11' => 43.5])]), $this->metric('order-trend', ['range' => 12])->getContent());
    }

    /**
     * @param  array<int|string, string>  $labels
     * @param  array<string, mixed>  $data
     */
    #[Test]
    #[DataProvider('splits')]
    public function it_splits_a_trend_into_one_series_per_value(string $helper, string $splitBy, array $labels, array $data): void
    {
        $this->serve(OrderTrend::make($helper, 'price', 'placed_at', $splitBy, [3 => '3 months', 7 => '7 periods'], $labels));

        $response = $this->metric('order-trend', str_ends_with($helper, 'Months') ? [] : ['range' => 7])->assertOk();

        $this->assertSame(self::trend($data), $response->getContent());
    }

    /** @return iterable<string, array{string, string, array<int|string, string>, array<string, mixed>}> */
    public static function splits(): iterable
    {
        yield 'count by an enum, the null group last' => ['countByMonths', 'status', [], ['series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 4]), 'splits' => [
            ['key' => 'paid', 'label' => 'Paid', 'series' => self::points(['2026-09' => 0, '2026-10' => 0, '2026-11' => 2])],
            ['key' => 'pending', 'label' => 'Pending', 'series' => self::points(['2026-09' => 0, '2026-10' => 1, '2026-11' => 1])],
            ['key' => null, 'label' => null, 'series' => self::points(['2026-09' => 0, '2026-10' => 0, '2026-11' => 1])],
        ], 'other' => null]];
        yield 'average by a text column, merged exactly' => ['averageByWeeks', 'channel', [], ['series' => self::points(['2026-09-21' => null, '2026-09-28' => null, '2026-10-05' => null, '2026-10-12' => null, '2026-10-19' => null, '2026-10-26' => 16.25, '2026-11-02' => 9.0]), 'splits' => [
            ['key' => 'shop', 'label' => 'shop', 'series' => self::points(['2026-09-21' => null, '2026-09-28' => null, '2026-10-05' => null, '2026-10-12' => null, '2026-10-19' => null, '2026-10-26' => 12.5, '2026-11-02' => null])],
            ['key' => 'web', 'label' => 'web', 'series' => self::points(['2026-09-21' => null, '2026-09-28' => null, '2026-10-05' => null, '2026-10-12' => null, '2026-10-19' => null, '2026-10-26' => 20.0, '2026-11-02' => 10.0])],
            ['key' => null, 'label' => null, 'series' => self::points(['2026-09-21' => null, '2026-09-28' => null, '2026-10-05' => null, '2026-10-12' => null, '2026-10-19' => null, '2026-10-26' => null, '2026-11-02' => 7.0])],
        ], 'other' => null]];
        yield 'sum by an integer column, renamed' => ['sumByWeeks', 'quantity', [1 => 'Single', 4 => 'Pack of four'], ['series' => self::points(['2026-09-21' => 0.0, '2026-09-28' => 0.0, '2026-10-05' => 0.0, '2026-10-12' => 0.0, '2026-10-19' => 0.0, '2026-10-26' => 32.5, '2026-11-02' => 27.0]), 'splits' => [
            ['key' => 1, 'label' => 'Single', 'series' => self::points(['2026-09-21' => 0.0, '2026-09-28' => 0.0, '2026-10-05' => 0.0, '2026-10-12' => 0.0, '2026-10-19' => 0.0, '2026-10-26' => 32.5, '2026-11-02' => 7.0])],
            ['key' => 2, 'label' => '2', 'series' => self::points(['2026-09-21' => 0.0, '2026-09-28' => 0.0, '2026-10-05' => 0.0, '2026-10-12' => 0.0, '2026-10-19' => 0.0, '2026-10-26' => 0.0, '2026-11-02' => 15.0])],
            ['key' => 4, 'label' => 'Pack of four', 'series' => self::points(['2026-09-21' => 0.0, '2026-09-28' => 0.0, '2026-10-05' => 0.0, '2026-10-12' => 0.0, '2026-10-19' => 0.0, '2026-10-26' => 0.0, '2026-11-02' => 5.0])],
        ], 'other' => null]];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $sql
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('queries')]
    public function it_groups_the_search_inside_the_perimeter_in_one_query(?string $splitBy, array $body, array $sql, array $bindings): void
    {
        $this->serve(OrderTrend::make('countByMonths', 'price', 'placed_at', $splitBy, [3 => '3 months']));
        $this->logQueries();

        $this->metric('order-trend', $body)->assertOk();

        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame($bindings, $queries[0]['bindings']);
    }

    /** @return iterable<string, array{string|null, array<string, mixed>, array<string, string>, list<mixed>}> */
    public static function queries(): iterable
    {
        yield 'a filter' => [null, ['search' => ['filters' => [['field' => 'status', 'value' => 'paid']]]], [
            'sqlite' => 'select strftime(\'%Y-%m\', "orders"."placed_at") as "period", count(*) as "period_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ? group by strftime(\'%Y-%m\', "orders"."placed_at")',
            'mysql' => 'select date_format(`orders`.`placed_at`, \'%Y-%m\') as `period`, count(*) as `period_0` from `orders` where (`orders`.`team_id` = ?) and (`orders`.`status` = ?) and `orders`.`placed_at` between ? and ? group by date_format(`orders`.`placed_at`, \'%Y-%m\')',
            'pgsql' => 'select to_char(date_trunc(\'month\', "orders"."placed_at"), \'YYYY-MM\') as "period", count(*) as "period_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ? group by to_char(date_trunc(\'month\', "orders"."placed_at"), \'YYYY-MM\')',
        ], [1, 'paid', '2026-09-01 00:00:00', '2026-11-07 12:00:00']];
        yield 'a split and a text' => ['status', ['search' => ['text' => ['value' => 'T-1']]], [
            'sqlite' => 'select strftime(\'%Y-%m\', "orders"."placed_at") as "period", "orders"."status" as "split_key", count(*) as "period_0" from "orders" where ("orders"."team_id" = ?) and (("orders"."reference" like ? escape \'!\')) and "orders"."placed_at" between ? and ? group by strftime(\'%Y-%m\', "orders"."placed_at"), "orders"."status"',
            'mysql' => 'select date_format(`orders`.`placed_at`, \'%Y-%m\') as `period`, `orders`.`status` as `split_key`, count(*) as `period_0` from `orders` where (`orders`.`team_id` = ?) and ((`orders`.`reference` like ? escape \'!\')) and `orders`.`placed_at` between ? and ? group by date_format(`orders`.`placed_at`, \'%Y-%m\'), `orders`.`status`',
            'pgsql' => 'select to_char(date_trunc(\'month\', "orders"."placed_at"), \'YYYY-MM\') as "period", "orders"."status" as "split_key", count(*) as "period_0" from "orders" where ("orders"."team_id" = ?) and (("orders"."reference" ilike ? escape \'!\')) and "orders"."placed_at" between ? and ? group by to_char(date_trunc(\'month\', "orders"."placed_at"), \'YYYY-MM\'), "orders"."status"',
        ], [1, '%T-1%', '2026-09-01 00:00:00', '2026-11-07 12:00:00']];
    }

    #[Test]
    public function it_answers_the_example_of_a_revenue_per_month(): void
    {
        $this->serve(RevenuePerMonth::make());

        $response = $this->metric('revenue-per-month', ['range' => 6, 'search' => ['filters' => [['field' => 'status', 'value' => 'paid']]]])->assertOk();

        $this->assertSame('{"data":{"type":"trend","series":[{"label":"2026-06","value":0.0},{"label":"2026-07","value":0.0},{"label":"2026-08","value":0.0},{"label":"2026-09","value":0.0},{"label":"2026-10","value":0.0},{"label":"2026-11","value":19.5}]}}', $response->getContent());
    }

    #[Test]
    public function it_lets_a_custom_metric_group_rows_by_period(): void
    {
        $this->serve(ChannelsPerDay::make());

        $response = $this->metric('channels-per-day')->assertOk();

        $this->assertSame(self::trend(['series' => self::points(['2026-11-01' => 1, '2026-11-02' => 1, '2026-11-03' => 0, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 0, '2026-11-07' => 1])]), $response->getContent());
    }

    #[Test]
    public function it_needs_a_number_of_periods_outside_the_ranges(): void
    {
        $this->withoutExceptionHandling();
        $this->serve(UnboundedPeriods::make());

        $this->expectExceptionObject(InvalidMetricException::withoutPeriods(UnboundedPeriods::class, 'periods'));

        $this->metric('unbounded-periods');
    }

    #[Test]
    public function it_validates_the_range_of_a_trend(): void
    {
        $this->serve(OrderTrend::make());
        $this->logQueries();

        $this->metric('order-trend', ['range' => 30])->assertStatus(422)->assertExactJson(['message' => 'The selected range is invalid.', 'errors' => ['range' => ['The selected range is invalid.']]]);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_field_that_does_not_fit_the_helper(string $helper, string $column, string $dateColumn, ?string $splitBy, InvalidMetricException $exception): void
    {
        $this->withoutExceptionHandling();
        $this->serve(OrderTrend::make($helper, $column, $dateColumn, $splitBy));

        $this->expectExceptionObject($exception);

        $this->metric('order-trend');
    }

    /** @return iterable<string, array{string, string, string, string|null, InvalidMetricException}> */
    public static function misfits(): iterable
    {
        yield 'periods dated with an integer field' => ['countByMonths', 'price', 'quantity', null, InvalidMetricException::wrongField(OrderTrend::class, 'countByMonths', 'dates its periods with', 'quantity', 'integer', 'a date or datetime')];
        yield 'periods dated with a field that is not declared' => ['countByDays', 'price', 'shipped_at', null, InvalidMetricException::unknownField(OrderTrend::class, 'countByDays', 'dates its periods with', 'shipped_at', MetricOrderResource::class)];
        yield 'the sum of a text field' => ['sumByWeeks', 'reference', 'placed_at', null, InvalidMetricException::wrongField(OrderTrend::class, 'sumByWeeks', 'aggregates', 'reference', 'text', 'a number')];
        yield 'a split by a datetime field' => ['countByMonths', 'price', 'placed_at', 'placed_at', InvalidMetricException::wrongField(OrderTrend::class, 'countByMonths', 'groups by', 'placed_at', 'datetime', 'a text, enum, integer or boolean')];
    }

    #[Test]
    public function it_rejects_a_trend_without_ranges_when_the_routes_register(): void
    {
        $this->expectExceptionObject(InvalidMetricException::trendWithoutRanges(OrderTrend::class));

        $this->serve(OrderTrend::make('countByMonths', 'price', 'placed_at', null, []));
    }

    #[Test]
    public function it_refuses_a_driver_without_period_grammar(): void
    {
        $this->expectExceptionObject(InvalidMetricException::unsupportedDriver('sqlsrv'));

        PeriodGrammar::for('sqlsrv', CarbonInterface::MONDAY);
    }

    #[Test]
    public function it_describes_a_trend(): void
    {
        $this->serve(RevenuePerMonth::make());

        $this->getJson('/metric-orders?with=metrics.meta,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.metrics', [['name' => 'Revenue Per Month', 'uriKey' => 'revenue-per-month', 'type' => 'trend', 'ranges' => [['value' => 3, 'label' => '3 months'], ['value' => 6, 'label' => '6 months'], ['value' => 12, 'label' => '12 months']], 'meta' => [], 'authorized' => true]]);
    }
}
