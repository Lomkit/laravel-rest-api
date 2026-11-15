<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ConfiguredTeamExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ElsewhereRestriction;
use Lomkit\Rest\Tests\Feature\Metrics\SalesTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderCount;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class RestrictedMetricKeyTest extends SalesTestCase
{
    /** @var list<string> */
    private array $keys = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->serve(OrderCount::make()->cacheFor(300));
        Event::listen(RetrievingKey::class, function (RetrievingKey $event): void {
            if (str_starts_with($event->key, 'rest.metrics.')) {
                $this->keys[] = $event->key;
            }
        });
    }

    #[Test]
    #[WithConfig('rest.extensions', [ConfiguredTeamExtension::class])]
    public function it_keeps_a_user_result_of_each_restriction_apart(): void
    {
        $contents = [];

        foreach ([1, 2, 1] as $team) {
            config(['extension_fixtures.team' => $team]);
            $contents[] = $this->actingAs(new GenericUser(['id' => 1]))->postJson('/metric-orders/metrics/order-count')->getContent();
        }

        $this->assertSame([self::value(7), self::value(0), self::value(7)], $contents);
        $this->assertSame([$this->keys[0], $this->keys[1], $this->keys[0]], $this->keys);
        $this->assertNotSame($this->keys[0], $this->keys[1]);
    }

    #[Test]
    public function it_keeps_the_key_of_a_metric_that_no_restriction_reaches(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]))->postJson('/metric-orders/metrics/order-count')->assertOk();
        config(['rest.extensions' => [ElsewhereRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1]))->postJson('/metric-orders/metrics/order-count')->assertOk();

        $this->assertCount(2, $this->keys);
        $this->assertSame($this->keys[0], $this->keys[1]);
    }

    private static function value(int $value): string
    {
        return '{"data":{"type":"value","value":'.$value.',"previous":null}}';
    }
}
