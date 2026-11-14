<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentationLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstAugmentation;
use Lomkit\Rest\Tests\Feature\Metrics\SalesTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderCount;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['first' => FirstAugmentation::class])]
class AugmentedMetricTest extends SalesTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        AugmentationLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_does_not_augment_the_rows_of_a_metric(): void
    {
        AugmentationLog::reset();
        FirstAugmentation::$augment = static function (): void {
            AugmentationLog::$aliases[] = 'called';
        };
        $this->serve(OrderCount::make());
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs(new GenericUser(['id' => 1]))->postJson('/metric-orders/metrics/order-count')->assertOk();

        $this->assertSame([], AugmentationLog::$aliases);
        $this->assertNotSame([], DB::getQueryLog());
    }
}
