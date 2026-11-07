<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\StatusThenChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class HelperQueryTest extends SalesTestCase
{
    #[Test]
    #[DataProvider('metrics')]
    public function it_gives_each_helper_the_query_that_the_metric_receives(Metric $metric, string $key, string $json): void
    {
        $this->serve($metric);

        $response = $this->metric($key)->assertOk();

        $this->assertSame($json, $response->getContent());
    }

    /** @return iterable<string, array{Metric, string, string}> */
    public static function metrics(): iterable
    {
        yield 'a partition after another partition' => [StatusThenChannel::make(), 'status-then-channel', '{"data":{"type":"partition","parts":[{"key":"web","label":"web","value":4},{"key":"shop","label":"shop","value":2},{"key":null,"label":null,"value":1}],"other":null}}'];
    }
}
