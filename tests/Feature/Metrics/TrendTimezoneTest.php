<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\ChannelsPerDay;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderTrend;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderValue;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TrendTimezoneTest extends TrendTestCase
{
    /** @param array<string, int|float|null> $points */
    #[Test]
    #[DataProvider('zones')]
    public function it_groups_the_periods_in_the_time_zone_of_the_request(string $helper, int $range, ?string $timezone, array $points): void
    {
        $this->serve(OrderTrend::make($helper, 'price', 'placed_at', null, [12 => '12 periods', 7 => '7 periods']));

        $response = $this->metric('order-trend', array_filter(['range' => $range, 'timezone' => $timezone]))->assertOk();

        $this->assertSame(self::trend(['series' => self::points($points)]), $response->getContent());
    }

    /** @return iterable<string, array{string, int, string|null, array<string, int|float|null>}> */
    public static function zones(): iterable
    {
        yield 'months, the app zone' => ['sumByMonths', 12, null, ['2025-12' => 0.0, '2026-01' => 100.0, '2026-02' => 0.0, '2026-03' => 10.0, '2026-04' => 0.0, '2026-05' => 30.0, '2026-06' => 0.0, '2026-07' => 0.0, '2026-08' => 0.0, '2026-09' => 0.0, '2026-10' => 20.0, '2026-11' => 39.5]];
        yield 'months, UTC' => ['sumByMonths', 12, 'UTC', ['2025-12' => 0.0, '2026-01' => 100.0, '2026-02' => 0.0, '2026-03' => 10.0, '2026-04' => 0.0, '2026-05' => 30.0, '2026-06' => 0.0, '2026-07' => 0.0, '2026-08' => 0.0, '2026-09' => 0.0, '2026-10' => 20.0, '2026-11' => 39.5]];
        yield 'months, Europe/Paris' => ['sumByMonths', 12, 'Europe/Paris', ['2025-12' => 0.0, '2026-01' => 100.0, '2026-02' => 0.0, '2026-03' => 0.0, '2026-04' => 10.0, '2026-05' => 0.0, '2026-06' => 30.0, '2026-07' => 0.0, '2026-08' => 0.0, '2026-09' => 0.0, '2026-10' => 20.0, '2026-11' => 39.5]];
        yield 'months, America/New_York' => ['sumByMonths', 12, 'America/New_York', ['2025-12' => 0.0, '2026-01' => 100.0, '2026-02' => 0.0, '2026-03' => 10.0, '2026-04' => 0.0, '2026-05' => 30.0, '2026-06' => 0.0, '2026-07' => 0.0, '2026-08' => 0.0, '2026-09' => 0.0, '2026-10' => 20.0, '2026-11' => 39.5]];
        yield 'weeks, UTC' => ['countByWeeks', 7, 'UTC', ['2026-09-21' => 0, '2026-09-28' => 0, '2026-10-05' => 0, '2026-10-12' => 0, '2026-10-19' => 0, '2026-10-26' => 2, '2026-11-02' => 3]];
        yield 'weeks, Europe/Paris' => ['countByWeeks', 7, 'Europe/Paris', ['2026-09-21' => 0, '2026-09-28' => 0, '2026-10-05' => 0, '2026-10-12' => 0, '2026-10-19' => 0, '2026-10-26' => 2, '2026-11-02' => 3]];
        yield 'weeks, America/New_York' => ['countByWeeks', 7, 'America/New_York', ['2026-09-21' => 0, '2026-09-28' => 0, '2026-10-05' => 0, '2026-10-12' => 0, '2026-10-19' => 0, '2026-10-26' => 3, '2026-11-02' => 2]];
        yield 'days, UTC' => ['countByDays', 7, 'UTC', ['2026-11-01' => 1, '2026-11-02' => 1, '2026-11-03' => 0, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 1, '2026-11-07' => 1]];
        yield 'days, Europe/Paris' => ['countByDays', 7, 'Europe/Paris', ['2026-11-01' => 1, '2026-11-02' => 1, '2026-11-03' => 0, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 0, '2026-11-07' => 2]];
        yield 'days, America/New_York' => ['countByDays', 7, 'America/New_York', ['2026-11-01' => 2, '2026-11-02' => 0, '2026-11-03' => 0, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 1, '2026-11-07' => 1]];
    }

    /**
     * @param  array<string, string>  $sql
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('shifts')]
    public function it_shifts_the_stored_dates_by_the_offsets_of_the_zone_in_one_query(string $helper, string $timezone, int $day, array $sql, array $bindings): void
    {
        $this->serve(OrderTrend::make($helper, 'price', 'placed_at', null, [12 => '12 periods', 7 => '7 periods'])->weekStartsOn($day));
        $this->logQueries();

        $this->metric('order-trend', ['range' => str_ends_with($helper, 'Months') ? 12 : 7, 'timezone' => $timezone])->assertOk();

        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame($bindings, $queries[0]['bindings']);
    }

    /** @return iterable<string, array{string, string, int, array<string, string>, list<mixed>}> */
    public static function shifts(): iterable
    {
        $paris = 'case when "orders"."placed_at" < \'2026-03-29 01:00:00\' then 60 when "orders"."placed_at" < \'2026-10-25 01:00:00\' then 120 else 60 end';
        $parisMy = str_replace('"', '`', $paris);
        yield 'months, two changes of offset' => ['sumByMonths', 'Europe/Paris', CarbonInterface::MONDAY, [
            'sqlite' => "select strftime('%Y-%m', datetime(\"orders\".\"placed_at\", ({$paris}) || ' minutes')) as \"period\", sum(\"orders\".\"price\") as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by strftime('%Y-%m', datetime(\"orders\".\"placed_at\", ({$paris}) || ' minutes'))",
            'mysql' => "select date_format((`orders`.`placed_at` + interval ({$parisMy}) minute), '%Y-%m') as `period`, sum(`orders`.`price`) as `period_0` from `orders` where (`orders`.`team_id` = ?) and `orders`.`placed_at` between ? and ? group by date_format((`orders`.`placed_at` + interval ({$parisMy}) minute), '%Y-%m')",
            'pgsql' => "select to_char(date_trunc('month', (\"orders\".\"placed_at\" + ({$paris}) * interval '1 minute')), 'YYYY-MM') as \"period\", sum(\"orders\".\"price\") as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by to_char(date_trunc('month', (\"orders\".\"placed_at\" + ({$paris}) * interval '1 minute')), 'YYYY-MM')",
        ], [1, '2025-11-30 23:00:00', '2026-11-07 12:00:00']];
        yield 'days, one offset' => ['countByDays', 'Asia/Tokyo', CarbonInterface::MONDAY, [
            'sqlite' => "select strftime('%Y-%m-%d', datetime(\"orders\".\"placed_at\", (540) || ' minutes')) as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by strftime('%Y-%m-%d', datetime(\"orders\".\"placed_at\", (540) || ' minutes'))",
            'mysql' => "select date_format((`orders`.`placed_at` + interval (540) minute), '%Y-%m-%d') as `period`, count(*) as `period_0` from `orders` where (`orders`.`team_id` = ?) and `orders`.`placed_at` between ? and ? group by date_format((`orders`.`placed_at` + interval (540) minute), '%Y-%m-%d')",
            'pgsql' => "select to_char(date_trunc('day', (\"orders\".\"placed_at\" + (540) * interval '1 minute')), 'YYYY-MM-DD') as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by to_char(date_trunc('day', (\"orders\".\"placed_at\" + (540) * interval '1 minute')), 'YYYY-MM-DD')",
        ], [1, '2026-10-31 15:00:00', '2026-11-07 12:00:00']];
        yield 'weeks, the zone of the stored dates' => ['countByWeeks', 'UTC', CarbonInterface::MONDAY, [
            'sqlite' => "select strftime('%Y-%m-%d', \"orders\".\"placed_at\", 'weekday 0', '-6 days') as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by strftime('%Y-%m-%d', \"orders\".\"placed_at\", 'weekday 0', '-6 days')",
            'mysql' => "select date_format(date_sub(`orders`.`placed_at`, interval weekday(`orders`.`placed_at`) day), '%Y-%m-%d') as `period`, count(*) as `period_0` from `orders` where (`orders`.`team_id` = ?) and `orders`.`placed_at` between ? and ? group by date_format(date_sub(`orders`.`placed_at`, interval weekday(`orders`.`placed_at`) day), '%Y-%m-%d')",
            'pgsql' => "select to_char(date_trunc('week', \"orders\".\"placed_at\"), 'YYYY-MM-DD') as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by to_char(date_trunc('week', \"orders\".\"placed_at\"), 'YYYY-MM-DD')",
        ], [1, '2026-09-21 00:00:00', '2026-11-07 12:00:00']];
        yield 'weeks from Sunday, one offset' => ['countByWeeks', 'Asia/Tokyo', CarbonInterface::SUNDAY, [
            'sqlite' => "select strftime('%Y-%m-%d', datetime(\"orders\".\"placed_at\", (540) || ' minutes'), 'weekday 6', '-6 days') as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by strftime('%Y-%m-%d', datetime(\"orders\".\"placed_at\", (540) || ' minutes'), 'weekday 6', '-6 days')",
            'mysql' => "select date_format(date_sub((`orders`.`placed_at` + interval (540) minute), interval mod(weekday((`orders`.`placed_at` + interval (540) minute)) + 1, 7) day), '%Y-%m-%d') as `period`, count(*) as `period_0` from `orders` where (`orders`.`team_id` = ?) and `orders`.`placed_at` between ? and ? group by date_format(date_sub((`orders`.`placed_at` + interval (540) minute), interval mod(weekday((`orders`.`placed_at` + interval (540) minute)) + 1, 7) day), '%Y-%m-%d')",
            'pgsql' => "select to_char(date_trunc('week', (\"orders\".\"placed_at\" + (540) * interval '1 minute') + interval '1 days') - interval '1 days', 'YYYY-MM-DD') as \"period\", count(*) as \"period_0\" from \"orders\" where (\"orders\".\"team_id\" = ?) and \"orders\".\"placed_at\" between ? and ? group by to_char(date_trunc('week', (\"orders\".\"placed_at\" + (540) * interval '1 minute') + interval '1 days') - interval '1 days', 'YYYY-MM-DD')",
        ], [1, '2026-09-19 15:00:00', '2026-11-07 12:00:00']];
    }

    /** @param array<string, int> $points */
    #[Test]
    #[DataProvider('sundayWeeks')]
    public function it_starts_the_weeks_on_the_declared_day_in_the_time_zone_of_the_request(string $timezone, array $points): void
    {
        $this->serve(OrderTrend::make('countByWeeks')->weekStartsOn(CarbonInterface::SUNDAY));

        $response = $this->metric('order-trend', ['range' => 7, 'timezone' => $timezone])->assertOk();

        $this->assertSame(self::trend(['series' => self::points($points)]), $response->getContent());
    }

    /** @return iterable<string, array{string, array<string, int>}> */
    public static function sundayWeeks(): iterable
    {
        $weeks = ['2026-09-20' => 0, '2026-09-27' => 0, '2026-10-04' => 0, '2026-10-11' => 0, '2026-10-18' => 0];

        yield 'UTC' => ['UTC', [...$weeks, '2026-10-25' => 1, '2026-11-01' => 4]];
        yield 'Europe/Paris, after its change of offset' => ['Europe/Paris', [...$weeks, '2026-10-25' => 1, '2026-11-01' => 4]];
        yield 'America/New_York, across its change of offset' => ['America/New_York', [...$weeks, '2026-10-25' => 1, '2026-11-01' => 4]];
        yield 'Asia/Tokyo' => ['Asia/Tokyo', [...$weeks, '2026-10-25' => 0, '2026-11-01' => 5]];
    }

    /** @param array<string, int|float|null> $points */
    #[Test]
    #[DataProvider('storedZones')]
    public function it_reads_the_stored_dates_in_the_app_zone(?string $timezone, array $points): void
    {
        Config::set('app.timezone', 'America/New_York');
        Order::query()->delete();
        Order::create(['id' => 41, 'team_id' => 1, 'reference' => 'N-41', 'price' => '1.00', 'quantity' => 1, 'gift' => false, 'placed_at' => '2026-10-31 21:30:00']);
        Order::create(['id' => 42, 'team_id' => 1, 'reference' => 'N-42', 'price' => '2.00', 'quantity' => 1, 'gift' => false, 'placed_at' => '2026-11-01 19:30:00']);
        Order::create(['id' => 43, 'team_id' => 1, 'reference' => 'N-43', 'price' => '4.00', 'quantity' => 1, 'gift' => false, 'placed_at' => '2026-11-06 23:00:00']);
        $this->serve(OrderTrend::make('sumByDays', 'price', 'placed_at', null, [8 => '8 days']));

        $response = $this->metric('order-trend', array_filter(['timezone' => $timezone]))->assertOk();

        $this->assertSame(self::trend(['series' => self::points($points)]), $response->getContent());
    }

    /** @return iterable<string, array{string|null, array<string, int|float|null>}> */
    public static function storedZones(): iterable
    {
        yield 'the app zone' => [null, ['2026-10-31' => 1.0, '2026-11-01' => 2.0, '2026-11-02' => 0.0, '2026-11-03' => 0.0, '2026-11-04' => 0.0, '2026-11-05' => 0.0, '2026-11-06' => 4.0, '2026-11-07' => 0.0]];
        yield 'UTC' => ['UTC', ['2026-10-31' => 0.0, '2026-11-01' => 1.0, '2026-11-02' => 2.0, '2026-11-03' => 0.0, '2026-11-04' => 0.0, '2026-11-05' => 0.0, '2026-11-06' => 0.0, '2026-11-07' => 4.0]];
    }

    /**
     * @param  array<string, int|float|null>  $points
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('dateZones')]
    public function it_dates_the_periods_of_a_date_field_in_the_zone_without_shifting_it(string $timezone, array $points, array $bindings): void
    {
        $this->serve(OrderTrend::make('countByDays', 'price', 'delivered_on', null, [7 => '7 days']));
        $this->logQueries();

        $response = $this->metric('order-trend', ['timezone' => $timezone])->assertOk();

        $this->assertSame(self::trend(['series' => self::points($points)]), $response->getContent());
        $this->assertSame($bindings, DB::getQueryLog()[0]['bindings']);
    }

    /** @return iterable<string, array{string, array<string, int|float|null>, list<mixed>}> */
    public static function dateZones(): iterable
    {
        yield 'a zone already on the next day' => ['Pacific/Kiritimati', ['2026-11-02' => 1, '2026-11-03' => 1, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 0, '2026-11-07' => 1, '2026-11-08' => 1], [1, '2026-11-02 00:00:00', '2026-11-08 00:00:00']];
        yield 'a zone behind the stored dates' => ['America/New_York', ['2026-11-01' => 0, '2026-11-02' => 1, '2026-11-03' => 1, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 0, '2026-11-07' => 1], [1, '2026-11-01 00:00:00', '2026-11-07 00:00:00']];
    }

    #[Test]
    public function it_gives_the_zone_of_the_request_to_a_custom_metric(): void
    {
        $this->serve(ChannelsPerDay::make());

        $response = $this->metric('channels-per-day', ['timezone' => 'America/New_York'])->assertOk();

        $this->assertSame(self::trend(['series' => self::points(['2026-11-01' => 2, '2026-11-02' => 0, '2026-11-03' => 0, '2026-11-04' => 0, '2026-11-05' => 0, '2026-11-06' => 0, '2026-11-07' => 1])]), $response->getContent());
    }

    #[Test]
    #[DataProvider('invalidZones')]
    public function it_validates_the_timezone_of_the_request(mixed $timezone): void
    {
        $this->serve(OrderTrend::make());
        $this->logQueries();

        $this->metric('order-trend', ['timezone' => $timezone])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'The timezone field must be a valid timezone.', 'errors' => ['timezone' => ['The timezone field must be a valid timezone.']]]);
        $this->assertSame([], DB::getQueryLog());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidZones(): iterable
    {
        yield 'an unknown zone' => ['Mars/Olympus'];
        yield 'an offset' => ['+01:00'];
        yield 'a zone in lower case' => ['europe/paris'];
        yield 'a number' => [1];
    }

    #[Test]
    public function it_accepts_a_timezone_on_every_metric(): void
    {
        $this->serve(OrderValue::make('count', 'price', 'placed_at', []));

        $this->assertSame('{"data":{"type":"value","value":9,"previous":null}}', $this->metric('order-value', ['timezone' => 'Europe/Paris'])->assertOk()->getContent());
    }
}
