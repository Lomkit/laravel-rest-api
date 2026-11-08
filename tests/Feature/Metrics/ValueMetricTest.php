<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderValue;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TotalRevenue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ValueMetricTest extends SalesTestCase
{
    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('periods')]
    public function it_computes_a_value_and_the_same_value_over_the_previous_period(string $helper, string $column, array $body, string $json): void
    {
        $this->serve(OrderValue::make($helper, $column));

        $response = $this->metric('order-value', $body)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{string, string, array<string, mixed>, string}> */
    public static function periods(): iterable
    {
        yield 'count, first range' => ['count', 'price', [], '{"data":{"type":"value","value":3,"previous":2}}'];
        yield 'sum of a number' => ['sum', 'price', [], '{"data":{"type":"value","value":52.0,"previous":37.25}}'];
        yield 'average of a number' => ['average', 'price', [], '{"data":{"type":"value","value":17.333333333333332,"previous":18.625}}'];
        yield 'min of a number' => ['min', 'price', [], '{"data":{"type":"value","value":12.5,"previous":7.25}}'];
        yield 'max of a number' => ['max', 'price', [], '{"data":{"type":"value","value":20.0,"previous":30.0}}'];
        yield 'sum of an integer' => ['sum', 'quantity', [], '{"data":{"type":"value","value":6,"previous":5}}'];
        yield 'average of an integer' => ['average', 'quantity', [], '{"data":{"type":"value","value":2.0,"previous":2.5}}'];
        yield 'max of an integer' => ['max', 'quantity', [], '{"data":{"type":"value","value":3,"previous":4}}'];
        yield 'count, second range' => ['count', 'price', ['range' => 90], '{"data":{"type":"value","value":6,"previous":0}}'];
        yield 'sum, previous period without rows' => ['sum', 'price', ['range' => 90], '{"data":{"type":"value","value":134.25,"previous":0.0}}'];
        yield 'average, previous period without rows' => ['average', 'price', ['range' => 90], '{"data":{"type":"value","value":22.375,"previous":null}}'];
        yield 'min, previous period without rows' => ['min', 'price', ['range' => 90], '{"data":{"type":"value","value":7.25,"previous":null}}'];
        yield 'range sent as a string' => ['count', 'price', ['range' => '90'], '{"data":{"type":"value","value":6,"previous":0}}'];
    }

    #[Test]
    #[DataProvider('totals')]
    public function it_computes_a_value_over_every_row_without_ranges(string $helper, string $column, string $json): void
    {
        $this->serve(OrderValue::make($helper, $column, 'placed_at', []));

        $response = $this->metric('order-value')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function totals(): iterable
    {
        yield 'count' => ['count', 'price', '{"data":{"type":"value","value":7,"previous":null}}'];
        yield 'sum of a number' => ['sum', 'price', '{"data":{"type":"value","value":233.25,"previous":null}}'];
        yield 'average of a number' => ['average', 'price', '{"data":{"type":"value","value":33.32142857142857,"previous":null}}'];
        yield 'min of a number' => ['min', 'price', '{"data":{"type":"value","value":7.25,"previous":null}}'];
        yield 'max of a number' => ['max', 'price', '{"data":{"type":"value","value":99.0,"previous":null}}'];
        yield 'sum of an integer' => ['sum', 'quantity', '{"data":{"type":"value","value":22,"previous":null}}'];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $sql
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('queries')]
    public function it_aggregates_the_search_inside_the_perimeter_in_one_query(bool $ranged, array $body, string $json, array $sql, array $bindings): void
    {
        $this->serve($ranged ? TotalRevenue::make() : OrderValue::make('count', 'price', 'placed_at', []));
        $this->logQueries();

        $response = $this->metric($ranged ? 'total-revenue' : 'order-value', $body)->assertOk();

        $this->assertSame($json, $response->getContent());
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame($bindings, $queries[0]['bindings']);
    }

    /** @return iterable<string, array{bool, array<string, mixed>, string, array<string, string>, list<mixed>}> */
    public static function queries(): iterable
    {
        yield 'a range and a filter' => [true, ['search' => ['filters' => [['field' => 'status', 'value' => 'paid']]]], '{"data":{"type":"value","value":32.5,"previous":0.0}}', [
            'sqlite' => 'select sum(case when "orders"."placed_at" >= ? then "orders"."price" end) as "current_0", sum(case when "orders"."placed_at" < ? then "orders"."price" end) as "previous_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ?',
            'mysql' => 'select sum(case when `orders`.`placed_at` >= ? then `orders`.`price` end) as `current_0`, sum(case when `orders`.`placed_at` < ? then `orders`.`price` end) as `previous_0` from `orders` where (`orders`.`team_id` = ?) and (`orders`.`status` = ?) and `orders`.`placed_at` between ? and ?',
            'pgsql' => 'select sum(case when "orders"."placed_at" >= ? then "orders"."price" end) as "current_0", sum(case when "orders"."placed_at" < ? then "orders"."price" end) as "previous_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ?',
        ], ['2026-10-08 12:00:00', '2026-10-08 12:00:00', 1, 'paid', '2026-09-08 12:00:00', '2026-11-07 12:00:00']];
        yield 'a range, a filter and unknown keys' => [true, ['search' => ['filters' => [['field' => 'status', 'value' => 'paid']], 'filter' => [['field' => 'status', 'value' => 'refunded']]], 'timezon' => 'Europe/Paris'], '{"data":{"type":"value","value":32.5,"previous":0.0}}', [
            'sqlite' => 'select sum(case when "orders"."placed_at" >= ? then "orders"."price" end) as "current_0", sum(case when "orders"."placed_at" < ? then "orders"."price" end) as "previous_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ?',
            'mysql' => 'select sum(case when `orders`.`placed_at` >= ? then `orders`.`price` end) as `current_0`, sum(case when `orders`.`placed_at` < ? then `orders`.`price` end) as `previous_0` from `orders` where (`orders`.`team_id` = ?) and (`orders`.`status` = ?) and `orders`.`placed_at` between ? and ?',
            'pgsql' => 'select sum(case when "orders"."placed_at" >= ? then "orders"."price" end) as "current_0", sum(case when "orders"."placed_at" < ? then "orders"."price" end) as "previous_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ?',
        ], ['2026-10-08 12:00:00', '2026-10-08 12:00:00', 1, 'paid', '2026-09-08 12:00:00', '2026-11-07 12:00:00']];
        yield 'no range and a text' => [false, ['search' => ['text' => ['value' => 'A-100']]], '{"data":{"type":"value","value":7,"previous":null}}', [
            'sqlite' => 'select count(*) as "total_0" from "orders" where ("orders"."team_id" = ?) and (("orders"."reference" like ? escape \'!\'))',
            'mysql' => 'select count(*) as `total_0` from `orders` where (`orders`.`team_id` = ?) and ((`orders`.`reference` like ? escape \'!\'))',
            'pgsql' => 'select count(*) as "total_0" from "orders" where ("orders"."team_id" = ?) and (("orders"."reference" ilike ? escape \'!\'))',
        ], [1, '%A-100%']];
    }

    #[Test]
    public function it_refuses_the_search_keys_that_a_metric_does_not_run_and_ignores_unknown_ones(): void
    {
        $this->serve(OrderValue::make('count', 'price', 'placed_at', []));
        $this->logQueries();

        $this->metric('order-value', ['search' => ['limit' => 5, 'flter' => [], 'sorts' => [['field' => 'price']]]])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'The search.limit field is prohibited. (and 1 more error)',
                'errors' => [
                    'search.limit' => ['The search.limit field is prohibited.'],
                    'search.sorts' => ['The search.sorts field is prohibited.'],
                ],
            ]);
        $this->assertSame([], DB::getQueryLog());
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('ranges')]
    public function it_validates_the_range_of_the_request(array $ranges, array $body, string $message): void
    {
        $this->serve(OrderValue::make('count', 'price', 'placed_at', $ranges));
        $this->logQueries();

        $this->metric('order-value', $body)
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['range' => [$message]]]);
        $this->assertSame([], DB::getQueryLog());
    }

    /** @return iterable<string, array{array<int, string>, array<string, mixed>, string}> */
    public static function ranges(): iterable
    {
        yield 'a key that is not declared' => [[30 => '30 days', 90 => '90 days'], ['range' => 7], 'The selected range is invalid.'];
        yield 'a range on a metric without ranges' => [[], ['range' => 30], 'The range field is prohibited.'];
    }

    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_field_that_does_not_fit_the_helper(string $helper, string $column, string $dateColumn, InvalidMetricException $exception): void
    {
        $this->withoutExceptionHandling();
        $this->serve(OrderValue::make($helper, $column, $dateColumn));

        $this->expectExceptionObject($exception);

        $this->metric('order-value');
    }

    /** @return iterable<string, array{string, string, string, InvalidMetricException}> */
    public static function misfits(): iterable
    {
        yield 'sum of a text field' => ['sum', 'reference', 'placed_at', InvalidMetricException::wrongField(OrderValue::class, 'sum', 'aggregates', 'reference', 'text', 'a number')];
        yield 'average of an enum field' => ['average', 'status', 'placed_at', InvalidMetricException::wrongField(OrderValue::class, 'average', 'aggregates', 'status', 'enum', 'a number')];
        yield 'a range dated with an integer field' => ['count', 'price', 'quantity', InvalidMetricException::wrongField(OrderValue::class, 'count', 'dates its range with', 'quantity', 'integer', 'a date or datetime')];
        yield 'a field that is not declared' => ['sum', 'cost', 'placed_at', InvalidMetricException::unknownField(OrderValue::class, 'sum', 'aggregates', 'cost', MetricOrderResource::class)];
        yield 'a virtual field' => ['max', 'summary', 'placed_at', InvalidMetricException::unknownField(OrderValue::class, 'max', 'aggregates', 'summary', MetricOrderResource::class)];
    }

    #[Test]
    public function it_reads_no_date_column_without_ranges(): void
    {
        $this->serve(OrderValue::make('count', 'price', 'shipped_at', []));

        $response = $this->metric('order-value')->assertOk();

        $this->assertSame('{"data":{"type":"value","value":7,"previous":null}}', $response->getContent());
    }

    /** @param array<mixed> $ranges */
    #[Test]
    #[DataProvider('invalidRanges')]
    public function it_rejects_a_range_that_is_not_a_positive_integer_when_the_routes_register(array $ranges, string $key): void
    {
        $this->expectExceptionObject(InvalidMetricException::invalidRange(OrderValue::class, $key));

        $this->serve(OrderValue::make('count', 'price', 'placed_at', $ranges));
    }

    /** @return iterable<string, array{array<mixed>, string}> */
    public static function invalidRanges(): iterable
    {
        yield 'zero' => [[0 => 'Never'], '0'];
        yield 'a word' => [['mtd' => 'Month to date'], 'mtd'];
        yield 'a label that is not a string' => [[30 => 30], '30'];
    }

    #[Test]
    public function it_describes_the_ranges_of_a_metric(): void
    {
        $this->serve(TotalRevenue::make(), OrderValue::make('count', 'price', 'placed_at', []));

        $this->getJson('/metric-orders?with=metrics.meta,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.metrics', [
                ['name' => 'Total Revenue', 'uriKey' => 'total-revenue', 'type' => 'value', 'ranges' => [['value' => 30, 'label' => '30 days'], ['value' => 90, 'label' => '90 days']], 'meta' => [], 'authorized' => true],
                ['name' => 'Order Value', 'uriKey' => 'order-value', 'type' => 'value', 'ranges' => [], 'meta' => [], 'authorized' => true],
            ]);
    }
}
