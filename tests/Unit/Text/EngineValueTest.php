<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherKind;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Priority;
use Lomkit\Rest\Text\Engine\EngineValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class EngineValueTest extends TestCase
{
    #[Test]
    #[DataProvider('values')]
    public function it_converts_a_value_to_the_engine_value_of_the_attribute(Field $attribute, mixed $value, int|float|string|bool|null $expected): void
    {
        $this->assertSame($expected, EngineValue::of($attribute, $value));
    }

    /** @return array<string, array{Field, mixed, int|float|string|bool|null}> */
    public static function values(): array
    {
        return [
            'an integer' => [Integer::make('number'), 5, 5],
            'an integer string' => [Integer::make('number'), '5', 5],
            'an integral float for an integer' => [Integer::make('number'), 5.0, 5],
            'a fraction for an integer' => [Integer::make('number'), 5.5, null],
            'a decimal string for an integer' => [Integer::make('number'), '5.5', null],
            'an exponent for an integer' => [Integer::make('number'), '1e3', null],
            'a boolean for an integer' => [Integer::make('number'), true, null],
            'a number' => [Number::make('price'), 2.5, 2.5],
            'a number string' => [Number::make('price'), '7.25', 7.25],
            'an integral number string' => [Number::make('price'), '2.0', 2],
            'an integer for a number' => [Number::make('price'), 3, 3],
            'a large number' => [Number::make('price'), 1.0e20, 1.0e20],
            'an infinite number' => [Number::make('price'), INF, null],
            'not a number' => [Number::make('price'), NAN, null],
            'an overflowing number string' => [Number::make('price'), '1e400', null],
            'a word for a number' => [Number::make('price'), 'cheap', null],
            'a boolean for a number' => [Number::make('price'), false, null],
            'true' => [Boolean::make('active'), true, true],
            'false' => [Boolean::make('active'), false, false],
            'one' => [Boolean::make('active'), 1, true],
            'zero string' => [Boolean::make('active'), '0', false],
            'a word for a boolean' => [Boolean::make('active'), 'true', null],
            'two for a boolean' => [Boolean::make('active'), 2, null],
            'a text' => [Text::make('code'), 'V-a', 'V-a'],
            'an integer for a text' => [Text::make('code'), 7, '7'],
            'a float for a text' => [Text::make('code'), 1.5, null],
            'a boolean for a text' => [Text::make('code'), true, null],
            'an object for a text' => [Text::make('code'), new stdClass, null],
            'a case of the enum' => [Enum::make('kind', VoucherKind::class), VoucherKind::Debit, 'debit'],
            'a value of the enum' => [Enum::make('kind', VoucherKind::class), 'credit', 'credit'],
            'a value outside the enum' => [Enum::make('kind', VoucherKind::class), 'refund', null],
            'a case of another enum' => [Enum::make('kind', VoucherKind::class), Priority::Low, null],
            'an integer of an integer enum' => [Enum::make('priority', Priority::class), 2, 2],
            'a string of an integer enum' => [Enum::make('priority', Priority::class), '3', 3],
            'a word for an integer enum' => [Enum::make('priority', Priority::class), 'high', null],
            'an integer key' => [ID::make('id'), '4', 4],
            'a string key' => [ID::make('id')->keyType('string'), 4, '4'],
            'a uuid key' => [ID::make('id')->keyType('uuid'), '0195f8d2-6f8a-7c1e-9d2b-3a4b5c6d7e8f', '0195f8d2-6f8a-7c1e-9d2b-3a4b5c6d7e8f'],
            'a json document' => [Json::make('metadata'), 'a', null],
            'null' => [Integer::make('number'), null, null],
            'a list' => [Text::make('code'), ['V-a'], null],
        ];
    }
}
