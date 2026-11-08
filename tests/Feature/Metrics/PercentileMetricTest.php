<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderPartition;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderTable;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderTrend;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderValue;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PercentileMetricTest extends SalesTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $rows = [
            [41, 1, 'paid', 'web', 'acme', '10.00', 1, 1, '2026-11-01 09:00:00'],
            [42, 1, 'paid', 'shop', 'acme', '20.00', 2, null, '2026-10-30 10:00:00'],
            [43, 1, 'paid', 'web', 'bolt', '30.00', 3, 4, '2026-10-20 10:00:00'],
            [44, 1, 'pending', 'web', 'bolt', '40.00', 4, 2, '2026-10-10 10:00:00'],
            [45, 1, 'pending', 'shop', 'crane', '100.00', 5, null, '2026-09-20 10:00:00'],
            [46, 1, 'refunded', 'web', 'crane', '60.00', 6, 5, '2026-09-15 10:00:00'],
            [47, 1, null, 'web', null, '50.00', 7, null, '2026-09-10 10:00:00'],
            [48, 2, 'paid', 'web', 'acme', '999.00', 9, 5, '2026-11-02 10:00:00'],
        ];

        foreach ($rows as [$id, $team, $status, $channel, $customer, $price, $quantity, $rating, $placedAt]) {
            Order::create(['id' => $id, 'team_id' => $team, 'reference' => "P-{$id}", 'status' => $status, 'channel' => $channel, 'customer' => $customer, 'price' => $price, 'quantity' => $quantity, 'rating' => $rating, 'gift' => false, 'placed_at' => $placedAt]);
        }
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('periods')]
    public function it_computes_a_percentile_over_the_current_and_the_previous_period(string $helper, string $column, float $percentile, array $body, string $json): void
    {
        $this->serve(OrderValue::make($helper, $column, 'placed_at', [30 => '30 days', 90 => '90 days'], $percentile));

        $response = $this->metric('order-value', $body)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{string, string, float, array<string, mixed>, string}> */
    public static function periods(): iterable
    {
        yield 'median of an even and of an odd count' => ['median', 'price', 0.5, [], '{"data":{"type":"value","value":25.0,"previous":60.0}}'];
        yield 'an interpolated percentile' => ['percentile', 'price', 0.75, [], '{"data":{"type":"value","value":32.5,"previous":80.0}}'];
        yield 'the ninetieth percentile' => ['percentile', 'price', 0.9, [], '{"data":{"type":"value","value":37.0,"previous":92.0}}'];
        yield 'median of an integer without its null values' => ['median', 'rating', 0.5, [], '{"data":{"type":"value","value":2.0,"previous":5.0}}'];
        yield 'a previous period without rows' => ['median', 'price', 0.5, ['range' => 90], '{"data":{"type":"value","value":40.0,"previous":null}}'];
    }

    #[Test]
    #[DataProvider('totals')]
    public function it_computes_a_percentile_over_every_row_without_ranges(string $helper, string $column, float $percentile, string $json): void
    {
        $this->serve(OrderValue::make($helper, $column, 'placed_at', [], $percentile));

        $response = $this->metric('order-value')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{string, string, float, string}> */
    public static function totals(): iterable
    {
        yield 'median of an odd count' => ['median', 'price', 0.5, '{"data":{"type":"value","value":40.0,"previous":null}}'];
        yield 'the lowest value' => ['percentile', 'price', 0.0, '{"data":{"type":"value","value":10.0,"previous":null}}'];
        yield 'the highest value' => ['percentile', 'price', 1.0, '{"data":{"type":"value","value":100.0,"previous":null}}'];
        yield 'between two rows' => ['percentile', 'price', 0.75, '{"data":{"type":"value","value":55.0,"previous":null}}'];
        yield 'median of an even count without the null values' => ['median', 'rating', 0.5, '{"data":{"type":"value","value":3.0,"previous":null}}'];
        yield 'median of an integer' => ['median', 'quantity', 0.5, '{"data":{"type":"value","value":4.0,"previous":null}}'];
    }

    #[Test]
    public function it_gives_null_over_no_row(): void
    {
        Order::query()->where('team_id', 1)->delete();
        $this->serve(OrderValue::make('median', 'price', 'placed_at', []));

        $response = $this->metric('order-value')->assertOk();

        $this->assertSame('{"data":{"type":"value","value":null,"previous":null}}', $response->getContent());
    }

    /**
     * @param  array<string, string>  $sql
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('queries')]
    public function it_computes_the_percentile_in_one_query(Metric $metric, string $key, array $sql, array $bindings): void
    {
        $this->serve($metric);
        $this->logQueries();

        $this->metric($key)->assertOk();

        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame($bindings, $queries[0]['bindings']);
    }

    /** @return iterable<string, array{Metric, string, array<string, string>, list<mixed>}> */
    public static function queries(): iterable
    {
        yield 'a percentile over every row' => [OrderValue::make('percentile', 'price', 'placed_at', [], 0.75), 'order-value', [
            'sqlite' => 'select min(case when abs("rest_offset_total") < 1 then "orders"."price" end) + max(case when abs("rest_offset_total") < 1 then "rest_offset_total" end) * (max(case when abs("rest_offset_total") < 1 then "orders"."price" end) - min(case when abs("rest_offset_total") < 1 then "orders"."price" end)) as "total_0" from (select "orders".*, 0.75 * (count("orders"."price") over () - 1) - row_number() over (order by case when "orders"."price" is null then 1 else 0 end, "orders"."price") + 1 as "rest_offset_total" from "orders" where ("orders"."team_id" = ?)) as "orders"',
            'mysql' => 'select min(case when abs(`rest_offset_total`) < 1 then `orders`.`price` end) + max(case when abs(`rest_offset_total`) < 1 then `rest_offset_total` end) * (max(case when abs(`rest_offset_total`) < 1 then `orders`.`price` end) - min(case when abs(`rest_offset_total`) < 1 then `orders`.`price` end)) as `total_0` from (select `orders`.*, 0.75 * (count(`orders`.`price`) over () - 1) - row_number() over (order by case when `orders`.`price` is null then 1 else 0 end, `orders`.`price`) + 1 as `rest_offset_total` from `orders` where (`orders`.`team_id` = ?)) as `orders`',
            'pgsql' => 'select percentile_cont(0.75) within group (order by "orders"."price") as "total_0" from "orders" where ("orders"."team_id" = ?)',
        ], [1]];
        yield 'a median for each group' => [OrderPartition::make('median'), 'order-partition', [
            'sqlite' => 'select "orders"."status" as "group_key", min(case when abs("rest_offset_group") < 1 then "orders"."price" end) + max(case when abs("rest_offset_group") < 1 then "rest_offset_group" end) * (max(case when abs("rest_offset_group") < 1 then "orders"."price" end) - min(case when abs("rest_offset_group") < 1 then "orders"."price" end)) as "group_0" from (select "orders".*, 0.5 * (count("orders"."price") over (partition by "orders"."status") - 1) - row_number() over (partition by "orders"."status" order by case when "orders"."price" is null then 1 else 0 end, "orders"."price") + 1 as "rest_offset_group" from "orders" where ("orders"."team_id" = ?)) as "orders" group by "orders"."status" order by case when min(case when abs("rest_offset_group") < 1 then "orders"."price" end) + max(case when abs("rest_offset_group") < 1 then "rest_offset_group" end) * (max(case when abs("rest_offset_group") < 1 then "orders"."price" end) - min(case when abs("rest_offset_group") < 1 then "orders"."price" end)) is null then 1 else 0 end asc, min(case when abs("rest_offset_group") < 1 then "orders"."price" end) + max(case when abs("rest_offset_group") < 1 then "rest_offset_group" end) * (max(case when abs("rest_offset_group") < 1 then "orders"."price" end) - min(case when abs("rest_offset_group") < 1 then "orders"."price" end)) desc, case when "orders"."status" is null then 1 else 0 end asc, "orders"."status" asc limit 101',
            'mysql' => 'select `orders`.`status` as `group_key`, min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) + max(case when abs(`rest_offset_group`) < 1 then `rest_offset_group` end) * (max(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) - min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end)) as `group_0` from (select `orders`.*, 0.5 * (count(`orders`.`price`) over (partition by `orders`.`status`) - 1) - row_number() over (partition by `orders`.`status` order by case when `orders`.`price` is null then 1 else 0 end, `orders`.`price`) + 1 as `rest_offset_group` from `orders` where (`orders`.`team_id` = ?)) as `orders` group by `orders`.`status` order by case when min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) + max(case when abs(`rest_offset_group`) < 1 then `rest_offset_group` end) * (max(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) - min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end)) is null then 1 else 0 end asc, min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) + max(case when abs(`rest_offset_group`) < 1 then `rest_offset_group` end) * (max(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) - min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end)) desc, case when `orders`.`status` is null then 1 else 0 end asc, `orders`.`status` asc limit 101',
            'pgsql' => 'select "orders"."status" as "group_key", percentile_cont(0.5) within group (order by "orders"."price") as "group_0" from "orders" where ("orders"."team_id" = ?) group by "orders"."status" order by case when percentile_cont(0.5) within group (order by "orders"."price") is null then 1 else 0 end asc, percentile_cont(0.5) within group (order by "orders"."price") desc, case when "orders"."status" is null then 1 else 0 end asc, "orders"."status" asc limit 101',
        ], [1]];
    }

    #[Test]
    #[DataProvider('partitions')]
    public function it_computes_a_percentile_for_each_group(string $helper, string $groupBy, string $column, float $percentile, string $parts): void
    {
        $this->serve(OrderPartition::make($helper, $groupBy, $column, percentile: $percentile));

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":['.$parts.'],"other":null}}', $response->getContent());
    }

    /** @return iterable<string, array{string, string, string, float, string}> */
    public static function partitions(): iterable
    {
        yield 'median by an enum' => ['median', 'status', 'price', 0.5, '{"key":"pending","label":"Pending","value":70.0},{"key":"refunded","label":"Refunded","value":60.0},{"key":null,"label":null,"value":50.0},{"key":"paid","label":"Paid","value":20.0}'];
        yield 'a percentile by a text column' => ['percentile', 'channel', 'price', 0.75, '{"key":"shop","label":"shop","value":80.0},{"key":"web","label":"web","value":50.0}'];
        yield 'a group without values last' => ['median', 'status', 'rating', 0.5, '{"key":"refunded","label":"Refunded","value":5.0},{"key":"paid","label":"Paid","value":2.5},{"key":"pending","label":"Pending","value":2.0},{"key":null,"label":null,"value":null}'];
    }

    #[Test]
    #[DataProvider('folds')]
    public function it_computes_the_percentile_of_the_folded_rows(int $limit, string $json): void
    {
        $this->serve(OrderPartition::make('median')->limit($limit));

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{int, string}> */
    public static function folds(): iterable
    {
        yield 'the null group folded' => [2, '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":70.0},{"key":"refunded","label":"Refunded","value":60.0}],"other":{"label":"Other","value":25.0}}}'];
        yield 'the null group kept' => [3, '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":70.0},{"key":"refunded","label":"Refunded","value":60.0},{"key":null,"label":null,"value":50.0}],"other":{"label":"Other","value":20.0}}}'];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('comparedPartitions')]
    public function it_computes_the_percentile_of_each_part_over_the_previous_period(Metric $metric, array $body, string $json): void
    {
        $this->serve($metric);

        $response = $this->metric('order-partition', $body)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{Metric, array<string, mixed>, string}> */
    public static function comparedPartitions(): iterable
    {
        yield 'median by a text column' => [self::compared('median', 'channel'), [], '{"data":{"type":"partition","parts":[{"key":"web","label":"web","value":30.0,"previous":55.0},{"key":"shop","label":"shop","value":20.0,"previous":100.0}],"other":null}}'];
        yield 'median by an enum, a key missing from each period' => [self::compared('median', 'status'), [], '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":40.0,"previous":100.0},{"key":"paid","label":"Paid","value":20.0,"previous":null},{"key":"refunded","label":"Refunded","value":null,"previous":60.0},{"key":null,"label":null,"value":null,"previous":50.0}],"other":null}}'];
        yield 'an interpolated percentile' => [self::compared('percentile', 'channel', percentile: 0.75), [], '{"data":{"type":"partition","parts":[{"key":"web","label":"web","value":35.0,"previous":57.5},{"key":"shop","label":"shop","value":20.0,"previous":100.0}],"other":null}}'];
        yield 'a group without values in both periods' => [self::compared('median', 'channel', 'rating'), [], '{"data":{"type":"partition","parts":[{"key":"web","label":"web","value":2.0,"previous":5.0},{"key":"shop","label":"shop","value":null,"previous":null}],"other":null}}'];
        yield 'second range, no row before it' => [self::compared('median', 'channel'), ['range' => 90], '{"data":{"type":"partition","parts":[{"key":"shop","label":"shop","value":60.0,"previous":null},{"key":"web","label":"web","value":40.0,"previous":null}],"other":null}}'];
        yield 'the folded parts over both periods' => [self::compared('median', 'status')->limit(1), [], '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":40.0,"previous":100.0}],"other":{"label":"Other","value":20.0,"previous":55.0}}}'];
        yield 'folded parts without rows in the period' => [self::compared('median', 'status')->limit(2), [], '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":40.0,"previous":100.0},{"key":"paid","label":"Paid","value":20.0,"previous":null}],"other":{"label":"Other","value":null,"previous":55.0}}}'];
        yield 'the null key folded' => [self::compared('median', 'status')->limit(3), [], '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":40.0,"previous":100.0},{"key":"paid","label":"Paid","value":20.0,"previous":null},{"key":"refunded","label":"Refunded","value":null,"previous":60.0}],"other":{"label":"Other","value":null,"previous":50.0}}}'];
        yield 'every row and no date column without ranges' => [OrderPartition::make('median', 'channel', dateColumn: 'shipped_at')->limit(1), [], '{"data":{"type":"partition","parts":[{"key":"shop","label":"shop","value":60.0,"previous":null}],"other":{"label":"Other","value":40.0,"previous":null}}}'];
    }

    #[Test]
    public function it_ranks_the_current_period_then_reads_the_previous_period_of_the_ranked_keys(): void
    {
        $this->serve(self::compared('median', 'status'));
        $this->logQueries();

        $this->metric('order-partition')->assertOk();

        $sql = [
            'sqlite' => 'select "orders"."status" as "group_key", min(case when abs("rest_offset_group") < 1 then "orders"."price" end) + max(case when abs("rest_offset_group") < 1 then "rest_offset_group" end) * (max(case when abs("rest_offset_group") < 1 then "orders"."price" end) - min(case when abs("rest_offset_group") < 1 then "orders"."price" end)) as "group_0" from (select "orders".*, 0.5 * (count("orders"."price") over (partition by "orders"."status") - 1) - row_number() over (partition by "orders"."status" order by case when "orders"."price" is null then 1 else 0 end, "orders"."price") + 1 as "rest_offset_group" from "orders" where ("orders"."team_id" = ?) and %s) as "orders" group by "orders"."status"%s',
            'mysql' => 'select `orders`.`status` as `group_key`, min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) + max(case when abs(`rest_offset_group`) < 1 then `rest_offset_group` end) * (max(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) - min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end)) as `group_0` from (select `orders`.*, 0.5 * (count(`orders`.`price`) over (partition by `orders`.`status`) - 1) - row_number() over (partition by `orders`.`status` order by case when `orders`.`price` is null then 1 else 0 end, `orders`.`price`) + 1 as `rest_offset_group` from `orders` where (`orders`.`team_id` = ?) and %s) as `orders` group by `orders`.`status`%s',
            'pgsql' => 'select "orders"."status" as "group_key", percentile_cont(0.5) within group (order by "orders"."price") as "group_0" from "orders" where ("orders"."team_id" = ?) and %s group by "orders"."status"%s',
        ];
        $windows = [
            'sqlite' => ['"orders"."placed_at" between ? and ?', '"orders"."placed_at" >= ? and "orders"."placed_at" < ?'],
            'mysql' => ['`orders`.`placed_at` between ? and ?', '`orders`.`placed_at` >= ? and `orders`.`placed_at` < ?'],
            'pgsql' => ['"orders"."placed_at" between ? and ?', '"orders"."placed_at" >= ? and "orders"."placed_at" < ?'],
        ];
        $ranks = [
            'sqlite' => ['min(case when abs("rest_offset_group") < 1 then "orders"."price" end) + max(case when abs("rest_offset_group") < 1 then "rest_offset_group" end) * (max(case when abs("rest_offset_group") < 1 then "orders"."price" end) - min(case when abs("rest_offset_group") < 1 then "orders"."price" end))', '"orders"."status"'],
            'mysql' => ['min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) + max(case when abs(`rest_offset_group`) < 1 then `rest_offset_group` end) * (max(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end) - min(case when abs(`rest_offset_group`) < 1 then `orders`.`price` end))', '`orders`.`status`'],
            'pgsql' => ['percentile_cont(0.5) within group (order by "orders"."price")', '"orders"."status"'],
        ];
        $driver = DB::connection()->getDriverName();
        [$rank, $status] = $ranks[$driver];
        [$current, $previous] = $windows[$driver];
        $byValue = " order by case when {$rank} is null then 1 else 0 end asc, {$rank} desc, case when {$status} is null then 1 else 0 end asc, {$status} asc limit 101";
        $byKey = " order by case when {$status} is null then 1 else 0 end asc, {$status} asc limit 101";
        $queries = DB::getQueryLog();
        $this->assertSame([
            sprintf($sql[$driver], $current, $byValue),
            sprintf($sql[$driver], "{$previous} and {$status} in (?, ?)", ''),
            sprintf($sql[$driver], "{$previous} and ({$status} not in (?, ?) or {$status} is null)", $byKey),
        ], array_column($queries, 'query'));
        $this->assertSame([
            [1, '2026-10-08 12:00:00', '2026-11-07 12:00:00'],
            [1, '2026-09-08 12:00:00', '2026-10-08 12:00:00', 'pending', 'paid'],
            [1, '2026-09-08 12:00:00', '2026-10-08 12:00:00', 'pending', 'paid'],
        ], array_column($queries, 'bindings'));
    }

    /** @param array<string, mixed> $data */
    #[Test]
    #[DataProvider('trends')]
    public function it_computes_a_percentile_for_each_period(Metric $metric, array $data): void
    {
        $this->serve($metric);

        $response = $this->metric('order-trend')->assertOk();

        $this->assertSame((string) json_encode(['data' => ['type' => 'trend', ...$data]], JSON_PRESERVE_ZERO_FRACTION), $response->getContent());
    }

    /** @return iterable<string, array{Metric, array<string, mixed>}> */
    public static function trends(): iterable
    {
        $series = self::points(['2026-09' => 60.0, '2026-10' => 30.0, '2026-11' => 10.0]);
        $paid = ['key' => 'paid', 'label' => 'Paid', 'series' => self::points(['2026-09' => null, '2026-10' => 25.0, '2026-11' => 10.0])];
        $pending = ['key' => 'pending', 'label' => 'Pending', 'series' => self::points(['2026-09' => 100.0, '2026-10' => 40.0, '2026-11' => null])];
        $refunded = ['key' => 'refunded', 'label' => 'Refunded', 'series' => self::points(['2026-09' => 60.0, '2026-10' => null, '2026-11' => null])];
        $none = ['key' => null, 'label' => null, 'series' => self::points(['2026-09' => 50.0, '2026-10' => null, '2026-11' => null])];
        $days = ['2026-11-01' => 10.0, '2026-11-02' => null, '2026-11-03' => null, '2026-11-04' => null, '2026-11-05' => null, '2026-11-06' => null, '2026-11-07' => null];

        yield 'median by months' => [self::trend('medianByMonths'), ['series' => $series]];
        yield 'a percentile by months' => [self::trend('percentileByMonths', percentile: 0.75), ['series' => self::points(['2026-09' => 80.0, '2026-10' => 35.0, '2026-11' => 10.0])]];
        yield 'median by weeks' => [OrderTrend::make('medianByWeeks', 'price', 'placed_at', null, [3 => '3 weeks']), ['series' => self::points(['2026-10-19' => 30.0, '2026-10-26' => 15.0, '2026-11-02' => null])]];
        yield 'a percentile by days' => [OrderTrend::make('percentileByDays', 'price', 'placed_at', null, [7 => '7 days'], [], null, 0.75), ['series' => self::points($days)]];
        yield 'median by months for each status' => [self::trend('medianByMonths', 'status'), ['series' => $series, 'splits' => [$paid, $pending, $refunded, $none], 'other' => null]];
        yield 'the folded splits, the null one among them' => [self::trend('medianByMonths', 'status')->limit(1), ['series' => $series, 'splits' => [$pending], 'other' => ['label' => 'Other', 'series' => self::points(['2026-09' => 55.0, '2026-10' => 25.0, '2026-11' => 10.0])]]];
        yield 'the folded splits, the null one kept' => [self::trend('medianByMonths', 'status')->limit(3), ['series' => $series, 'splits' => [$pending, $refunded, $none], 'other' => ['label' => 'Other', 'series' => $paid['series']]]];
    }

    /**
     * @param  array<string, TableColumn>  $columns
     * @param  array<int, string>  $ranges
     */
    #[Test]
    #[DataProvider('tables')]
    public function it_ranks_the_groups_by_a_percentile(array $columns, string $orderBy, string $direction, array $ranges, string $json): void
    {
        $this->serve(OrderTable::make('customer', $columns, $orderBy, $direction, 'placed_at', $ranges));

        $response = $this->metric('order-table')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{array<string, TableColumn>, string, string, array<int, string>, string}> */
    public static function tables(): iterable
    {
        $prices = ['orders' => TableColumn::count(), 'median' => TableColumn::median('price'), 'p75' => TableColumn::percentile('price', 0.75)->label('P75')];
        $columns = [['name' => 'orders', 'label' => 'Orders', 'aggregate' => 'count'], ['name' => 'median', 'label' => 'Median', 'aggregate' => 'median'], ['name' => 'p75', 'label' => 'P75', 'aggregate' => 'percentile', 'percentile' => 0.75]];

        yield 'by a median, descending' => [$prices, 'median', 'desc', [], self::table($columns, [
            ['crane', ['orders' => 2, 'median' => 80.0, 'p75' => 90.0]],
            [null, ['orders' => 1, 'median' => 50.0, 'p75' => 50.0]],
            ['bolt', ['orders' => 2, 'median' => 35.0, 'p75' => 37.5]],
            ['acme', ['orders' => 2, 'median' => 15.0, 'p75' => 17.5]],
        ])];
        yield 'by a percentile, ascending' => [$prices, 'p75', 'asc', [], self::table($columns, [
            ['acme', ['orders' => 2, 'median' => 15.0, 'p75' => 17.5]],
            ['bolt', ['orders' => 2, 'median' => 35.0, 'p75' => 37.5]],
            [null, ['orders' => 1, 'median' => 50.0, 'p75' => 50.0]],
            ['crane', ['orders' => 2, 'median' => 80.0, 'p75' => 90.0]],
        ])];
        yield 'over the range of the request' => [$prices, 'median', 'desc', [30 => '30 days'], self::table($columns, [
            ['bolt', ['orders' => 2, 'median' => 35.0, 'p75' => 37.5]],
            ['acme', ['orders' => 2, 'median' => 15.0, 'p75' => 17.5]],
        ])];
        yield 'a group without values last' => [['rating' => TableColumn::median('rating')], 'rating', 'desc', [], self::table([['name' => 'rating', 'label' => 'Rating', 'aggregate' => 'median']], [
            ['crane', ['rating' => 5.0]],
            ['bolt', ['rating' => 3.0]],
            ['acme', ['rating' => 1.0]],
            [null, ['rating' => null]],
        ])];
    }

    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_percentile_outside_zero_and_one(Metric $metric, string $key, InvalidMetricException $exception): void
    {
        $this->withoutExceptionHandling();
        $this->serve($metric);

        $this->expectExceptionObject($exception);

        $this->metric($key);
    }

    /** @return iterable<string, array{Metric, string, InvalidMetricException}> */
    public static function misfits(): iterable
    {
        yield 'a value above one' => [OrderValue::make('percentile', 'price', 'placed_at', [], 1.5), 'order-value', InvalidMetricException::invalidPercentile(OrderValue::class, 'percentile', 1.5)];
        yield 'a partition below zero' => [OrderPartition::make('percentile', 'status', 'price', percentile: -0.1), 'order-partition', InvalidMetricException::invalidPercentile(OrderPartition::class, 'percentile', -0.1)];
        yield 'a trend in percent' => [self::trend('percentileByMonths', percentile: 90.0), 'order-trend', InvalidMetricException::invalidPercentile(OrderTrend::class, 'percentileByMonths', 90.0)];
        yield 'a table column above one' => [OrderTable::make('customer', ['p90' => TableColumn::percentile('price', 2.0)]), 'order-table', InvalidMetricException::invalidPercentile(OrderTable::class, 'rows', 2.0)];
        yield 'a median of a text field' => [OrderValue::make('median', 'reference'), 'order-value', InvalidMetricException::wrongField(OrderValue::class, 'median', 'aggregates', 'reference', 'text', 'a number')];
    }

    private static function compared(string $helper, string $groupBy, string $column = 'price', float $percentile = 0.5): OrderPartition
    {
        return OrderPartition::make($helper, $groupBy, $column, dateColumn: 'placed_at', declaredRanges: [30 => '30 days', 90 => '90 days'], percentile: $percentile);
    }

    private static function trend(string $helper, ?string $splitBy = null, float $percentile = 0.5): OrderTrend
    {
        return OrderTrend::make($helper, 'price', 'placed_at', $splitBy, [3 => '3 months'], [], null, $percentile);
    }

    /**
     * @param  array<string, float|null>  $points
     * @return list<array{label: string, value: float|null}>
     */
    private static function points(array $points): array
    {
        return array_map(fn (string $label, ?float $value): array => ['label' => $label, 'value' => $value], array_keys($points), array_values($points));
    }

    /**
     * @param  list<array<string, string|float>>  $columns
     * @param  list<array{string|null, array<string, float|int|null>}>  $rows
     */
    private static function table(array $columns, array $rows): string
    {
        $rows = array_map(fn (array $row): array => ['key' => $row[0], 'label' => $row[0], 'values' => $row[1]], $rows);

        return (string) json_encode(['data' => ['type' => 'table', 'columns' => $columns, 'rows' => $rows]], JSON_PRESERVE_ZERO_FRACTION);
    }
}
