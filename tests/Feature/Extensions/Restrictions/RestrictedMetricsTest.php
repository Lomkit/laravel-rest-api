<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherTeamsExtension;
use Lomkit\Rest\Tests\Feature\Metrics\SalesTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\SharedOrderCount;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
#[WithConfig('rest.extensions', [OtherTeamsExtension::class])]
class RestrictedMetricsTest extends SalesTestCase
{
    use ResetsExtensionStubs;

    #[Test]
    public function it_keeps_a_global_result_of_each_restriction_apart(): void
    {
        $this->serve(SharedOrderCount::make());
        $keys = [];
        Event::listen(RetrievingKey::class, function (RetrievingKey $event) use (&$keys): void {
            if (str_starts_with($event->key, 'rest.metrics.')) {
                $keys[] = $event->key;
            }
        });
        $contents = [];

        foreach ([1, 2, 1, 2] as $id) {
            $contents[] = $this->actingAs(new GenericUser(['id' => $id]))->postJson('/metric-orders/metrics/shared-order-count')->getContent();
        }

        $this->assertSame([self::value(0), self::value(7), self::value(0), self::value(7)], $contents);
        $this->assertSame([$keys[0], $keys[1], $keys[0], $keys[1]], $keys);
        $this->assertNotSame($keys[0], $keys[1]);
    }

    #[Test]
    public function it_counts_the_metric_rows_inside_the_restriction(): void
    {
        $this->serve(SharedOrderCount::make());
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs(new GenericUser(['id' => 2]))->postJson('/metric-orders/metrics/shared-order-count');

        $response->assertOk()->assertExactJson(['data' => ['type' => 'value', 'value' => 7, 'previous' => null]]);
        $this->assertSame(
            ['select count(*) as total_0 from orders where ((orders.team_id = ?) and (orders.team_id != ?))'],
            array_values(array_filter(str_replace(['"', '`'], '', array_column(DB::getQueryLog(), 'query')), fn (string $query): bool => str_contains($query, 'orders'))),
        );
    }

    private static function value(int $value): string
    {
        return '{"data":{"type":"value","value":'.$value.',"previous":null}}';
    }
}
