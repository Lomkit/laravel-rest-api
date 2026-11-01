<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\Amount;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class IndexAttributeDeclarationTest extends ScoutEngineDeclarationTestCase
{
    #[Test]
    #[DataProvider('unsupportedTypes')]
    public function it_refuses_an_attribute_type_without_engine_value(Field $attribute): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::unsupportedAttributeType(VoucherResource::class, $attribute->name, $attribute::class));

        $this->capabilities($this->open([$attribute]));
    }

    /** @return array<string, array{Field}> */
    public static function unsupportedTypes(): array
    {
        return [
            'a date' => [Date::make('issued_on')],
            'a date and time' => [DateTime::make('issued_at')],
            'a json document' => [Json::make('metadata')],
            'a subclass of an allowed type' => [Amount::make('amount')],
        ];
    }

    #[Test]
    #[DataProvider('callbackAttributes')]
    public function it_refuses_an_attribute_that_filters_or_sorts_through_a_callback(Field $attribute): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::callbackAttribute(VoucherResource::class, $attribute->name));

        $this->capabilities(ScoutEngine::make()->perimeter(fn (): null => null, [$attribute]));
    }

    /** @return array<string, array{Field}> */
    public static function callbackAttributes(): array
    {
        return [
            'a filter callback' => [Integer::make('number')->filterUsing(fn (Builder $query): Builder => $query)],
            'a sort callback' => [Number::make('price')->sortUsing(fn (Builder $query): Builder => $query)],
        ];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_refuses_an_attribute_name_engines_cannot_read(string $name): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::invalidAttributeName(VoucherResource::class, $name));

        $this->capabilities($this->open([Integer::make($name)]));
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'a hyphen' => ['team-id'],
            'a leading digit' => ['1st'],
            'a space' => ['team id'],
            'a quote' => ["team'"],
            'a trailing newline' => ["team\n"],
            'the soft delete flag' => ['__soft_deleted'],
        ];
    }

    #[Test]
    public function it_refuses_a_name_that_is_not_a_field_of_the_resource(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::unknownAttribute(VoucherResource::class, 'category_id'));

        $this->capabilities($this->open(['price', 'category_id']));
    }

    #[Test]
    public function it_refuses_a_declaration_that_is_neither_a_field_nor_a_name(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::notAnAttribute(VoucherResource::class, 'perimeter()', 1, 42));

        $this->capabilities(ScoutEngine::make()->perimeter(fn (): null => null, [Text::make('team'), 42]));
    }

    #[Test]
    public function it_refuses_an_attribute_declared_twice(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::duplicateAttribute(VoucherResource::class, 'price'));

        $this->capabilities(
            ScoutEngine::make()
                ->filterable(['price'])
                ->perimeter(fn (): null => null, [Number::make('price')]),
        );
    }

    #[Test]
    public function it_refuses_an_attribute_without_filter_operator(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::unfilterableAttribute(VoucherResource::class, 'active'));

        $this->capabilities($this->open([Boolean::make('active')->filterable(false)]));
    }

    #[Test]
    public function it_refuses_an_enum_attribute_without_enum(): void
    {
        $this->expectExceptionObject(InvalidFieldException::missingEnumClass('status'));

        $this->capabilities($this->open([Enum::make('status')]));
    }

    #[Test]
    #[DataProvider('unsortableNames')]
    public function it_refuses_a_sort_outside_the_filterable_attributes(string $name): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::unsortableAttribute(VoucherResource::class, $name));

        $this->capabilities(
            ScoutEngine::make()
                ->filterable(['price'])
                ->sortable([$name])
                ->perimeter(fn (): null => null, [Text::make('team')]),
        );
    }

    /** @return array<string, array{string}> */
    public static function unsortableNames(): array
    {
        return [
            'a field of the resource' => ['title'],
            'a perimeter attribute' => ['team'],
        ];
    }

    #[Test]
    public function it_refuses_a_sort_that_is_not_a_name(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::notAnAttributeName(VoucherResource::class, 0, Number::make('price')));

        $this->capabilities($this->open(['price'], [Number::make('price')]));
    }
}
