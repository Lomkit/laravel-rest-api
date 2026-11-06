<?php

namespace Lomkit\Rest\Tests\Unit\Config;

use Illuminate\Config\Repository;
use Lomkit\Rest\Config\ConfigValue;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigValueTest extends TestCase
{
    private const array NAMES = ['authorized_to_view', 'authorized_to_create'];

    #[Test]
    #[DataProvider('booleans')]
    public function it_reads_a_boolean_from_each_accepted_value(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, ConfigValue::at('rest.authorizations.strict', $value)->boolean());
    }

    #[Test]
    #[DataProvider('notBooleans')]
    public function it_names_the_key_of_a_value_that_is_not_a_boolean(mixed $value): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.authorizations.strict] option must be a boolean.'));

        ConfigValue::at('rest.authorizations.strict', $value)->boolean();
    }

    #[Test]
    public function it_refuses_null_as_a_boolean(): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.authorizations.cache.enabled] option must be a boolean.'));

        ConfigValue::at('rest.authorizations.cache.enabled', null)->boolean();
    }

    #[Test]
    public function it_reads_the_value_of_a_key_from_the_repository(): void
    {
        $config = new Repository(['rest' => ['authorizations' => ['strict' => 'true']]]);

        $this->assertTrue(ConfigValue::of($config, 'rest.authorizations.strict')->boolean());
    }

    #[Test]
    public function it_names_a_key_missing_from_the_repository(): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.authorizations.strict] option must be a boolean.'));

        ConfigValue::of(new Repository(['rest' => []]), 'rest.authorizations.strict')->boolean();
    }

    #[Test]
    public function it_reads_an_integer_of_zero_or_more(): void
    {
        $this->assertSame(
            [0, 5, 0, 15],
            array_map(fn (int|string $value): int => ConfigValue::at('rest.limits.depths.filters', $value)->integer(), [0, 5, '0', '15']),
        );
    }

    #[Test]
    #[DataProvider('notIntegers')]
    public function it_names_the_key_of_a_value_that_is_not_an_integer_of_zero_or_more(mixed $value): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.limits.depths.filters] option must be an integer of 0 or more.'));

        ConfigValue::at('rest.limits.depths.filters', $value)->integer();
    }

    #[Test]
    public function it_reads_a_string_that_is_not_empty(): void
    {
        $this->assertSame('abilities', ConfigValue::at('rest.gates.key', 'abilities')->string());
    }

    #[Test]
    #[DataProvider('notStrings')]
    public function it_names_the_key_of_a_value_that_is_not_a_non_empty_string(mixed $value): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.gates.key] option must be a non-empty string.'));

        ConfigValue::at('rest.gates.key', $value)->string();
    }

    #[Test]
    public function it_reads_null_or_a_non_empty_string(): void
    {
        $this->assertSame(
            [null, 'redis'],
            array_map(fn (?string $value): ?string => ConfigValue::at('rest.authorizations.cache.store', $value)->nullableString(), [null, 'redis']),
        );
    }

    #[Test]
    #[DataProvider('notNullableStrings')]
    public function it_names_the_key_of_a_value_that_is_neither_null_nor_a_non_empty_string(mixed $value): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.authorizations.cache.store] option must be null or a non-empty string.'));

        ConfigValue::at('rest.authorizations.cache.store', $value)->nullableString();
    }

    #[Test]
    public function it_reads_a_map_of_strings_with_its_extra_entries(): void
    {
        $names = ['authorized_to_view' => 'can_view', 'authorized_to_create' => 'can_create', 'authorized_to_export' => 'can_export'];

        $this->assertSame($names, ConfigValue::at('rest.gates.names', $names)->strings(self::NAMES));
    }

    #[Test]
    #[DataProvider('notMaps')]
    public function it_names_the_key_of_a_value_that_is_not_a_map(mixed $value): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.gates.names] option must be a map of strings.'));

        ConfigValue::at('rest.gates.names', $value)->strings(self::NAMES);
    }

    #[Test]
    public function it_names_the_entry_of_a_map_that_is_not_a_string(): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.gates.names.authorized_to_create] option must be a string.'));

        ConfigValue::at('rest.gates.names', ['authorized_to_view' => 'can_view', 'authorized_to_create' => null])->strings(self::NAMES);
    }

    #[Test]
    public function it_names_a_required_entry_missing_from_a_map(): void
    {
        $this->expectExceptionObject(new InvalidConfigException('The [rest.gates.names.authorized_to_create] option must be a string.'));

        ConfigValue::at('rest.gates.names', ['authorized_to_view' => 'can_view'])->strings(self::NAMES);
    }

    /** @return array<string, array{mixed, bool}> */
    public static function booleans(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            'the string 1' => ['1', true],
            'the string 0' => ['0', false],
            'the string true' => ['true', true],
            'the string false' => ['false', false],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function notBooleans(): array
    {
        return [
            'the integer 1' => [1],
            'the integer 0' => [0],
            'an empty string' => [''],
            'the string yes' => ['yes'],
            'the string TRUE' => ['TRUE'],
            'an array' => [['enabled' => true]],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function notIntegers(): array
    {
        return [
            'a negative integer' => [-1],
            'a float' => [1.5],
            'a decimal string' => ['1.5'],
            'a negative string' => ['-1'],
            'a word' => ['five'],
            'a boolean' => [true],
            'null' => [null],
            'an empty string' => [''],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function notStrings(): array
    {
        return [
            'an empty string' => [''],
            'null' => [null],
            'an integer' => [5],
            'an array' => [['gates']],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function notNullableStrings(): array
    {
        return [
            'an empty string' => [''],
            'false' => [false],
            'an integer' => [5],
            'an array' => [['redis']],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function notMaps(): array
    {
        return [
            'a string' => ['authorized_to_view'],
            'null' => [null],
            'a list' => [['can_view', 'can_create']],
        ];
    }
}
