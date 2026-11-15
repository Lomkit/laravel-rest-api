<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\CacheContext;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheContextValuesTest extends TestCase
{
    private const string RESOURCE = 'App\Rest\Resources\TicketResource';

    #[Test]
    public function it_serializes_the_declared_values_sorted_by_key(): void
    {
        $context = CacheContext::of(['team' => 7, 'locale' => 'fr'], self::RESOURCE);

        $this->assertSame(['locale' => 'fr', 'team' => 7], $context->values);
        $this->assertSame('a:2:{s:6:"locale";s:2:"fr";s:4:"team";i:7;}', $context->serialized());
    }

    #[Test]
    public function it_serializes_no_value_as_an_empty_array(): void
    {
        $context = CacheContext::of([], self::RESOURCE);

        $this->assertTrue($context->isEmpty());
        $this->assertSame('a:0:{}', $context->serialized());
        $this->assertFalse(CacheContext::of(['team' => null], self::RESOURCE)->isEmpty());
    }

    #[Test]
    public function it_keeps_refusing_a_float_from_the_resource(): void
    {
        $this->expectExceptionObject(InvalidResourceException::invalidCacheContext(self::RESOURCE, 'weight'));

        CacheContext::of(['weight' => 1.5], self::RESOURCE);
    }
}
