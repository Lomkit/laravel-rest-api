<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\LatestOrdersByStatus;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderPartition;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrdersByStatus;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TopChannels;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PartitionMetricTest extends SalesTestCase
{
    #[Test]
    #[DataProvider('partitions')]
    public function it_splits_the_rows_of_the_search_by_a_column(string $helper, string $groupBy, string $column, string $parts): void
    {
        $this->serve(OrderPartition::make($helper, $groupBy, $column));

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":['.$parts.'],"other":null}}', $response->getContent());
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function partitions(): iterable
    {
        yield 'count by an enum, the null group last of its tie' => ['count', 'status', 'price', '{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1},{"key":"refunded","label":"Refunded","value":1},{"key":null,"label":null,"value":1}'];
        yield 'count by a text column' => ['count', 'channel', 'price', '{"key":"web","label":"web","value":4},{"key":"shop","label":"shop","value":2},{"key":null,"label":null,"value":1}'];
        yield 'count by an integer column' => ['count', 'quantity', 'price', '{"key":1,"label":"1","value":2},{"key":2,"label":"2","value":2},{"key":3,"label":"3","value":1},{"key":4,"label":"4","value":1},{"key":9,"label":"9","value":1}'];
        yield 'count by a boolean column' => ['count', 'gift', 'price', '{"key":false,"label":"false","value":5},{"key":true,"label":"true","value":2}'];
        yield 'sum of a number by an enum' => ['sum', 'status', 'price', '{"key":"paid","label":"Paid","value":176.5},{"key":null,"label":null,"value":30.0},{"key":"pending","label":"Pending","value":19.5},{"key":"refunded","label":"Refunded","value":7.25}'];
        yield 'average of a number by an enum' => ['average', 'status', 'price', '{"key":"paid","label":"Paid","value":44.125},{"key":null,"label":null,"value":30.0},{"key":"pending","label":"Pending","value":19.5},{"key":"refunded","label":"Refunded","value":7.25}'];
        yield 'min of an integer by a text column' => ['min', 'channel', 'quantity', '{"key":"web","label":"web","value":2},{"key":"shop","label":"shop","value":1},{"key":null,"label":null,"value":1}'];
        yield 'max of a number by a text column' => ['max', 'channel', 'price', '{"key":"web","label":"web","value":99.0},{"key":"shop","label":"shop","value":45.0},{"key":null,"label":null,"value":7.25}'];
    }

    #[Test]
    #[DataProvider('limits')]
    public function it_keeps_the_largest_parts_and_folds_the_others_into_one(Metric $metric, string $key, string $json): void
    {
        $this->serve($metric);

        $response = $this->metric($key)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{Metric, string, string}> */
    public static function limits(): iterable
    {
        yield 'a tie at the limit, kept by key' => [OrderPartition::make()->limit(2), 'order-partition', '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1}],"other":{"label":"Other","value":2}}}'];
        yield 'the null group folded' => [OrderPartition::make()->limit(3), 'order-partition', '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1},{"key":"refunded","label":"Refunded","value":1}],"other":{"label":"Other","value":1}}}'];
        yield 'an average over the folded rows' => [OrderPartition::make('average')->limit(1), 'order-partition', '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":44.125}],"other":{"label":"Other","value":18.916666666666668}}}'];
        yield 'the smallest of the folded parts' => [OrderPartition::make('min')->limit(1), 'order-partition', '{"data":{"type":"partition","parts":[{"key":null,"label":null,"value":30.0}],"other":{"label":"Other","value":7.25}}}'];
        yield 'fewer parts than the limit' => [OrderPartition::make()->limit(10), 'order-partition', '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1},{"key":"refunded","label":"Refunded","value":1},{"key":null,"label":null,"value":1}],"other":null}}'];
        yield 'a limit declared on the class' => [TopChannels::make(), 'top-channels', '{"data":{"type":"partition","parts":[{"key":"web","label":"web","value":4}],"other":{"label":"Other","value":3}}}'];
    }

    /** @param array<int|string, string> $labels */
    #[Test]
    #[DataProvider('labels')]
    public function it_renames_the_labels_of_the_parts(string $groupBy, array $labels, ?string $other, string $json): void
    {
        $this->serve(OrderPartition::make('count', $groupBy, 'price', $labels, $other)->limit(2));

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{string, array<int|string, string>, ?string, string}> */
    public static function labels(): iterable
    {
        yield 'enum keys and the other part' => ['status', ['paid' => 'Payée', 'refunded' => 'Remboursée'], 'Autre', '{"data":{"type":"partition","parts":[{"key":"paid","label":"Pay\u00e9e","value":4},{"key":"pending","label":"Pending","value":1}],"other":{"label":"Autre","value":2}}}'];
        yield 'boolean keys' => ['gift', [1 => 'Gift', 0 => 'Order'], null, '{"data":{"type":"partition","parts":[{"key":false,"label":"Order","value":5},{"key":true,"label":"Gift","value":2}],"other":null}}'];
    }

    #[Test]
    public function it_groups_the_search_inside_the_perimeter_in_one_query(): void
    {
        $this->serve(OrdersByStatus::make());
        $this->logQueries();

        $response = $this->metric('orders-by-status', ['search' => ['filters' => [['field' => 'channel', 'value' => 'web']]]])->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":2},{"key":"pending","label":"Pending","value":1},{"key":null,"label":null,"value":1}],"other":null}}', $response->getContent());
        $sql = [
            'sqlite' => 'select "orders"."status" as "group_key", count(*) as "group_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."channel" = ?) group by "orders"."status"',
            'mysql' => 'select `orders`.`status` as `group_key`, count(*) as `group_0` from `orders` where (`orders`.`team_id` = ?) and (`orders`.`channel` = ?) group by `orders`.`status`',
            'pgsql' => 'select "orders"."status" as "group_key", count(*) as "group_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."channel" = ?) group by "orders"."status"',
        ];
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame([1, 'web'], $queries[0]['bindings']);
    }

    #[Test]
    public function it_groups_the_rows_whatever_the_order_that_the_metric_adds(): void
    {
        $this->serve(LatestOrdersByStatus::make());

        $response = $this->metric('latest-orders-by-status')->assertOk();

        $this->assertSame('{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1},{"key":"refunded","label":"Refunded","value":1},{"key":null,"label":null,"value":1}],"other":null}}', $response->getContent());
    }

    #[Test]
    public function it_refuses_a_range_on_a_partition(): void
    {
        $this->serve(OrdersByStatus::make());

        $this->metric('orders-by-status', ['range' => 30])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The range field is prohibited.', 'errors' => ['range' => ['The range field is prohibited.']]]);
    }

    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_column_that_does_not_fit_the_helper(string $helper, string $groupBy, string $column, InvalidMetricException $exception): void
    {
        $this->withoutExceptionHandling();
        $this->serve(OrderPartition::make($helper, $groupBy, $column));

        $this->expectExceptionObject($exception);

        $this->metric('order-partition');
    }

    /** @return iterable<string, array{string, string, string, InvalidMetricException}> */
    public static function misfits(): iterable
    {
        yield 'a datetime group' => ['count', 'placed_at', 'price', InvalidMetricException::wrongField(OrderPartition::class, 'count', 'groups by', 'placed_at', 'datetime', 'a text, enum, integer or boolean')];
        yield 'a number group' => ['count', 'price', 'price', InvalidMetricException::wrongField(OrderPartition::class, 'count', 'groups by', 'price', 'number', 'a text, enum, integer or boolean')];
        yield 'a group that is not declared' => ['count', 'region', 'price', InvalidMetricException::unknownField(OrderPartition::class, 'count', 'groups by', 'region', MetricOrderResource::class)];
        yield 'the sum of a text field' => ['sum', 'status', 'reference', InvalidMetricException::wrongField(OrderPartition::class, 'sum', 'aggregates', 'reference', 'text', 'a number')];
    }

    #[Test]
    public function it_rejects_a_limit_below_one_when_the_routes_register(): void
    {
        $this->expectExceptionObject(InvalidMetricException::invalidLimit(OrderPartition::class, 0));

        $this->serve(OrderPartition::make()->limit(0));
    }

    #[Test]
    public function it_describes_a_partition(): void
    {
        $this->serve(OrdersByStatus::make()->limit(3));

        $this->getJson('/metric-orders')
            ->assertOk()
            ->assertJsonPath('data.metrics', [['name' => 'Orders By Status', 'uriKey' => 'orders-by-status', 'type' => 'partition', 'ranges' => [], 'meta' => [], 'authorized' => true]]);
    }
}
