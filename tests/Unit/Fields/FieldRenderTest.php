<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Percentage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldRenderTest extends TestCase
{
    #[Test]
    #[DataProvider('renderedValues')]
    public function it_renders_a_value_in_the_type_of_its_field(Field $field, mixed $value, string $expected): void
    {
        $this->assertSame($expected, json_encode($field->render($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, array{0: Field, 1: mixed, 2: string}> */
    public static function renderedValues(): array
    {
        return [
            'a decimal string' => [Number::make('price'), '25000.50', '25000.5'],
            'a decimal string without fraction' => [Number::make('price'), '7.00', '7.0'],
            'an integer string on a number' => [Number::make('price'), '12', '12'],
            'an exponent' => [Number::make('price'), '1e3', '1000.0'],
            'a float' => [Number::make('price'), 1.5, '1.5'],
            'an integer' => [Number::make('price'), 12, '12'],
            'null on a number' => [Number::make('price'), null, 'null'],
            'a string that is not numeric' => [Number::make('price'), 'n/a', '"n/a"'],
            'an integer string' => [Integer::make('seats'), '300', '300'],
            'an integer on an integer' => [Integer::make('seats'), 300, '300'],
            'a decimal string on an integer' => [Integer::make('seats'), '2.50', '2.5'],
            'a type that extends a number' => [Percentage::make('rate'), '0.25', '0.25'],
            'an empty array' => [Json::make('settings'), [], '{}'],
            'an object' => [Json::make('settings'), ['lights' => 'warm'], '{"lights":"warm"}'],
            'a list' => [Json::make('settings'), ['warm', 'cold'], '["warm","cold"]'],
            'null on a json field' => [Json::make('settings'), null, 'null'],
            'a numeric string on a text field' => [Text::make('code'), '12', '"12"'],
            'an empty array on a text field' => [Text::make('code'), [], '[]'],
        ];
    }
}
