<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Support\Facades\Validator;
use Lomkit\Rest\Rules\MaxDepth;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MaxDepthTest extends PackageTestCase
{
    #[Test]
    #[DataProvider('values')]
    public function it_accepts_values_that_nest_at_most_the_given_levels(mixed $value, bool $accepted): void
    {
        $this->assertSame($accepted, (new MaxDepth(3))->accepts($value));
    }

    /** @return array<string, array{mixed, bool}> */
    public static function values(): array
    {
        return [
            'null' => [null, true],
            'a string' => ['deep', true],
            'an empty array' => [[], true],
            'three levels of objects' => [['a' => ['b' => ['c' => 1]]], true],
            'three levels of lists' => [[[[1, 2]]], true],
            'three levels ending in an empty list' => [[[[]]], true],
            'four levels of objects' => [['a' => ['b' => ['c' => ['d' => 1]]]], false],
            'four levels ending in an empty list' => [[[[[]]]], false],
            'four levels in the last of several branches' => [['a' => ['b' => 1], 'c' => ['d' => ['e' => ['f' => 1]]]], false],
        ];
    }

    #[Test]
    public function it_refuses_every_array_with_a_limit_of_zero(): void
    {
        $this->assertFalse((new MaxDepth(0))->accepts([]));
        $this->assertTrue((new MaxDepth(0))->accepts('flat'));
    }

    #[Test]
    public function it_reports_the_limit_on_the_attribute(): void
    {
        $validator = Validator::make(['meta' => ['a' => ['b' => ['c' => 1]]]], ['meta' => [new MaxDepth(2)]], [], ['meta' => 'metadata']);

        $this->assertSame(['meta' => ['The metadata field goes deeper than the limit of 2 levels.']], $validator->errors()->toArray());
    }

    #[Test]
    public function it_describes_itself_with_its_limit(): void
    {
        $this->assertSame('max_depth:64', (string) new MaxDepth(64));
    }
}
