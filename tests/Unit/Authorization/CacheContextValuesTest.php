<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\CacheContext;
use Lomkit\Rest\Authorization\CacheContributions;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheContextValuesTest extends TestCase
{
    private const string RESOURCE = 'App\Rest\Resources\TicketResource';

    private const string ACL = 'Vendor\Acl\AccessControlExtension';

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

    #[Test]
    public function it_merges_and_sorts_extension_items(): void
    {
        $contributions = new CacheContributions(self::RESOURCE);
        $contributions->put('tenant', 'Vendor\Tenancy\TenantExtension', 'id', 3);
        $contributions->put('acl', self::ACL, 'rules', 'v1');
        $contributions->put('acl', self::ACL, 'weight', 1.5);

        $context = CacheContext::of(['team' => 7, 'locale' => 'fr'], self::RESOURCE, $contributions);

        $this->assertSame(['acl.rules' => 'v1', 'acl.weight' => 1.5, 'locale' => 'fr', 'team' => 7, 'tenant.id' => 3], $context->values);
        $this->assertSame(serialize(['acl.rules' => 'v1', 'acl.weight' => 1.5, 'locale' => 'fr', 'team' => 7, 'tenant.id' => 3]), $context->serialized());
    }

    #[Test]
    public function it_refuses_an_item_key_that_the_resource_returns(): void
    {
        $contributions = new CacheContributions(self::RESOURCE);
        $contributions->put('acl', self::ACL, 'rules', 'v1');

        try {
            CacheContext::of(['acl.rules' => 1], self::RESOURCE, $contributions);
            $this->fail('The key was not refused.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertEquals(InvalidAuthorizationExtensionException::contextKeyTaken('acl', self::ACL, self::RESOURCE, 'acl.rules'), $exception);
            $this->assertSame([true, false], [$exception->concerns(self::ACL), $exception->concerns('acl')]);
            $this->assertSame('The [acl] extension '.self::ACL.' puts the cache context key [acl.rules], which '.self::RESOURCE.' already returns from authorizationCacheContext(). Rename the key of the resource or of the extension.', $exception->getMessage());
        }
    }
}
