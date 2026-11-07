<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\CachedAbilities;
use Lomkit\Rest\Exceptions\InvalidResourceException;
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
        $abilities = CachedAbilities::of(['viewAny' => 300, 'view' => 60], self::RESOURCE, static fn (): array => []);

        $this->assertSame([300, 60, null], [$abilities->seconds('viewAny'), $abilities->seconds('view'), $abilities->seconds('update')]);
    }

    #[Test]
    #[DataProvider('invalidDurations')]
    public function it_names_an_ability_whose_duration_is_not_a_number_of_seconds_of_one_or_more(mixed $seconds): void
    {
        $this->expectExceptionObject(new InvalidResourceException("The resource [App\Rest\Resources\TicketResource] caches the ability [view] in authorizationCache() without a valid duration. Give an integer number of seconds of 1 or more, for example ['view' => 300]."));

        CachedAbilities::of(['viewAny' => 300, 'view' => $seconds], self::RESOURCE, static fn (): array => []);
    }

    /** @param array<mixed> $declared */
    #[Test]
    #[DataProvider('invalidKeys')]
    public function it_names_a_key_that_is_not_the_name_of_an_ability(array $declared, string $key): void
    {
        $this->expectExceptionObject(new InvalidResourceException("The resource [App\Rest\Resources\TicketResource] returns the key [{$key}] from authorizationCache(). Key each number of seconds by the name of an ability, for example ['viewAny' => 300]."));

        CachedAbilities::of($declared, self::RESOURCE, static fn (): array => []);
    }

    #[Test]
    public function it_reads_the_context_only_when_the_resource_declares_an_ability(): void
    {
        $reads = 0;
        $context = function () use (&$reads): array {
            $reads++;

            return ['team' => 7];
        };

        $silent = CachedAbilities::of([], self::RESOURCE, $context);
        $declaring = CachedAbilities::of(['viewAny' => 300], self::RESOURCE, $context);

        $this->assertSame(['', serialize(['team' => 7]), 1], [$silent->context, $declaring->context, $reads]);
    }

    #[Test]
    public function it_keys_the_same_context_the_same_way_whatever_the_order_of_its_keys(): void
    {
        $first = CachedAbilities::of(['viewAny' => 300], self::RESOURCE, static fn (): array => ['team' => 7, 'locale' => 'fr', 'staff' => true, 'region' => null]);
        $second = CachedAbilities::of(['viewAny' => 300], self::RESOURCE, static fn (): array => ['staff' => true, 'region' => null, 'locale' => 'fr', 'team' => 7]);
        $other = CachedAbilities::of(['viewAny' => 300], self::RESOURCE, static fn (): array => ['team' => '7', 'locale' => 'fr', 'staff' => true, 'region' => null]);

        $this->assertSame($first->context, $second->context);
        $this->assertNotSame($first->context, $other->context);
    }

    #[Test]
    #[DataProvider('invalidContextValues')]
    public function it_names_a_context_value_that_is_not_an_integer_a_string_a_boolean_or_null(mixed $value): void
    {
        $this->expectExceptionObject(new InvalidResourceException("The resource [App\Rest\Resources\TicketResource] returns a value for the key [team] from authorizationCacheContext() that is not an integer, a string, a boolean or null. Give a scalar value, for example ['team' => 1]."));

        CachedAbilities::of(['viewAny' => 300], self::RESOURCE, static fn (): array => ['locale' => 'fr', 'team' => $value]);
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
}
