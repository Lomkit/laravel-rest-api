<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Closure;
use Lomkit\Rest\Authorization\CacheContext;
use Lomkit\Rest\Authorization\CacheContributions;
use Lomkit\Rest\Authorization\CachedAbilities;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class CachedAbilitiesTest extends TestCase
{
    private const string RESOURCE = 'App\Rest\Resources\TicketResource';

    #[Test]
    public function it_gives_the_seconds_of_each_declared_ability(): void
    {
        $abilities = self::abilities(['viewAny' => 300, 'view' => 60], self::RESOURCE, static fn (): CacheContext => CacheContext::of([], self::RESOURCE));

        $this->assertSame([300, 60, null], [$abilities->seconds('viewAny'), $abilities->seconds('view'), $abilities->seconds('update')]);
    }

    #[Test]
    #[DataProvider('invalidDurations')]
    public function it_names_an_ability_whose_duration_is_not_a_number_of_seconds_of_one_or_more(mixed $seconds): void
    {
        $this->expectExceptionObject(new InvalidResourceException("The resource [App\Rest\Resources\TicketResource] caches the ability [view] in authorizationCache() without a valid duration. Give an integer number of seconds of 1 or more, for example ['view' => 300]."));

        self::abilities(['viewAny' => 300, 'view' => $seconds], self::RESOURCE, static fn (): CacheContext => CacheContext::of([], self::RESOURCE));
    }

    /** @param array<mixed> $declared */
    #[Test]
    #[DataProvider('invalidKeys')]
    public function it_names_a_key_that_is_not_the_name_of_an_ability(array $declared, string $key): void
    {
        $this->expectExceptionObject(new InvalidResourceException("The resource [App\Rest\Resources\TicketResource] returns the key [{$key}] from authorizationCache(). Key each number of seconds by the name of an ability, for example ['viewAny' => 300]."));

        self::abilities($declared, self::RESOURCE, static fn (): CacheContext => CacheContext::of([], self::RESOURCE));
    }

    #[Test]
    public function it_keys_the_same_context_the_same_way_whatever_the_order_of_its_keys(): void
    {
        $first = self::abilities(['viewAny' => 300], self::RESOURCE, static fn (): CacheContext => CacheContext::of(['team' => 7, 'locale' => 'fr', 'staff' => true, 'region' => null], self::RESOURCE));
        $second = self::abilities(['viewAny' => 300], self::RESOURCE, static fn (): CacheContext => CacheContext::of(['staff' => true, 'region' => null, 'locale' => 'fr', 'team' => 7], self::RESOURCE));
        $other = self::abilities(['viewAny' => 300], self::RESOURCE, static fn (): CacheContext => CacheContext::of(['team' => '7', 'locale' => 'fr', 'staff' => true, 'region' => null], self::RESOURCE));

        $this->assertSame($first->context, $second->context);
        $this->assertNotSame($first->context, $other->context);
    }

    #[Test]
    #[DataProvider('invalidContextValues')]
    public function it_names_a_context_value_that_is_not_an_integer_a_string_a_boolean_or_null(mixed $value): void
    {
        $this->expectExceptionObject(new InvalidResourceException("The resource [App\Rest\Resources\TicketResource] returns a value for the key [team] from authorizationCacheContext() that is not an integer, a string, a boolean or null. Give a scalar value, for example ['team' => 1]."));

        self::abilities(['viewAny' => 300], self::RESOURCE, static fn (): CacheContext => CacheContext::of(['locale' => 'fr', 'team' => $value], self::RESOURCE));
    }

    #[Test]
    public function it_reads_the_context_and_the_extensions_only_when_an_ability_is_declared(): void
    {
        $reads = [0, 0];
        $context = function () use (&$reads): CacheContext {
            $reads[0]++;

            return CacheContext::of(['team' => 7], self::RESOURCE);
        };
        $contributions = function () use (&$reads): CacheContributions {
            $reads[1]++;

            return new CacheContributions(TicketResource::class);
        };

        $silent = CachedAbilities::of([], self::RESOURCE, $context, $contributions);
        $this->assertSame(['', [0, 0]], [$silent->context, $reads]);

        $declaring = CachedAbilities::of(['viewAny' => 300], self::RESOURCE, $context, $contributions);
        $this->assertSame([serialize(['team' => 7]), [1, 1]], [$declaring->context, $reads]);
    }

    #[Test]
    public function it_reads_no_context_when_extensions_forget_every_ability(): void
    {
        $reads = 0;
        $contributions = new CacheContributions(TicketResource::class);
        $contributions->forget('viewAny');
        $contributions->forget('view');

        $abilities = self::abilities(['viewAny' => 300, 'view' => 60], self::RESOURCE, function () use (&$reads): CacheContext {
            $reads++;

            return CacheContext::of(['team' => 7], self::RESOURCE);
        }, $contributions);

        $this->assertSame(['', 0, null, null], [$abilities->context, $reads, $abilities->seconds('viewAny'), $abilities->seconds('view')]);
    }

    #[Test]
    public function it_shortens_and_forgets_with_the_contributions(): void
    {
        $contributions = new CacheContributions(TicketResource::class);
        $contributions->shorten('acl', 'Vendor\Acl\AccessControlExtension', 'viewAny', 30);
        $contributions->forget('view');

        $abilities = self::abilities(['viewAny' => 300, 'view' => 60, 'update' => 120], self::RESOURCE, static fn (): CacheContext => CacheContext::of(['team' => 7], self::RESOURCE), $contributions);

        $this->assertSame([30, null, 120], [$abilities->seconds('viewAny'), $abilities->seconds('view'), $abilities->seconds('update')]);
        $this->assertSame(serialize(['team' => 7]), $abilities->context);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidContextValues(): array
    {
        return [
            'a list' => [[1]],
            'a float' => [1.5],
            'an object' => [new stdClass],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function invalidDurations(): array
    {
        return [
            'zero' => [0],
            'a negative number' => [-5],
            'a numeric string' => ['300'],
            'a float' => [1.5],
            'null' => [null],
        ];
    }

    /** @return array<string, array{array<mixed>, string}> */
    public static function invalidKeys(): array
    {
        return [
            'a list of abilities' => [['viewAny'], '0'],
            'an empty name' => [['' => 300], ''],
        ];
    }

    /**
     * @param  array<mixed>  $declared
     * @param  Closure(): CacheContext  $context
     */
    private static function abilities(array $declared, string $resource, Closure $context, ?CacheContributions $contributions = null): CachedAbilities
    {
        return CachedAbilities::of($declared, $resource, $context, static fn (): CacheContributions => $contributions ?? new CacheContributions(TicketResource::class));
    }
}
