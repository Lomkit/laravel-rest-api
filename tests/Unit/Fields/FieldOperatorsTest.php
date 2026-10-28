<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Contracts\Filter;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator as Op;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldOperatorsTest extends TestCase
{
    #[Test]
    #[DataProvider('defaultFields')]
    public function it_offers_every_supported_operator_by_default(Field $field): void
    {
        $this->assertNotSame([], $field->filterOperators());
        $this->assertSame($field->supportedOperators(), $field->filterOperators());
    }

    /** @return array<string, array{Field}> */
    public static function defaultFields(): array
    {
        return [
            'id' => [ID::make('id')],
            'text' => [Text::make('title')],
            'number' => [Number::make('rating')],
            'integer' => [Integer::make('views')],
            'boolean' => [Boolean::make('featured')],
            'date' => [Date::make('published_on')],
            'datetime' => [DateTime::make('published_at')],
            'enum' => [Enum::make('status', PostStatus::class)],
            'nullable text' => [Text::make('summary')->nullable()],
            'nullable boolean' => [Boolean::make('featured')->nullable()],
            'nullable date' => [Date::make('published_on')->nullable()],
        ];
    }

    #[Test]
    public function it_offers_no_operator_on_a_json_field_unless_made_filterable_and_nullable(): void
    {
        $this->assertSame([], Json::make('metadata')->filterOperators());
        $this->assertSame([], Json::make('metadata')->nullable()->filterOperators());
        $this->assertSame([], Json::make('metadata')->filterable()->filterOperators());
        $this->assertSame([Op::IsNull, Op::IsNotNull], Json::make('metadata')->nullable()->filterable()->filterOperators());
    }

    #[Test]
    public function it_offers_no_operator_when_not_filterable(): void
    {
        $this->assertSame([], Text::make('title')->filterable(false)->filterOperators());
        $this->assertSame([], Text::make('summary')->nullable()->filterable(false)->filterOperators());
    }

    #[Test]
    public function it_narrows_the_operators_in_the_declared_order(): void
    {
        $this->assertSame([Op::In, Op::Equal, Op::ILike], Text::make('title')->filterable(['in', '=', 'ilike'])->filterOperators());
        $this->assertSame([Op::NotIn, Op::IsNull], Text::make('summary')->nullable()->filterable(['NOT IN', Op::IsNull->value])->filterOperators());
    }

    #[Test]
    public function it_rejects_a_narrowed_operator_the_type_lacks_when_the_operators_are_resolved(): void
    {
        $field = Boolean::make('featured')->filterable(['=', '>']);

        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage('The field [featured] does not support the operator [>].');

        $field->filterOperators();
    }

    #[Test]
    public function it_resolves_a_narrowed_null_operator_whatever_the_call_order(): void
    {
        $this->assertSame([Op::IsNull], Text::make('summary')->filterable(['is null'])->nullable()->filterOperators());
        $this->assertSame([Op::IsNull], Text::make('summary')->nullable()->filterable(['is null'])->filterOperators());
    }

    #[Test]
    public function it_rejects_a_narrowed_null_operator_once_the_field_is_no_longer_nullable(): void
    {
        $field = Text::make('summary')->nullable()->filterable(['is null'])->nullable(false);

        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage('The field [summary] does not support the operator [is null].');

        $field->isFilterable();
    }

    #[Test]
    public function it_offers_no_operator_on_a_virtual_field_without_a_custom_filter(): void
    {
        $this->assertSame([], Text::make('full_name')->virtual()->filterOperators());
        $this->assertSame([], Text::make('full_name')->virtual()->filterable(['='])->filterOperators());
    }

    #[Test]
    public function it_offers_the_type_operators_on_a_virtual_field_with_a_custom_filter(): void
    {
        $closure = Boolean::make('popular')->virtual()->filterUsing(fn (Builder $query, string $operator, mixed $value) => $query);
        $filter = Boolean::make('popular')->virtual()->filterUsing(new class implements Filter
        {
            public function apply(Builder $query, string $operator, mixed $value): void {}
        });

        $this->assertSame([Op::Equal, Op::NotEqual], $closure->filterOperators());
        $this->assertSame([Op::Equal, Op::NotEqual], $filter->filterOperators());
        $this->assertSame([Op::NotEqual], $closure->filterable(['!='])->filterOperators());
    }

    #[Test]
    #[DataProvider('filterability')]
    public function it_is_filterable_when_at_least_one_operator_is_offered(Field $field, bool $expected): void
    {
        $this->assertSame($expected, $field->isFilterable());
    }

    /** @return array<string, array{Field, bool}> */
    public static function filterability(): array
    {
        return [
            'text by default' => [Text::make('title'), true],
            'text not filterable' => [Text::make('title')->filterable(false), false],
            'text narrowed' => [Text::make('title')->filterable(['=']), true],
            'text narrowed to nothing' => [Text::make('title')->filterable([]), false],
            'virtual text' => [Text::make('full_name')->virtual(), false],
            'virtual text with a custom filter' => [Text::make('full_name')->virtual()->filterUsing(fn (Builder $query) => $query), true],
            'json by default' => [Json::make('metadata'), false],
            'json made filterable' => [Json::make('metadata')->filterable(), false],
            'nullable json made filterable' => [Json::make('metadata')->nullable()->filterable(), true],
        ];
    }

    #[Test]
    #[DataProvider('operatorResolvers')]
    public function it_rejects_an_unknown_operator_declared_by_an_app_type_when_its_operators_are_resolved(string $resolver): void
    {
        $field = new class('slug') extends Field
        {
            public function operators(): array
            {
                return ['=', 'contains'];
            }

            public function baseRules(): array
            {
                return ['string'];
            }
        };

        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage('Unknown filter operator [contains].');

        $field->{$resolver}();
    }

    /** @return array<string, array{string}> */
    public static function operatorResolvers(): array
    {
        return [
            'supported operators' => ['supportedOperators'],
            'filter operators' => ['filterOperators'],
            'filterability' => ['isFilterable'],
        ];
    }
}
