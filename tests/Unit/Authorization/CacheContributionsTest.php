<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Closure;
use Lomkit\Rest\Authorization\CacheContributions;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheContributionsTest extends TestCase
{
    private const string ACL = 'Vendor\Acl\AccessControlExtension';

    private const string TENANT = 'Vendor\Tenancy\TenantExtension';

    private const string FLAGS = 'Vendor\Flags\FlagExtension';

    private const array DECLARED = ['viewAny' => 300, 'view' => 60, 'update' => 120];

    #[Test]
    public function it_prefixes_each_key_with_the_extension_name(): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        $contributions->put('tenant', self::TENANT, 'id', 7);
        $contributions->put('acl', self::ACL, 'rules', 'v1');
        $contributions->put('acl', self::ACL, 'weight', 1.5);
        $contributions->put('flags', self::FLAGS, 'beta', false);
        $contributions->put('flags', self::FLAGS, 'region', null);

        $this->assertSame(['tenant.id' => 7, 'acl.rules' => 'v1', 'acl.weight' => 1.5, 'flags.beta' => false, 'flags.region' => null], $contributions->items());
    }

    #[Test]
    public function it_keeps_the_last_value_of_a_repeated_key(): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        $contributions->put('acl', self::ACL, 'rules', 'v1');
        $contributions->put('acl', self::ACL, 'rules', 'v2');

        $this->assertSame(['acl.rules' => 'v2'], $contributions->items());
    }

    #[Test]
    public function it_takes_the_minimum_duration(): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        $contributions->shorten('acl', self::ACL, 'viewAny', 120);
        $contributions->shorten('tenant', self::TENANT, 'viewAny', 30);
        $contributions->shorten('acl', self::ACL, 'update', 90);

        $this->assertSame(['viewAny' => 30, 'view' => 60, 'update' => 90], $contributions->narrow(self::DECLARED));
    }

    #[Test]
    public function it_never_lengthens_a_duration(): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        $contributions->shorten('acl', self::ACL, 'view', 3600);

        $this->assertSame(self::DECLARED, $contributions->narrow(self::DECLARED));
    }

    #[Test]
    public function it_forgets_before_it_shortens(): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        $contributions->shorten('acl', self::ACL, 'view', 10);
        $contributions->forget('view');
        $contributions->shorten('tenant', self::TENANT, 'view', 5);

        $this->assertSame(['viewAny' => 300, 'update' => 120], $contributions->narrow(self::DECLARED));
    }

    #[Test]
    public function it_ignores_an_ability_the_resource_does_not_cache(): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        $contributions->shorten('acl', self::ACL, 'escalate', 30);
        $contributions->forget('share');

        $this->assertSame(self::DECLARED, $contributions->narrow(self::DECLARED));
        $this->assertSame([], $contributions->narrow([]));
    }

    /** @param list<Closure(CacheContributions): void> $calls */
    #[Test]
    #[DataProvider('callOrders')]
    public function it_gives_the_same_result_in_any_order(array $calls): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        foreach ($calls as $call) {
            $call($contributions);
        }

        $this->assertSame(['viewAny' => 30, 'update' => 120], $contributions->narrow(self::DECLARED));
        $items = $contributions->items();
        ksort($items);
        $this->assertSame(['acl.rules' => 'v1', 'tenant.id' => 7], $items);
    }

    /** @return iterable<string, array{list<Closure(CacheContributions): void>}> */
    public static function callOrders(): iterable
    {
        $calls = [
            'a' => static function (CacheContributions $contributions): void {
                $contributions->shorten('acl', self::ACL, 'viewAny', 120);
                $contributions->put('acl', self::ACL, 'rules', 'v1');
            },
            'b' => static function (CacheContributions $contributions): void {
                $contributions->shorten('tenant', self::TENANT, 'viewAny', 30);
                $contributions->put('tenant', self::TENANT, 'id', 7);
            },
            'c' => static function (CacheContributions $contributions): void {
                $contributions->forget('view');
            },
        ];

        foreach (['abc', 'acb', 'bac', 'bca', 'cab', 'cba'] as $order) {
            yield $order => [array_map(static fn (string $name): Closure => $calls[$name], str_split($order))];
        }
    }

    #[Test]
    #[DataProvider('invalidKeys')]
    public function it_refuses_an_invalid_key(string $key): void
    {
        $this->expectExceptionObject(InvalidAuthorizationExtensionException::invalidContextKey('acl', self::ACL, TicketResource::class, $key));

        (new CacheContributions(TicketResource::class))->put('acl', self::ACL, $key, 1);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'a leading digit' => ['1tenant'];
        yield 'a dash' => ['tenant-id'];
        yield 'a dot' => ['tenant.id'];
        yield 'a trailing newline' => ["tenant\n"];
        yield '65 characters' => [str_repeat('a', 65)];
    }

    #[Test]
    public function it_accepts_a_key_of_64_characters(): void
    {
        $contributions = new CacheContributions(TicketResource::class);

        $contributions->put('acl', self::ACL, str_repeat('a', 64), 1);

        $this->assertSame(['acl.'.str_repeat('a', 64) => 1], $contributions->items());
    }

    #[Test]
    #[DataProvider('infiniteFloats')]
    public function it_refuses_a_float_that_is_not_finite(float $value): void
    {
        $this->expectExceptionObject(InvalidAuthorizationExtensionException::invalidContextValue('acl', self::ACL, TicketResource::class, 'weight'));

        (new CacheContributions(TicketResource::class))->put('acl', self::ACL, 'weight', $value);
    }

    /** @return iterable<string, array{float}> */
    public static function infiniteFloats(): iterable
    {
        yield 'not a number' => [NAN];
        yield 'infinity' => [INF];
        yield 'minus infinity' => [-INF];
    }

    #[Test]
    #[DataProvider('shortDurations')]
    public function it_refuses_a_duration_below_one_second_and_names_the_extension(int $seconds): void
    {
        try {
            (new CacheContributions(TicketResource::class))->shorten('acl', self::ACL, 'view', $seconds);
            $this->fail('The duration was not refused.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertSame([true, false], [$exception->concerns(self::ACL), $exception->concerns('acl')]);
            $this->assertSame('The [acl] extension '.self::ACL.' shortens the cache of the ability [view] of '.TicketResource::class." to {$seconds} seconds. Give 1 second or more, or call forget('view') to keep no decision.", $exception->getMessage());
        }
    }

    /** @return iterable<string, array{int}> */
    public static function shortDurations(): iterable
    {
        yield 'zero' => [0];
        yield 'a negative duration' => [-5];
    }
}
