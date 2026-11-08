<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderProgress;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\SalesGoal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ProgressMetricTest extends SalesTestCase
{
    /**
     * @param  array<int, string>  $ranges
     * @param  array<string, mixed>  $body
     */
    #[Test]
    #[DataProvider('progresses')]
    public function it_gives_a_value_and_its_target(string $helper, string $column, int|float $target, array $ranges, array $body, string $json): void
    {
        $this->serve(OrderProgress::make($helper, $column, $target, 'placed_at', $ranges));

        $response = $this->metric('order-progress', $body)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{string, string, int|float, array<int, string>, array<string, mixed>, string}> */
    public static function progresses(): iterable
    {
        $ranges = [30 => '30 days', 90 => '90 days'];

        yield 'sum over the first range' => ['sum', 'price', 100, $ranges, [], '{"data":{"type":"progress","value":52.0,"target":100.0}}'];
        yield 'count over the second range' => ['count', 'price', 10, $ranges, ['range' => 90], '{"data":{"type":"progress","value":6,"target":10}}'];
        yield 'average of a number' => ['average', 'price', 20.5, $ranges, [], '{"data":{"type":"progress","value":17.333333333333332,"target":20.5}}'];
        yield 'max of an integer' => ['max', 'quantity', 5, $ranges, [], '{"data":{"type":"progress","value":3,"target":5}}'];
        yield 'every row without ranges' => ['sum', 'price', 250.0, [], [], '{"data":{"type":"progress","value":233.25,"target":250.0}}'];
    }

    #[Test]
    public function it_gives_a_target_that_the_metric_computes_in_one_query(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'goal' => 80.5]));
        $this->serve(SalesGoal::make());
        $this->logQueries();

        $response = $this->metric('sales-goal')->assertOk();

        $this->assertSame('{"data":{"type":"progress","value":52.0,"target":80.5}}', $response->getContent());
        $sql = [
            'sqlite' => 'select sum("orders"."price") as "total_0" from "orders" where ("orders"."team_id" = ?) and "orders"."placed_at" between ? and ?',
            'mysql' => 'select sum(`orders`.`price`) as `total_0` from `orders` where (`orders`.`team_id` = ?) and `orders`.`placed_at` between ? and ?',
            'pgsql' => 'select sum("orders"."price") as "total_0" from "orders" where ("orders"."team_id" = ?) and "orders"."placed_at" between ? and ?',
        ];
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame([1, '2026-10-08 12:00:00', '2026-11-07 12:00:00'], $queries[0]['bindings']);
    }

    #[Test]
    public function it_validates_the_range_of_a_progress(): void
    {
        $this->serve(OrderProgress::make());

        $this->metric('order-progress', ['range' => 7])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The selected range is invalid.', 'errors' => ['range' => ['The selected range is invalid.']]]);
    }

    #[Test]
    #[DataProvider('misfits')]
    public function it_refuses_a_field_that_does_not_fit_the_helper(string $helper, string $column, string $dateColumn, InvalidMetricException $exception): void
    {
        $this->withoutExceptionHandling();
        $this->serve(OrderProgress::make($helper, $column, 10, $dateColumn));

        $this->expectExceptionObject($exception);

        $this->metric('order-progress');
    }

    /** @return iterable<string, array{string, string, string, InvalidMetricException}> */
    public static function misfits(): iterable
    {
        yield 'the sum of a boolean field' => ['sum', 'gift', 'placed_at', InvalidMetricException::wrongField(OrderProgress::class, 'sum', 'aggregates', 'gift', 'boolean', 'a number')];
        yield 'a range dated with a text field' => ['count', 'price', 'reference', InvalidMetricException::wrongField(OrderProgress::class, 'count', 'dates its range with', 'reference', 'text', 'a date or datetime')];
    }

    #[Test]
    public function it_describes_a_progress(): void
    {
        $this->serve(SalesGoal::make());

        $this->getJson('/metric-orders?with=metrics.meta,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.metrics', [['name' => 'Sales Goal', 'uriKey' => 'sales-goal', 'type' => 'progress', 'ranges' => [['value' => 30, 'label' => '30 days']], 'meta' => [], 'authorized' => true]]);
    }
}
