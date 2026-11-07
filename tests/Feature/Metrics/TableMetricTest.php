<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Support\Facades\DB;
use LogicException;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\LatestTopCustomers;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\NamedCustomers;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderTable;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TopCustomers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TableMetricTest extends TableTestCase
{
    private const array SALES = ['orders' => ['Orders', 'count'], 'revenue' => ['Revenue', 'sum'], 'basket' => ['Basket', 'average']];

    private const array TOP = ['orders' => ['Orders', 'count'], 'revenue' => ['Revenue', 'sum'], 'basket' => ['Average basket', 'average']];

    /** @param array<string, TableColumn> $columns */
    #[Test]
    #[DataProvider('tables')]
    public function it_ranks_the_groups_by_one_of_their_columns(string $groupBy, array $columns, ?string $orderBy, string $direction, int $limit, string $json): void
    {
        $this->serve(OrderTable::make($groupBy, $columns, $orderBy, $direction)->limit($limit));

        $response = $this->metric('order-table')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{string, array<string, TableColumn>, ?string, string, int, string}> */
    public static function tables(): iterable
    {
        $sales = ['orders' => TableColumn::count(), 'revenue' => TableColumn::sum('price'), 'basket' => TableColumn::average('price')];

        yield 'by the first column, descending' => ['customer', $sales, null, 'desc', 5, self::table(self::SALES, [
            ['acme', 'acme', ['orders' => 3, 'revenue' => 175.0, 'basket' => 58.333333333333336]],
            ['bolt', 'bolt', ['orders' => 2, 'revenue' => 120.0, 'basket' => 60.0]],
            ['delta', 'delta', ['orders' => 2, 'revenue' => 60.0, 'basket' => 30.0]],
            ['crane', 'crane', ['orders' => 1, 'revenue' => 120.0, 'basket' => 120.0]],
            ['echo', 'echo', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
        ])];
        yield 'by a sum, ties by key, the null group last of its tie' => ['customer', $sales, 'revenue', 'desc', 10, self::table(self::SALES, [
            ['acme', 'acme', ['orders' => 3, 'revenue' => 175.0, 'basket' => 58.333333333333336]],
            ['bolt', 'bolt', ['orders' => 2, 'revenue' => 120.0, 'basket' => 60.0]],
            ['crane', 'crane', ['orders' => 1, 'revenue' => 120.0, 'basket' => 120.0]],
            ['delta', 'delta', ['orders' => 2, 'revenue' => 60.0, 'basket' => 30.0]],
            ['echo', 'echo', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            ['fox', 'fox', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            [null, null, ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
        ])];
        yield 'by an average' => ['customer', $sales, 'basket', 'desc', 10, self::table(self::SALES, [
            ['crane', 'crane', ['orders' => 1, 'revenue' => 120.0, 'basket' => 120.0]],
            ['bolt', 'bolt', ['orders' => 2, 'revenue' => 120.0, 'basket' => 60.0]],
            ['acme', 'acme', ['orders' => 3, 'revenue' => 175.0, 'basket' => 58.333333333333336]],
            ['echo', 'echo', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            ['fox', 'fox', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            [null, null, ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            ['delta', 'delta', ['orders' => 2, 'revenue' => 60.0, 'basket' => 30.0]],
        ])];
        yield 'by a sum, ascending' => ['customer', $sales, 'revenue', 'asc', 10, self::table(self::SALES, [
            ['echo', 'echo', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            ['fox', 'fox', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            [null, null, ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            ['delta', 'delta', ['orders' => 2, 'revenue' => 60.0, 'basket' => 30.0]],
            ['bolt', 'bolt', ['orders' => 2, 'revenue' => 120.0, 'basket' => 60.0]],
            ['crane', 'crane', ['orders' => 1, 'revenue' => 120.0, 'basket' => 120.0]],
            ['acme', 'acme', ['orders' => 3, 'revenue' => 175.0, 'basket' => 58.333333333333336]],
        ])];
        yield 'an average without values last, descending' => ['customer', ['rating' => TableColumn::average('rating')], null, 'desc', 5, self::table(['rating' => ['Rating', 'average']], [
            ['echo', 'echo', ['rating' => 5.0]],
            ['acme', 'acme', ['rating' => 4.5]],
            ['bolt', 'bolt', ['rating' => 3.0]],
            ['delta', 'delta', ['rating' => 3.0]],
            [null, null, ['rating' => 1.0]],
        ])];
        yield 'an average without values last, ascending' => ['customer', ['rating' => TableColumn::average('rating')], null, 'asc', 5, self::table(['rating' => ['Rating', 'average']], [
            [null, null, ['rating' => 1.0]],
            ['bolt', 'bolt', ['rating' => 3.0]],
            ['delta', 'delta', ['rating' => 3.0]],
            ['acme', 'acme', ['rating' => 4.5]],
            ['echo', 'echo', ['rating' => 5.0]],
        ])];
        yield 'a sum without values ranks as 0, ascending' => ['customer', ['rating' => TableColumn::sum('rating')], null, 'asc', 3, self::table(['rating' => ['Rating', 'sum']], [
            ['crane', 'crane', ['rating' => 0]],
            ['fox', 'fox', ['rating' => 0]],
            [null, null, ['rating' => 1]],
        ])];
        yield 'the min and the max of an integer' => ['customer', ['fewest' => TableColumn::min('quantity'), 'most' => TableColumn::max('quantity')], 'most', 'desc', 10, self::table(['fewest' => ['Fewest', 'min'], 'most' => ['Most', 'max']], [
            ['bolt', 'bolt', ['fewest' => 2, 'most' => 4]],
            ['crane', 'crane', ['fewest' => 3, 'most' => 3]],
            ['acme', 'acme', ['fewest' => 1, 'most' => 2]],
            ['fox', 'fox', ['fewest' => 2, 'most' => 2]],
            ['delta', 'delta', ['fewest' => 1, 'most' => 1]],
            ['echo', 'echo', ['fewest' => 1, 'most' => 1]],
            [null, null, ['fewest' => 1, 'most' => 1]],
        ])];
        yield 'grouped by an enum' => ['status', $sales, 'revenue', 'desc', 10, self::table(self::SALES, [
            ['paid', 'Paid', ['orders' => 7, 'revenue' => 470.0, 'basket' => 67.14285714285714]],
            ['pending', 'Pending', ['orders' => 2, 'revenue' => 85.0, 'basket' => 42.5]],
            [null, null, ['orders' => 1, 'revenue' => 30.0, 'basket' => 30.0]],
            ['refunded', 'Refunded', ['orders' => 1, 'revenue' => 25.0, 'basket' => 25.0]],
        ])];
    }

    #[Test]
    #[DataProvider('limits')]
    public function it_keeps_the_rows_up_to_its_limit(int $limit, string $json): void
    {
        $this->serve(OrderTable::make('customer', ['revenue' => TableColumn::sum('price')])->limit($limit));

        $response = $this->metric('order-table')->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{int, string}> */
    public static function limits(): iterable
    {
        $rows = [
            ['acme', 'acme', ['revenue' => 175.0]],
            ['bolt', 'bolt', ['revenue' => 120.0]],
            ['crane', 'crane', ['revenue' => 120.0]],
            ['delta', 'delta', ['revenue' => 60.0]],
            ['echo', 'echo', ['revenue' => 45.0]],
            ['fox', 'fox', ['revenue' => 45.0]],
            [null, null, ['revenue' => 45.0]],
        ];

        yield 'one row' => [1, self::table(['revenue' => ['Revenue', 'sum']], array_slice($rows, 0, 1))];
        yield 'a tie at the limit, kept by key' => [5, self::table(['revenue' => ['Revenue', 'sum']], array_slice($rows, 0, 5))];
        yield 'the largest limit' => [100, self::table(['revenue' => ['Revenue', 'sum']], $rows)];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('ranges')]
    public function it_ranks_the_rows_of_the_range_of_the_request(array $body, string $json): void
    {
        $this->serve(TopCustomers::make());

        $response = $this->metric('top-customers', $body)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function ranges(): iterable
    {
        yield 'the first range' => [[], self::table(self::TOP, [
            ['acme', 'acme', ['orders' => 2, 'revenue' => 150.0, 'basket' => 75.0]],
            ['bolt', 'bolt', ['orders' => 2, 'revenue' => 120.0, 'basket' => 60.0]],
            ['crane', 'crane', ['orders' => 1, 'revenue' => 120.0, 'basket' => 120.0]],
            ['echo', 'echo', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            ['fox', 'fox', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
        ])];
        yield 'the second range' => [['range' => 365], self::table(self::TOP, [
            ['acme', 'acme', ['orders' => 3, 'revenue' => 175.0, 'basket' => 58.333333333333336]],
            ['bolt', 'bolt', ['orders' => 2, 'revenue' => 120.0, 'basket' => 60.0]],
            ['crane', 'crane', ['orders' => 1, 'revenue' => 120.0, 'basket' => 120.0]],
            ['delta', 'delta', ['orders' => 2, 'revenue' => 60.0, 'basket' => 30.0]],
            ['echo', 'echo', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
        ])];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $sql
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('queries')]
    public function it_ranks_the_search_inside_the_perimeter_in_one_query(Metric $metric, string $key, array $body, string $json, array $sql, array $bindings): void
    {
        $this->serve($metric);
        $this->logQueries();

        $response = $this->metric($key, $body)->assertOk();

        $this->assertSame($json, $response->getContent());
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame($bindings, $queries[0]['bindings']);
    }

    /** @return iterable<string, array{Metric, string, array<string, mixed>, string, array<string, string>, list<mixed>}> */
    public static function queries(): iterable
    {
        yield 'a range and a filter' => [TopCustomers::make(), 'top-customers', ['search' => ['filters' => [['field' => 'status', 'value' => 'paid']]]], self::table(self::TOP, [
            ['acme', 'acme', ['orders' => 2, 'revenue' => 150.0, 'basket' => 75.0]],
            ['crane', 'crane', ['orders' => 1, 'revenue' => 120.0, 'basket' => 120.0]],
            ['bolt', 'bolt', ['orders' => 1, 'revenue' => 80.0, 'basket' => 80.0]],
            ['fox', 'fox', ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
            [null, null, ['orders' => 1, 'revenue' => 45.0, 'basket' => 45.0]],
        ]), [
            'sqlite' => 'select "orders"."customer" as "group_key", count(*) as "orders_0", sum("orders"."price") as "revenue_0", sum("orders"."price") as "basket_0", count("orders"."price") as "basket_1" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ? group by "orders"."customer" order by coalesce(sum("orders"."price"), 0) desc, case when "orders"."customer" is null then 1 else 0 end asc, "orders"."customer" asc limit 5',
            'mysql' => 'select `orders`.`customer` as `group_key`, count(*) as `orders_0`, sum(`orders`.`price`) as `revenue_0`, sum(`orders`.`price`) as `basket_0`, count(`orders`.`price`) as `basket_1` from `orders` where (`orders`.`team_id` = ?) and (`orders`.`status` = ?) and `orders`.`placed_at` between ? and ? group by `orders`.`customer` order by coalesce(sum(`orders`.`price`), 0) desc, case when `orders`.`customer` is null then 1 else 0 end asc, `orders`.`customer` asc limit 5',
            'pgsql' => 'select "orders"."customer" as "group_key", count(*) as "orders_0", sum("orders"."price") as "revenue_0", sum("orders"."price") as "basket_0", count("orders"."price") as "basket_1" from "orders" where ("orders"."team_id" = ?) and ("orders"."status" = ?) and "orders"."placed_at" between ? and ? group by "orders"."customer" order by coalesce(sum("orders"."price"), 0) desc, case when "orders"."customer" is null then 1 else 0 end asc, "orders"."customer" asc limit 5',
        ], [1, 'paid', '2026-10-08 12:00:00', '2026-11-07 12:00:00']];
        yield 'an average without ranges and a text' => [OrderTable::make('customer', ['rating' => TableColumn::average('rating')]), 'order-table', ['search' => ['text' => ['value' => 'C-2']]], self::table(['rating' => ['Rating', 'average']], [
            ['echo', 'echo', ['rating' => 5.0]],
            ['acme', 'acme', ['rating' => 4.5]],
            ['bolt', 'bolt', ['rating' => 3.0]],
            ['delta', 'delta', ['rating' => 3.0]],
            ['crane', 'crane', ['rating' => null]],
        ]), [
            'sqlite' => 'select "orders"."customer" as "group_key", sum("orders"."rating") as "rating_0", count("orders"."rating") as "rating_1" from "orders" where ("orders"."team_id" = ?) and (("orders"."reference" like ? escape \'!\')) group by "orders"."customer" order by case when avg("orders"."rating") is null then 1 else 0 end asc, avg("orders"."rating") desc, case when "orders"."customer" is null then 1 else 0 end asc, "orders"."customer" asc limit 10',
            'mysql' => 'select `orders`.`customer` as `group_key`, sum(`orders`.`rating`) as `rating_0`, count(`orders`.`rating`) as `rating_1` from `orders` where (`orders`.`team_id` = ?) and ((`orders`.`reference` like ? escape \'!\')) group by `orders`.`customer` order by case when avg(`orders`.`rating`) is null then 1 else 0 end asc, avg(`orders`.`rating`) desc, case when `orders`.`customer` is null then 1 else 0 end asc, `orders`.`customer` asc limit 10',
            'pgsql' => 'select "orders"."customer" as "group_key", sum("orders"."rating") as "rating_0", count("orders"."rating") as "rating_1" from "orders" where ("orders"."team_id" = ?) and (("orders"."reference" ilike ? escape \'!\')) group by "orders"."customer" order by case when avg("orders"."rating") is null then 1 else 0 end asc, avg("orders"."rating") desc, case when "orders"."customer" is null then 1 else 0 end asc, "orders"."customer" asc limit 10',
        ], [1, '%C-2%']];
    }

    #[Test]
    public function it_ranks_the_rows_whatever_the_order_that_the_metric_adds(): void
    {
        $this->serve(LatestTopCustomers::make());

        $response = $this->metric('latest-top-customers')->assertOk();

        $this->assertSame(self::table(['revenue' => ['Revenue', 'sum']], [
            ['acme', 'acme', ['revenue' => 175.0]],
            ['bolt', 'bolt', ['revenue' => 120.0]],
            ['crane', 'crane', ['revenue' => 120.0]],
            ['delta', 'delta', ['revenue' => 60.0]],
            ['echo', 'echo', ['revenue' => 45.0]],
            ['fox', 'fox', ['revenue' => 45.0]],
            [null, null, ['revenue' => 45.0]],
        ]), $response->getContent());
    }

    #[Test]
    #[DataProvider('labels')]
    public function it_renames_the_labels_of_the_rows(Metric $metric, string $key, string $json): void
    {
        $this->serve($metric);

        $response = $this->metric($key)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{Metric, string, string}> */
    public static function labels(): iterable
    {
        yield 'labels of some keys' => [OrderTable::make('status', ['orders' => TableColumn::count()->label('Number of orders')], labels: ['paid' => 'Payée', 'refunded' => 'Remboursée']), 'order-table', self::table(['orders' => ['Number of orders', 'count']], [
            ['paid', 'Payée', ['orders' => 7]],
            ['pending', 'Pending', ['orders' => 2]],
            ['refunded', 'Remboursée', ['orders' => 1]],
            [null, null, ['orders' => 1]],
        ])];
        yield 'labels read from the keys of the rows' => [NamedCustomers::make(), 'named-customers', self::table(['revenue' => ['Revenue', 'sum']], [
            ['acme', 'Acme Ltd', ['revenue' => 175.0]],
            ['bolt', 'Bolt Ltd', ['revenue' => 120.0]],
            ['crane', 'Crane Ltd', ['revenue' => 120.0]],
        ])];
    }

    /** @param array<array-key, TableColumn> $columns */
    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_table_that_does_not_fit(string $groupBy, array $columns, ?string $orderBy, string $direction, string $dateColumn, LogicException $exception): void
    {
        $this->withoutExceptionHandling();
        $ranges = $dateColumn === 'placed_at' ? [] : [30 => '30 days'];
        $this->serve(OrderTable::make($groupBy, $columns, $orderBy, $direction, $dateColumn, $ranges));

        $this->expectExceptionObject($exception);

        $this->metric('order-table');
    }

    /** @return iterable<string, array{string, array<array-key, TableColumn>, ?string, string, string, LogicException}> */
    public static function misfits(): iterable
    {
        $orders = ['orders' => TableColumn::count()];

        yield 'no column' => ['customer', [], null, 'desc', 'placed_at', InvalidMetricException::withoutColumns(OrderTable::class)];
        yield 'a column without a name' => ['customer', [TableColumn::count()], null, 'desc', 'placed_at', InvalidMetricException::invalidColumn(OrderTable::class, '0')];
        yield 'a column name with a space' => ['customer', ['Total revenue' => TableColumn::sum('price')], null, 'desc', 'placed_at', InvalidMetricException::invalidColumn(OrderTable::class, 'Total revenue')];
        yield 'an order by a column that the table does not have' => ['customer', [...$orders, 'revenue' => TableColumn::sum('price')], 'profit', 'desc', 'placed_at', InvalidMetricException::unknownOrder(OrderTable::class, 'profit', ['orders', 'revenue'])];
        yield 'an unknown direction' => ['customer', $orders, null, 'up', 'placed_at', InvalidFieldException::unknownSortDirection('up')];
        yield 'the sum of a text field' => ['customer', ['total' => TableColumn::sum('reference')], null, 'desc', 'placed_at', InvalidMetricException::wrongField(OrderTable::class, 'rows', 'aggregates', 'reference', 'text', 'a number')];
        yield 'a group by a datetime field' => ['placed_at', $orders, null, 'desc', 'placed_at', InvalidMetricException::wrongField(OrderTable::class, 'rows', 'groups by', 'placed_at', 'datetime', 'a text, enum, integer or boolean')];
        yield 'a range dated with an integer field' => ['customer', $orders, null, 'desc', 'quantity', InvalidMetricException::wrongField(OrderTable::class, 'rows', 'dates its range with', 'quantity', 'integer', 'a date or datetime')];
    }

    #[Test]
    #[DataProvider('outOfBounds')]
    public function it_rejects_a_limit_out_of_bounds_when_the_routes_register(int $limit): void
    {
        $this->expectExceptionObject(InvalidMetricException::invalidRowLimit(OrderTable::class, $limit, 100));

        $this->serve(OrderTable::make()->limit($limit));
    }

    /** @return iterable<string, array{int}> */
    public static function outOfBounds(): iterable
    {
        yield 'zero' => [0];
        yield 'over the largest limit' => [101];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('invalidRanges')]
    public function it_validates_the_range_of_a_table(Metric $metric, string $key, array $body, string $message): void
    {
        $this->serve($metric);
        $this->logQueries();

        $this->metric($key, $body)
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['range' => [$message]]]);
        $this->assertSame([], DB::getQueryLog());
    }

    /** @return iterable<string, array{Metric, string, array<string, mixed>, string}> */
    public static function invalidRanges(): iterable
    {
        yield 'a key that the table does not declare' => [TopCustomers::make(), 'top-customers', ['range' => 7], 'The selected range is invalid.'];
        yield 'a range on a table without ranges' => [OrderTable::make('customer', ['orders' => TableColumn::count()]), 'order-table', ['range' => 30], 'The range field is prohibited.'];
    }

    #[Test]
    public function it_describes_a_table(): void
    {
        $this->serve(TopCustomers::make());

        $this->getJson('/metric-orders')
            ->assertOk()
            ->assertJsonPath('data.metrics', [['name' => 'Top Customers', 'uriKey' => 'top-customers', 'type' => 'table', 'ranges' => [['value' => 30, 'label' => '30 days'], ['value' => 365, 'label' => '365 days']], 'meta' => []]]);
    }
}
