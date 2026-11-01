<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use InvalidArgumentException;
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

        $this->assertSame(100, $limits->filters);
        $this->assertSame(20, $limits->sorts);
        $this->assertSame(100, $limits->selects);
        $this->assertSame(10, $limits->scopes);
        $this->assertSame(20, $limits->aggregates);
        $this->assertSame(20, $limits->includes);
        $this->assertSame(10, $limits->instructions);
        $this->assertSame(7, $limits->gates);
        $this->assertSame(100, $limits->fields);
        $this->assertSame(100, $limits->values);
        $this->assertSame(100, $limits->mutate);
        $this->assertSame(100, $limits->relationEntries);
        $this->assertSame(1000, $limits->resources);
        $this->assertSame(3, $limits->includeDepth);
        $this->assertSame(3, $limits->mutationDepth);
        $this->assertSame(500, $limits->filterNodes);
        $this->assertSame(1000, $limits->nodes);
    }

    #[Test]
    public function it_reads_a_limit_written_as_digits(): void
    {
        $limits = Limits::fromConfig(['filters' => '50', ...$this->withoutFilters()]);

        $this->assertSame(50, $limits->filters);
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function it_refuses_a_limit_that_is_not_an_integer_of_zero_or_more(bool $present, mixed $value): void
    {
        $config = $present ? ['filters' => $value, ...$this->withoutFilters()] : $this->withoutFilters();

        $this->expectExceptionObject(new InvalidArgumentException('The [rest.limits.filters] option must be an integer of 0 or more.'));

        Limits::fromConfig($config);
    }

    /** @return array<string, array{bool, mixed}> */
    public static function invalidLimits(): array
    {
        return [
            'a negative integer' => [true, -1],
            'a float' => [true, 1.5],
            'a decimal string' => [true, '1.5'],
            'a word' => [true, 'many'],
            'a boolean' => [true, true],
            'null' => [true, null],
            'an empty string' => [true, ''],
            'a missing key' => [false, null],
        ];
    }

    /** @return array<array-key, mixed> */
    private function packageLimits(): array
    {
        $config = require __DIR__.'/../../../config/rest.php';

        $this->assertIsArray($config);
        $this->assertIsArray($config['limits']);

        return $config['limits'];
    }

    /** @return array<array-key, mixed> */
    private function withoutFilters(): array
    {
        $limits = $this->packageLimits();
        unset($limits['filters']);

        return $limits;
    }
}
