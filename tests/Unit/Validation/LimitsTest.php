<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Closure;
use InvalidArgumentException;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Validation\Limits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LimitsTest extends TestCase
{
    #[Test]
    public function it_reads_the_limits_of_the_package_config(): void
    {
        $limits = Limits::fromConfig($this->packageLimits());

        $this->assertSame(100, $limits->sizes->filters);
        $this->assertSame(20, $limits->sizes->sorts);
        $this->assertSame(100, $limits->sizes->selects);
        $this->assertSame(10, $limits->sizes->scopes);
        $this->assertSame(20, $limits->sizes->aggregates);
        $this->assertSame(20, $limits->sizes->includes);
        $this->assertSame(10, $limits->sizes->instructions);
        $this->assertSame(100, $limits->sizes->fields);
        $this->assertSame(100, $limits->sizes->values);
        $this->assertSame(100, $limits->sizes->mutate);
        $this->assertSame(100, $limits->sizes->relationEntries);
        $this->assertSame(1000, $limits->sizes->resources);
        $this->assertSame(100, $limits->sizes->jobs);
        $this->assertSame(['filters' => 1, 'includes' => 3, 'mutations' => 3], get_object_vars($limits->depths));
        $this->assertSame(['filters' => 500, 'entries' => 1000, 'relations' => 20], get_object_vars($limits->totals));
        $this->assertSame(['patterns' => 255], get_object_vars($limits->lengths));
    }

    #[Test]
    public function it_reads_a_limit_written_as_digits(): void
    {
        $limits = Limits::fromConfig($this->withLimit('sizes', 'filters', '50'));

        $this->assertSame(50, $limits->sizes->filters);
    }

    /** @param Closure(Limits): int $read */
    #[Test]
    #[DataProvider('keyPaths')]
    public function it_reads_each_limit_from_its_key_path(string $group, string $key, Closure $read): void
    {
        $this->assertSame(42, $read(Limits::fromConfig($this->withLimit($group, $key, '42'))));
        $this->assertSame(0, $read(Limits::fromConfig($this->withLimit($group, $key, 0))));
    }

    #[Test]
    #[DataProvider('keys')]
    public function it_names_the_key_path_of_a_missing_limit(string $group, string $key): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("The [rest.limits.{$group}.{$key}] option must be an integer of 0 or more."));

        Limits::fromConfig($this->withoutLimit($group, $key));
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function it_refuses_a_limit_that_is_not_an_integer_of_zero_or_more(bool $present, mixed $value): void
    {
        $config = $present ? $this->withLimit('sizes', 'filters', $value) : $this->withoutLimit('sizes', 'filters');

        $this->expectExceptionObject(new InvalidArgumentException('The [rest.limits.sizes.filters] option must be an integer of 0 or more.'));

        Limits::fromConfig($config);
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function it_refuses_a_total_that_is_not_an_integer_of_zero_or_more(bool $present, mixed $value): void
    {
        $config = $present ? $this->withLimit('totals', 'entries', $value) : $this->withoutLimit('totals', 'entries');

        $this->expectExceptionObject(new InvalidArgumentException('The [rest.limits.totals.entries] option must be an integer of 0 or more.'));

        Limits::fromConfig($config);
    }

    #[Test]
    public function it_reads_the_relation_filter_total(): void
    {
        $this->assertSame(42, Limits::fromConfig($this->withLimit('totals', 'relations', '42'))->totals->relations);
        $this->assertSame(1, Limits::fromConfig($this->withLimit('totals', 'relations', 1))->totals->relations);
    }

    #[Test]
    #[DataProvider('invalidRelationTotals')]
    public function it_refuses_a_relation_filter_total_that_is_not_an_integer_of_one_or_more(bool $present, mixed $value): void
    {
        $config = $present ? $this->withLimit('totals', 'relations', $value) : $this->withoutLimit('totals', 'relations');

        $this->expectExceptionObject(new InvalidArgumentException('The [rest.limits.totals.relations] option must be an integer of 1 or more.'));

        Limits::fromConfig($config);
    }

    #[Test]
    public function it_reads_the_job_limit(): void
    {
        $this->assertSame(42, Limits::fromConfig($this->withLimit('sizes', 'jobs', '42'))->sizes->jobs);
        $this->assertSame(1, Limits::fromConfig($this->withLimit('sizes', 'jobs', 1))->sizes->jobs);
    }

    #[Test]
    #[DataProvider('invalidRelationTotals')]
    public function it_refuses_a_job_limit_that_is_not_an_integer_of_one_or_more(bool $present, mixed $value): void
    {
        $config = $present ? $this->withLimit('sizes', 'jobs', $value) : $this->withoutLimit('sizes', 'jobs');

        $this->expectExceptionObject(new InvalidArgumentException('The [rest.limits.sizes.jobs] option must be an integer of 1 or more.'));

        Limits::fromConfig($config);
    }

    #[Test]
    #[DataProvider('unreadableGroups')]
    public function it_names_the_first_key_of_a_group_it_cannot_read(string $group, bool $present, string $path): void
    {
        $config = $this->packageLimits();
        $config[$group] = 3;

        if (! $present) {
            unset($config[$group]);
        }

        $this->expectExceptionObject(new InvalidArgumentException("The [{$path}] option must be an integer of 0 or more."));

        Limits::fromConfig($config);
    }

    #[Test]
    public function it_names_the_first_size_of_a_limits_config_that_is_not_an_array(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('The [rest.limits.sizes.filters] option must be an integer of 0 or more.'));

        Limits::fromConfig(null);
    }

    #[Test]
    #[DataProvider('unknownKeys')]
    public function it_names_an_unknown_key_of_a_group(string $group, int|string $key): void
    {
        $this->expectExceptionObject(new InvalidConfigException("The [rest.limits.{$group}.{$key}] option does not exist."));

        Limits::fromConfig($this->withLimit($group, $key, 50));
    }

    #[Test]
    public function it_names_an_unknown_group_of_the_limits(): void
    {
        $config = $this->packageLimits();
        $config['size'] = ['filters' => 50];

        $this->expectExceptionObject(new InvalidConfigException('The [rest.limits.size] option does not exist.'));

        Limits::fromConfig($config);
    }

    /** @return array<string, array{string, int|string}> */
    public static function unknownKeys(): array
    {
        return [
            'a size in the singular' => ['sizes', 'filter'],
            'a depth of a list that does not nest' => ['depths', 'sorts'],
            'a total of one list' => ['totals', 'includes'],
            'a length of a list' => ['lengths', 'filters'],
            'a numeric key' => ['sizes', 0],
        ];
    }

    /** @return array<string, array{string, string, Closure(Limits): int}> */
    public static function keyPaths(): array
    {
        return [
            'sizes.filters' => ['sizes', 'filters', static fn (Limits $limits): int => $limits->sizes->filters],
            'sizes.sorts' => ['sizes', 'sorts', static fn (Limits $limits): int => $limits->sizes->sorts],
            'sizes.selects' => ['sizes', 'selects', static fn (Limits $limits): int => $limits->sizes->selects],
            'sizes.scopes' => ['sizes', 'scopes', static fn (Limits $limits): int => $limits->sizes->scopes],
            'sizes.aggregates' => ['sizes', 'aggregates', static fn (Limits $limits): int => $limits->sizes->aggregates],
            'sizes.includes' => ['sizes', 'includes', static fn (Limits $limits): int => $limits->sizes->includes],
            'sizes.instructions' => ['sizes', 'instructions', static fn (Limits $limits): int => $limits->sizes->instructions],
            'sizes.fields' => ['sizes', 'fields', static fn (Limits $limits): int => $limits->sizes->fields],
            'sizes.values' => ['sizes', 'values', static fn (Limits $limits): int => $limits->sizes->values],
            'sizes.mutate' => ['sizes', 'mutate', static fn (Limits $limits): int => $limits->sizes->mutate],
            'sizes.relation_entries' => ['sizes', 'relation_entries', static fn (Limits $limits): int => $limits->sizes->relationEntries],
            'sizes.resources' => ['sizes', 'resources', static fn (Limits $limits): int => $limits->sizes->resources],
            'depths.filters' => ['depths', 'filters', static fn (Limits $limits): int => $limits->depths->filters],
            'depths.includes' => ['depths', 'includes', static fn (Limits $limits): int => $limits->depths->includes],
            'depths.mutations' => ['depths', 'mutations', static fn (Limits $limits): int => $limits->depths->mutations],
            'totals.filters' => ['totals', 'filters', static fn (Limits $limits): int => $limits->totals->filters],
            'totals.entries' => ['totals', 'entries', static fn (Limits $limits): int => $limits->totals->entries],
            'lengths.patterns' => ['lengths', 'patterns', static fn (Limits $limits): int => $limits->lengths->patterns],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function keys(): array
    {
        return array_map(static fn (array $path): array => [$path[0], $path[1]], self::keyPaths());
    }

    /** @return array<string, array{bool, mixed}> */
    public static function invalidLimits(): array
    {
        return [
            'a negative integer' => [true, -1],
            'a float' => [true, 1.5],
            'a decimal string' => [true, '1.5'],
            'a negative string' => [true, '-1'],
            'a word' => [true, 'many'],
            'a boolean' => [true, true],
            'null' => [true, null],
            'an empty string' => [true, ''],
            'a missing key' => [false, null],
        ];
    }

    /** @return array<string, array{bool, mixed}> */
    public static function invalidRelationTotals(): array
    {
        return [...self::invalidLimits(), 'zero' => [true, 0], 'zero as a string' => [true, '0']];
    }

    /** @return array<string, array{string, bool, string}> */
    public static function unreadableGroups(): array
    {
        return [
            'missing sizes' => ['sizes', false, 'rest.limits.sizes.filters'],
            'missing depths' => ['depths', false, 'rest.limits.depths.filters'],
            'missing totals' => ['totals', false, 'rest.limits.totals.filters'],
            'missing lengths' => ['lengths', false, 'rest.limits.lengths.patterns'],
            'sizes that are not an array' => ['sizes', true, 'rest.limits.sizes.filters'],
            'depths that are not an array' => ['depths', true, 'rest.limits.depths.filters'],
            'totals that are not an array' => ['totals', true, 'rest.limits.totals.filters'],
            'lengths that are not an array' => ['lengths', true, 'rest.limits.lengths.patterns'],
        ];
    }

    /** @return array<array-key, mixed> */
    private function withLimit(string $group, int|string $key, mixed $value): array
    {
        $config = $this->packageLimits();
        $this->assertIsArray($config[$group]);
        $config[$group][$key] = $value;

        return $config;
    }

    /** @return array<array-key, mixed> */
    private function withoutLimit(string $group, string $key): array
    {
        $config = $this->packageLimits();
        $this->assertIsArray($config[$group]);
        unset($config[$group][$key]);

        return $config;
    }

    /** @return array<array-key, mixed> */
    private function packageLimits(): array
    {
        $config = require __DIR__.'/../../../config/rest.php';

        $this->assertIsArray($config);
        $this->assertIsArray($config['limits']);

        return $config['limits'];
    }
}
