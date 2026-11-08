<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderPartition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PartitionPreviousPeriodTest extends SalesTestCase
{
    private const array RANGES = [30 => '30 days', 90 => '90 days'];

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('periods')]
    public function it_gives_each_part_its_value_over_the_previous_period(string $helper, string $groupBy, string $column, array $body, string $parts): void
    {
        $this->serve(OrderPartition::make($helper, $groupBy, $column, dateColumn: 'placed_at', declaredRanges: self::RANGES));

        $response = $this->metric('order-partition', $body)->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":['.$parts.'],"other":null}}', $response->getContent());
    }

    /** @return iterable<string, array{string, string, string, array<string, mixed>, string}> */
    public static function periods(): iterable
    {
        yield 'count by a text column, a key missing from each period' => ['count', 'channel', 'price', [], '{"key":"web","label":"web","value":2,"previous":1},{"key":"shop","label":"shop","value":1,"previous":null},{"key":null,"label":null,"value":null,"previous":1}'];
        yield 'count by an enum, the parts without a value last' => ['count', 'status', 'price', [], '{"key":"paid","label":"Paid","value":2,"previous":null},{"key":"pending","label":"Pending","value":1,"previous":null},{"key":"refunded","label":"Refunded","value":null,"previous":1},{"key":null,"label":null,"value":null,"previous":1}'];
        yield 'count by a boolean column' => ['count', 'gift', 'price', [], '{"key":false,"label":"false","value":2,"previous":1},{"key":true,"label":"true","value":1,"previous":1}'];
        yield 'sum of a number' => ['sum', 'channel', 'price', [], '{"key":"web","label":"web","value":39.5,"previous":30.0},{"key":"shop","label":"shop","value":12.5,"previous":null},{"key":null,"label":null,"value":null,"previous":7.25}'];
        yield 'sum of a column without values, rows in the period' => ['sum', 'channel', 'rating', [], '{"key":"shop","label":"shop","value":0,"previous":null},{"key":"web","label":"web","value":0,"previous":0},{"key":null,"label":null,"value":null,"previous":0}'];
        yield 'average of a number' => ['average', 'channel', 'price', [], '{"key":"web","label":"web","value":19.75,"previous":30.0},{"key":"shop","label":"shop","value":12.5,"previous":null},{"key":null,"label":null,"value":null,"previous":7.25}'];
        yield 'min of an integer' => ['min', 'channel', 'quantity', [], '{"key":"web","label":"web","value":2,"previous":4},{"key":"shop","label":"shop","value":1,"previous":null},{"key":null,"label":null,"value":null,"previous":1}'];
        yield 'max of a number' => ['max', 'status', 'price', [], '{"key":"paid","label":"Paid","value":20.0,"previous":null},{"key":"pending","label":"Pending","value":19.5,"previous":null},{"key":"refunded","label":"Refunded","value":null,"previous":7.25},{"key":null,"label":null,"value":null,"previous":30.0}'];
        yield 'second range, no row before it' => ['count', 'channel', 'price', ['range' => 90], '{"key":"web","label":"web","value":3,"previous":null},{"key":"shop","label":"shop","value":2,"previous":null},{"key":null,"label":null,"value":1,"previous":null}'];
    }

    #[Test]
    #[DataProvider('limits')]
    public function it_folds_the_previous_period_of_the_folded_parts(Metric $metric, string $json): void
    {
        $this->serve($metric);

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{Metric, string}> */
    public static function limits(): iterable
    {
        yield 'a count' => [OrderPartition::make('count', 'status', dateColumn: 'placed_at', declaredRanges: self::RANGES)->limit(1), '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":2,"previous":null}],"other":{"label":"Other","value":1,"previous":2}}}'];
        yield 'folded parts without rows in the period' => [OrderPartition::make('count', 'status', dateColumn: 'placed_at', declaredRanges: self::RANGES)->limit(2), '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":2,"previous":null},{"key":"pending","label":"Pending","value":1,"previous":null}],"other":{"label":"Other","value":null,"previous":2}}}'];
        yield 'an average over the folded rows' => [OrderPartition::make('average', 'status', dateColumn: 'placed_at', declaredRanges: self::RANGES)->limit(1), '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":19.5,"previous":null}],"other":{"label":"Other","value":16.25,"previous":18.625}}}'];
        yield 'the smallest of the folded parts' => [OrderPartition::make('min', 'status', dateColumn: 'placed_at', declaredRanges: self::RANGES)->limit(2), '{"data":{"type":"partition","parts":[{"key":"pending","label":"Pending","value":19.5,"previous":null},{"key":"paid","label":"Paid","value":12.5,"previous":null}],"other":{"label":"Other","value":null,"previous":7.25}}}'];
        yield 'fewer parts than the limit' => [OrderPartition::make('count', 'channel', dateColumn: 'placed_at', declaredRanges: self::RANGES)->limit(3), '{"data":{"type":"partition","parts":[{"key":"web","label":"web","value":2,"previous":1},{"key":"shop","label":"shop","value":1,"previous":null},{"key":null,"label":null,"value":null,"previous":1}],"other":null}}'];
    }

    #[Test]
    public function it_keeps_the_previous_period_when_it_renames_the_labels(): void
    {
        $this->serve(OrderPartition::make('count', 'status', 'price', ['paid' => 'Payée'], 'Autre', 'placed_at', self::RANGES)->limit(2));

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":[{"key":"paid","label":"Pay\u00e9e","value":2,"previous":null},{"key":"pending","label":"Pending","value":1,"previous":null}],"other":{"label":"Autre","value":null,"previous":2}}}', $response->getContent());
    }

    #[Test]
    public function it_groups_both_periods_inside_the_perimeter_in_one_query(): void
    {
        $this->serve(OrderPartition::make('sum', 'status', 'price', dateColumn: 'placed_at', declaredRanges: self::RANGES));
        $this->logQueries();

        $response = $this->metric('order-partition', ['search' => ['filters' => [['field' => 'channel', 'value' => 'web']]]])->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":20.0,"previous":null},{"key":"pending","label":"Pending","value":19.5,"previous":null},{"key":null,"label":null,"value":null,"previous":30.0}],"other":null}}', $response->getContent());
        $sql = [
            'sqlite' => 'select "orders"."status" as "group_key", sum(case when "orders"."placed_at" >= ? then "orders"."price" end) as "current_0", sum(case when "orders"."placed_at" < ? then "orders"."price" end) as "previous_0", count(case when "orders"."placed_at" >= ? then 1 end) as "current_rows_0", count(case when "orders"."placed_at" < ? then 1 end) as "previous_rows_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."channel" = ?) and "orders"."placed_at" between ? and ? group by "orders"."status" order by case when case when count(case when "orders"."placed_at" >= ? then 1 end) > 0 then coalesce(sum(case when "orders"."placed_at" >= ? then "orders"."price" end), 0) end is null then 1 else 0 end asc, case when count(case when "orders"."placed_at" >= ? then 1 end) > 0 then coalesce(sum(case when "orders"."placed_at" >= ? then "orders"."price" end), 0) end desc, case when "orders"."status" is null then 1 else 0 end asc, "orders"."status" asc limit 101',
            'mysql' => 'select `orders`.`status` as `group_key`, sum(case when `orders`.`placed_at` >= ? then `orders`.`price` end) as `current_0`, sum(case when `orders`.`placed_at` < ? then `orders`.`price` end) as `previous_0`, count(case when `orders`.`placed_at` >= ? then 1 end) as `current_rows_0`, count(case when `orders`.`placed_at` < ? then 1 end) as `previous_rows_0` from `orders` where (`orders`.`team_id` = ?) and (`orders`.`channel` = ?) and `orders`.`placed_at` between ? and ? group by `orders`.`status` order by case when case when count(case when `orders`.`placed_at` >= ? then 1 end) > 0 then coalesce(sum(case when `orders`.`placed_at` >= ? then `orders`.`price` end), 0) end is null then 1 else 0 end asc, case when count(case when `orders`.`placed_at` >= ? then 1 end) > 0 then coalesce(sum(case when `orders`.`placed_at` >= ? then `orders`.`price` end), 0) end desc, case when `orders`.`status` is null then 1 else 0 end asc, `orders`.`status` asc limit 101',
            'pgsql' => 'select "orders"."status" as "group_key", sum(case when "orders"."placed_at" >= ? then "orders"."price" end) as "current_0", sum(case when "orders"."placed_at" < ? then "orders"."price" end) as "previous_0", count(case when "orders"."placed_at" >= ? then 1 end) as "current_rows_0", count(case when "orders"."placed_at" < ? then 1 end) as "previous_rows_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."channel" = ?) and "orders"."placed_at" between ? and ? group by "orders"."status" order by case when case when count(case when "orders"."placed_at" >= ? then 1 end) > 0 then coalesce(sum(case when "orders"."placed_at" >= ? then "orders"."price" end), 0) end is null then 1 else 0 end asc, case when count(case when "orders"."placed_at" >= ? then 1 end) > 0 then coalesce(sum(case when "orders"."placed_at" >= ? then "orders"."price" end), 0) end desc, case when "orders"."status" is null then 1 else 0 end asc, "orders"."status" asc limit 101',
        ];
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame(['2026-10-08 12:00:00', '2026-10-08 12:00:00', '2026-10-08 12:00:00', '2026-10-08 12:00:00', 1, 'web', '2026-09-08 12:00:00', '2026-11-07 12:00:00', '2026-10-08 12:00:00', '2026-10-08 12:00:00', '2026-10-08 12:00:00', '2026-10-08 12:00:00'], $queries[0]['bindings']);
    }

    #[Test]
    public function it_reads_every_row_and_no_date_column_without_ranges(): void
    {
        $this->serve(OrderPartition::make('count', 'channel', dateColumn: 'shipped_at')->limit(2));

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":[{"key":"web","label":"web","value":4,"previous":null},{"key":"shop","label":"shop","value":2,"previous":null}],"other":{"label":"Other","value":1,"previous":null}}}', $response->getContent());
    }

    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_date_column_that_does_not_fit(string $dateColumn, InvalidMetricException $exception): void
    {
        $this->withoutExceptionHandling();
        $this->serve(OrderPartition::make('count', 'status', dateColumn: $dateColumn, declaredRanges: self::RANGES));

        $this->expectExceptionObject($exception);

        $this->metric('order-partition');
    }

    /** @return iterable<string, array{string, InvalidMetricException}> */
    public static function misfits(): iterable
    {
        yield 'an integer field' => ['quantity', InvalidMetricException::wrongField(OrderPartition::class, 'count', 'dates its range with', 'quantity', 'integer', 'a date or datetime')];
        yield 'a field that is not declared' => ['shipped_at', InvalidMetricException::unknownField(OrderPartition::class, 'count', 'dates its range with', 'shipped_at', MetricOrderResource::class)];
    }
}
