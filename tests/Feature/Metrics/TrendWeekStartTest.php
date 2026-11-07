<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\ChannelsPerWeek;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderTrend;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\SundayWeeks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TrendWeekStartTest extends TrendTestCase
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, int|float|null>  $points
     */
    #[Test]
    #[DataProvider('weeks')]
    public function it_starts_the_weeks_on_the_declared_day(Metric $metric, string $key, array $body, array $points): void
    {
        $this->serve($metric);

        $response = $this->metric($key, $body)->assertOk();

        $this->assertSame(self::trend(['series' => self::points($points)]), $response->getContent());
    }

    /** @return iterable<string, array{Metric, string, array<string, mixed>, array<string, int|float|null>}> */
    public static function weeks(): iterable
    {
        $sundays = ['2026-09-20' => 0, '2026-09-27' => 0, '2026-10-04' => 0, '2026-10-11' => 0, '2026-10-18' => 0, '2026-10-25' => 1, '2026-11-01' => 4];

        yield 'Monday by default' => [OrderTrend::make('countByWeeks'), 'order-trend', ['range' => 7], ['2026-09-21' => 0, '2026-09-28' => 0, '2026-10-05' => 0, '2026-10-12' => 0, '2026-10-19' => 0, '2026-10-26' => 2, '2026-11-02' => 3]];
        yield 'Sunday on the declaration' => [OrderTrend::make('countByWeeks')->weekStartsOn(CarbonInterface::SUNDAY), 'order-trend', ['range' => 7], $sundays];
        yield 'Sunday on the class' => [SundayWeeks::make(), 'sunday-weeks', [], $sundays];
        yield 'Saturday' => [OrderTrend::make('countByWeeks')->weekStartsOn(CarbonInterface::SATURDAY), 'order-trend', ['range' => 7], ['2026-09-26' => 0, '2026-10-03' => 0, '2026-10-10' => 0, '2026-10-17' => 0, '2026-10-24' => 0, '2026-10-31' => 4, '2026-11-07' => 1]];
        yield 'a custom metric that groups by week' => [ChannelsPerWeek::make(), 'channels-per-week', [], ['2026-10-25' => 1, '2026-11-01' => 2]];
    }

    /**
     * @param  array<string, string>  $sql
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('queries')]
    public function it_groups_the_weeks_from_the_declared_day_in_one_query(int $day, array $sql, array $bindings): void
    {
        $this->serve(OrderTrend::make('countByWeeks')->weekStartsOn($day));
        $this->logQueries();

        $this->metric('order-trend', ['range' => 7])->assertOk();

        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame($bindings, $queries[0]['bindings']);
    }

    /** @return iterable<string, array{int, array<string, string>, list<mixed>}> */
    public static function queries(): iterable
    {
        yield 'Monday' => [CarbonInterface::MONDAY, self::weekly(
            'strftime(\'%Y-%m-%d\', "orders"."placed_at", \'weekday 0\', \'-6 days\')',
            'date_format(date_sub(`orders`.`placed_at`, interval weekday(`orders`.`placed_at`) day), \'%Y-%m-%d\')',
            'to_char(date_trunc(\'week\', "orders"."placed_at"), \'YYYY-MM-DD\')',
        ), [1, '2026-09-21 00:00:00', '2026-11-07 12:00:00']];
        yield 'Sunday' => [CarbonInterface::SUNDAY, self::weekly(
            'strftime(\'%Y-%m-%d\', "orders"."placed_at", \'weekday 6\', \'-6 days\')',
            'date_format(date_sub(`orders`.`placed_at`, interval mod(weekday(`orders`.`placed_at`) + 1, 7) day), \'%Y-%m-%d\')',
            'to_char(date_trunc(\'week\', "orders"."placed_at" + interval \'1 days\') - interval \'1 days\', \'YYYY-MM-DD\')',
        ), [1, '2026-09-20 00:00:00', '2026-11-07 12:00:00']];
        yield 'Saturday' => [CarbonInterface::SATURDAY, self::weekly(
            'strftime(\'%Y-%m-%d\', "orders"."placed_at", \'weekday 5\', \'-6 days\')',
            'date_format(date_sub(`orders`.`placed_at`, interval mod(weekday(`orders`.`placed_at`) + 2, 7) day), \'%Y-%m-%d\')',
            'to_char(date_trunc(\'week\', "orders"."placed_at" + interval \'2 days\') - interval \'2 days\', \'YYYY-MM-DD\')',
        ), [1, '2026-09-26 00:00:00', '2026-11-07 12:00:00']];
    }

    #[Test]
    public function it_rejects_a_week_start_outside_the_week_when_the_routes_register(): void
    {
        $this->expectExceptionObject(InvalidMetricException::invalidWeekStart(OrderTrend::class, 7));

        $this->serve(OrderTrend::make('countByWeeks')->weekStartsOn(7));
    }

    /** @return array<string, string> */
    private static function weekly(string $sqlite, string $mysql, string $pgsql): array
    {
        return [
            'sqlite' => "select {$sqlite} as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by {$sqlite}",
            'mysql' => "select {$mysql} as `period`, count(*) as `period_0` from `orders` where (`orders`.`team_id` = ?) and `orders`.`placed_at` between ? and ? group by {$mysql}",
            'pgsql' => "select {$pgsql} as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by {$pgsql}",
        ];
    }
}
