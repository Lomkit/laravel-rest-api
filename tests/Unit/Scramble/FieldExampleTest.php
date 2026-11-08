<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

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
    public function it_gives_a_json_field_an_empty_object(): void
    {
        $this->assertEquals(new stdClass, FieldExample::of(Json::make('settings')));
    }
}
