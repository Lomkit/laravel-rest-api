<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderPartition;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrdersByStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PartitionGroupLimitTest extends SalesTestCase
{
    #[Test]
    #[DataProvider('limits')]
    public function it_keeps_the_largest_parts_up_to_the_group_size_of_the_config(int $groups, Metric $metric, string $json): void
    {
        config()->set('rest.limits.sizes.groups', $groups);
        $this->serve($metric);

        $response = $this->metric('order-partition')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{int, Metric, string}> */
    public static function limits(): iterable
    {
        yield 'a count, a tie at the limit kept by key' => [2, OrderPartition::make(), '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1}],"other":{"label":"Other","value":2}}}'];
        yield 'a median' => [2, OrderPartition::make('median'), '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":32.5},{"key":null,"label":null,"value":30.0}],"other":{"label":"Other","value":13.375}}}'];
        yield 'a previous period' => [1, OrderPartition::make('count', 'status', dateColumn: 'placed_at', declaredRanges: [30 => '30 days']), '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":2,"previous":null}],"other":{"label":"Other","value":1,"previous":2}}}'];
        yield 'a limit of the metric over the config' => [1, OrderPartition::make()->limit(3), '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1},{"key":"refunded","label":"Refunded","value":1}],"other":{"label":"Other","value":1}}}'];
        yield 'fewer parts than the group size' => [4, OrderPartition::make(), '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1},{"key":"refunded","label":"Refunded","value":1},{"key":null,"label":null,"value":1}],"other":null}}'];
    }

    #[Test]
    public function it_reads_one_group_more_than_it_keeps_then_the_folded_rows_in_their_own_query(): void
    {
        config()->set('rest.limits.sizes.groups', 2);
        $this->serve(OrdersByStatus::make());
        $this->logQueries();

        $this->metric('orders-by-status')->assertOk();

        $sql = [
            'sqlite' => [
                'select "orders"."status" as "group_key", count(*) as "group_0" from "orders" where ("orders"."team_id" = ?) group by "orders"."status" order by count(*) desc, case when "orders"."status" is null then 1 else 0 end asc, "orders"."status" asc limit 3',
                'select count(*) as "total_0", count(*) as "total_rows_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" not in (?, ?) or "orders"."status" is null)',
            ],
            'mysql' => [
                'select `orders`.`status` as `group_key`, count(*) as `group_0` from `orders` where (`orders`.`team_id` = ?) group by `orders`.`status` order by count(*) desc, case when `orders`.`status` is null then 1 else 0 end asc, `orders`.`status` asc limit 3',
                'select count(*) as `total_0`, count(*) as `total_rows_0` from `orders` where (`orders`.`team_id` = ?) and (`orders`.`status` not in (?, ?) or `orders`.`status` is null)',
            ],
            'pgsql' => [
                'select "orders"."status" as "group_key", count(*) as "group_0" from "orders" where ("orders"."team_id" = ?) group by "orders"."status" order by count(*) desc, case when "orders"."status" is null then 1 else 0 end asc, "orders"."status" asc limit 3',
                'select count(*) as "total_0", count(*) as "total_rows_0" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" not in (?, ?) or "orders"."status" is null)',
            ],
        ];
        $queries = DB::getQueryLog();
        $this->assertSame($sql[DB::connection()->getDriverName()], array_column($queries, 'query'));
        $this->assertSame([[1], [1, 'paid', 'pending']], array_column($queries, 'bindings'));
    }
}
