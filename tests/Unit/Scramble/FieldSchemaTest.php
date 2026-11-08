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
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowLevel;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowStatus;
use Lomkit\Rest\Tests\Fixtures\Festival\Stage;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Money;
use Lomkit\Rest\Tests\Unit\Scramble\Stubs\Points;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldSchemaTest extends TestCase
{
    #[Test]
    #[DataProvider('fields')]
    public function it_documents_the_type_of_each_field(Field $field, string $schema): void
    {
        $this->assertSame($schema, json_encode(FieldSchema::of($field)->serializeAs31(), JSON_THROW_ON_ERROR));
    }

    /** @return array<string, array{Field, string}> */
    public static function fields(): array
    {
        return [
            'an integer key' => [ID::make('id')->boundTo(new Show), '{"type":"integer"}'],
            'a string key' => [ID::make('code')->boundTo(new Stage), '{"type":"string"}'],
            'a uuid key' => [ID::make('id')->keyType('uuid'), '{"type":"string","format":"uuid"}'],
            'a ulid key' => [ID::make('id')->keyType('ulid'), '{"type":"string"}'],
            'text' => [Text::make('title'), '{"type":"string"}'],
            'number' => [Number::make('price'), '{"type":"number"}'],
            'integer' => [Integer::make('seats'), '{"type":"integer"}'],
            'boolean' => [Boolean::make('featured'), '{"type":"boolean"}'],
            'date' => [Date::make('starts_on'), '{"type":"string","format":"date"}'],
            'datetime' => [DateTime::make('starts_at'), '{"type":"string","format":"date-time"}'],
            'a string-backed enum' => [Enum::make('status', ShowStatus::class), '{"type":"string","enum":["planned","on_sale","sold_out"]}'],
            'an int-backed enum' => [Enum::make('level', ShowLevel::class), '{"type":"integer","enum":[1,2,3]}'],
            'json' => [Json::make('settings'), '{"type":"object"}'],
            'a type of the application' => [Money::make('amount'), '{"type":"string"}'],
            'a type of the application with its schema' => [Points::make('points'), '{"type":"number"}'],
            'a nullable field' => [Text::make('summary')->nullable(), '{"type":["string","null"]}'],
            'a nullable enum' => [Enum::make('level', ShowLevel::class)->nullable(), '{"type":["integer","null"],"enum":[1,2,3,null]}'],
            'a field with meta' => [Number::make('price')->meta(['unit' => 'EUR']), '{"type":"number","x-lomkit-meta":{"unit":"EUR"}}'],
            'a field with examples' => [Text::make('title')->examples('Opening night', 'Closing night'), '{"type":"string","examples":["Opening night","Closing night"]}'],
        ];
    }

    #[Test]
    public function it_replaces_the_examples_of_a_field_on_each_call(): void
    {
        $field = Text::make('title')
            ->examples('Opening night')
            ->examples('Closing night', 'Matinee');

        $this->assertSame(['Closing night', 'Matinee'], $field->getExamples());
    }
}
