<?php

namespace Lomkit\Rest\Tests\Unit\Rules;

use Lomkit\Rest\Rules\ArrayWithKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArrayWithKeyTest extends TestCase
{
    #[Test]
    public function it_fails_once_on_a_value_that_is_not_an_array(): void
    {
        $this->assertSame(['The attributes field must be an array.'], $this->failures(['name'], 'Desk lamp'));
    }

    #[Test]
    public function it_reports_each_key_that_is_not_allowed(): void
    {
        $this->assertSame(
            ['The stock key is not valid for the attributes field.', 'The color key is not valid for the attributes field.'],
            $this->failures(['name', 'price'], ['name' => 'Desk lamp', 'stock' => 3, 'color' => 'black']),
        );
    }

    #[Test]
    public function it_accepts_the_allowed_keys(): void
    {
        $this->assertSame([], $this->failures(['name', 'price'], ['name' => 'Desk lamp', 'price' => 39]));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function failures(array $keys, mixed $value): array
    {
        $failures = [];

        (new ArrayWithKey($keys))->validate('attributes', $value, function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        return $failures;
    }
}
