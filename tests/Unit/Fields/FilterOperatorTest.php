<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Filtering\CaseInsensitivePattern;
use Lomkit\Rest\Fields\Filtering\Comparison;
use Lomkit\Rest\Fields\Filtering\Condition;
use Lomkit\Rest\Fields\Filtering\FilterOperator as Op;
use Lomkit\Rest\Fields\Filtering\Membership;
use Lomkit\Rest\Fields\Filtering\Nullity;
use Lomkit\Rest\Fields\Filtering\Pattern;
use Lomkit\Rest\Fields\Filtering\Range;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilterOperatorTest extends TestCase
{
    #[Test]
    #[DataProvider('parsedOperators')]
    public function it_parses_operators_regardless_of_case(Op|string $operator, Op $expected): void
    {
        $this->assertSame($expected, Op::parse($operator));
    }

    /** @return array<string, array{Op|string, Op}> */
    public static function parsedOperators(): array
    {
        return [
            'symbol' => ['>=', Op::GreaterThanOrEqual],
            'lower case' => ['not between', Op::NotBetween],
            'upper case' => ['ILIKE', Op::ILike],
            'mixed case' => ['Is Not Null', Op::IsNotNull],
            'case' => [Op::NotLike, Op::NotLike],
        ];
    }

    #[Test]
    #[DataProvider('unknownOperators')]
    public function it_rejects_an_unknown_operator(string $operator): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage("Unknown filter operator [{$operator}].");

        Op::parse($operator);
    }

    /** @return array<string, array{string}> */
    public static function unknownOperators(): array
    {
        return [
            'word' => ['Contains'],
            'symbol' => ['=='],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('conditions')]
    public function it_maps_each_operator_to_its_condition(Op $operator, Condition $condition): void
    {
        $this->assertEquals($condition, $operator->condition());
    }

    #[Test]
    public function it_backs_the_sixteen_operators_with_their_wire_values_and_maps_each_of_them(): void
    {
        $conditions = self::conditions();

        $this->assertSame(array_keys($conditions), array_column(Op::cases(), 'value'));
        $this->assertSame(Op::cases(), array_column($conditions, 0));
        $this->assertCount(16, Op::cases());
    }

    /** @return array<string, array{Op, Condition}> */
    public static function conditions(): array
    {
        return [
            '=' => [Op::Equal, new Comparison('=')],
            '!=' => [Op::NotEqual, new Comparison('!=')],
            '>' => [Op::GreaterThan, new Comparison('>')],
            '>=' => [Op::GreaterThanOrEqual, new Comparison('>=')],
            '<' => [Op::LessThan, new Comparison('<')],
            '<=' => [Op::LessThanOrEqual, new Comparison('<=')],
            'like' => [Op::Like, new Pattern(false)],
            'not like' => [Op::NotLike, new Pattern(true)],
            'ilike' => [Op::ILike, new CaseInsensitivePattern(false)],
            'not ilike' => [Op::NotILike, new CaseInsensitivePattern(true)],
            'in' => [Op::In, new Membership(false)],
            'not in' => [Op::NotIn, new Membership(true)],
            'between' => [Op::Between, new Range(false)],
            'not between' => [Op::NotBetween, new Range(true)],
            'is null' => [Op::IsNull, new Nullity(false)],
            'is not null' => [Op::IsNotNull, new Nullity(true)],
        ];
    }
}
