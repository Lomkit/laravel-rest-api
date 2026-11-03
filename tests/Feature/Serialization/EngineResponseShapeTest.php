<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineResponseShapeTest extends EngineSearchTestCase
{
    #[Test]
    #[DataProvider('drivers')]
    public function it_renders_an_engine_page(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $found = $this->search('vouchers', ['text' => ['value' => 'report'], 'gates' => ['create', 'view'], 'selects' => [['field' => 'id']]]);
        $empty = $this->search('vouchers', ['text' => ['value' => 'zebra'], 'gates' => ['create']]);

        $found->assertOk();
        $this->assertSame(
            '{"current_page":1,"data":[{"id":5,"gates":{"authorized_to_view":{"allowed":true,"message":null}}},{"id":1,"gates":{"authorized_to_view":{"allowed":true,"message":null}}},{"id":3,"gates":{"authorized_to_view":{"allowed":true,"message":null}}}],"from":1,"last_page":1,"per_page":50,"to":3,"total":4,"meta":{"gates":{"authorized_to_create":{"allowed":true,"message":null}}}}',
            $found->getContent(),
        );
        $empty->assertOk();
        $this->assertSame(
            '{"current_page":1,"data":[],"from":null,"last_page":1,"per_page":50,"to":null,"total":0,"meta":{"gates":{"authorized_to_create":{"allowed":true,"message":null}}}}',
            $empty->getContent(),
        );
    }
}
