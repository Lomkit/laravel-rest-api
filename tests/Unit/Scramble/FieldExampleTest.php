<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Scramble\FieldExample;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowLevel;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowStatus;
use Lomkit\Rest\Tests\Fixtures\Festival\Stage;
use Lomkit\Rest\Tests\Unit\Scramble\Stubs\Points;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class FieldExampleTest extends TestCase
{
    #[Test]
    #[DataProvider('fields')]
    public function it_gives_each_field_an_example_value(Field $field, int|float|string|bool $example): void
    {
        $this->assertSame($example, FieldExample::of($field));
    }

    /** @return array<string, array{Field, int|float|string|bool}> */
    public static function fields(): array
    {
        return [
            'an integer key' => [ID::make('id')->boundTo(new Show), 1],
            'a string key' => [ID::make('code')->boundTo(new Stage), 'a'],
            'a uuid key' => [ID::make('id')->keyType('uuid'), '00000000-0000-0000-0000-000000000000'],
            'a ulid key' => [ID::make('id')->keyType('ulid'), '00000000000000000000000000'],
            'text' => [Text::make('title'), 'a'],
            'number' => [Number::make('price'), 1],
            'integer' => [Integer::make('seats'), 1],
            'boolean' => [Boolean::make('featured'), true],
            'date' => [Date::make('starts_on'), '2026-01-01'],
            'datetime' => [DateTime::make('starts_at'), '2026-01-01T00:00:00Z'],
            'a string-backed enum' => [Enum::make('status', ShowStatus::class), 'planned'],
            'an int-backed enum' => [Enum::make('level', ShowLevel::class), 1],
            'a type of the application with its schema' => [Points::make('points'), 1],
            'a field with examples' => [Number::make('price')->examples(12.5, 20), 12.5],
            'a key with examples' => [ID::make('code')->boundTo(new Stage)->examples('main'), 'main'],
        ];
    }

    #[Test]
    #[DataProvider('rules')]
    public function it_gives_a_field_without_examples_a_value_that_honours_its_rules(Field $field, int|float|string|bool $example): void
    {
        $this->assertSame($example, FieldExample::of($field));
    }

    /** @return array<string, array{Field, int|float|string|bool}> */
    public static function rules(): array
    {
        return [
            'a minimum length' => [Text::make('password')->rules('min:8'), 'aaaaaaaa'],
            'a length between two bounds' => [Text::make('code')->rules('between:3,5'), 'aaa'],
            'an exact length' => [Text::make('code')->rules('size:4'), 'aaaa'],
            'a maximum length' => [Text::make('code')->rules('max:0'), ''],
            'an email' => [Text::make('contact')->rules('email', 'max:80'), 'user@example.com'],
            'a url' => [Text::make('website')->rules('url'), 'https://example.com'],
            'a date in a text' => [Text::make('released')->rules('date'), '2026-01-01'],
            'a numeric text' => [Text::make('zip')->rules('numeric', 'min:5'), '5'],
            'a list of allowed values' => [Text::make('color')->rules('in:red,blue'), 'red'],
            'a rule object of allowed values' => [Text::make('color')->rules(Rule::in(['green', 'blue'])), 'green'],
            'an integer with a minimum' => [Integer::make('seats')->rules('min:10'), 10],
            'an integer with a maximum' => [Integer::make('seats')->rules('max:0'), 0],
            'an integer between two bounds' => [Integer::make('nights')->rules('between:2,5'), 2],
            'an integer greater than a value' => [Integer::make('nights')->rules('gt:3'), 4],
            'an integer among allowed values' => [Integer::make('nights')->rules('in:3,6'), 3],
            'a number less than a value' => [Number::make('delta')->rules('lt:0'), -1],
            'a declined boolean' => [Boolean::make('optin')->rules('declined'), false],
            'a rule that it does not read' => [Text::make('slug')->rules('alpha_dash'), 'a'],
            'examples before the rules' => [Text::make('password')->rules('min:8')->examples('secret-word'), 'secret-word'],
        ];
    }

    #[Test]
    public function it_honours_the_rules_of_the_operation_that_it_gives_an_example_for(): void
    {
        $field = Text::make('code')->creationRules('size:3');

        $this->assertSame('aaa', FieldExample::of($field, WriteOperation::Create));
        $this->assertSame('a', FieldExample::of($field, WriteOperation::Update));
        $this->assertSame('a', FieldExample::of($field));
    }

    #[Test]
    public function it_gives_a_json_field_an_empty_object(): void
    {
        $this->assertEquals(new stdClass, FieldExample::of(Json::make('settings')));
    }
}
