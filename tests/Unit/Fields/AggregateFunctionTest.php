<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Money;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Percentage;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Toggle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AggregateFunctionTest extends TestCase
{
    #[Test]
    public function it_splits_the_functions_by_whether_they_need_a_field(): void
    {
        $this->assertSame([AggregateFunction::Min, AggregateFunction::Max, AggregateFunction::Avg, AggregateFunction::Sum], AggregateFunction::needingField());
        $this->assertSame([AggregateFunction::Count, AggregateFunction::Exists], AggregateFunction::takingNoField());
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('orderableFields')]
    public function it_takes_any_orderable_stored_field_for_min_and_max(Closure $field): void
    {
        $this->assertTrue(AggregateFunction::Min->accepts($field()));
        $this->assertTrue(AggregateFunction::Max->accepts($field()));
        $this->assertFalse(AggregateFunction::Count->accepts($field()));
        $this->assertFalse(AggregateFunction::Exists->accepts($field()));
    }

    /** @return array<string, array{Closure(): Field}> */
    public static function orderableFields(): array
    {
        return [
            'text' => [fn (): Field => Text::make('title')],
            'date' => [fn (): Field => Date::make('published_on')],
            'id' => [fn (): Field => ID::make('id')],
            'uuid id' => [fn (): Field => ID::make('id')->keyType('uuid')],
            'app type' => [fn (): Field => Money::make('price')],
            'not selectable' => [fn (): Field => Text::make('title')->selectable(false)],
            'text with a custom sort' => [fn (): Field => Text::make('title')->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('views', $direction))],
            'json marked sortable' => [fn (): Field => Json::make('metadata')->sortable()],
        ];
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('unorderableFields')]
    public function it_refuses_a_field_that_is_not_an_orderable_stored_field(Closure $field): void
    {
        foreach (AggregateFunction::cases() as $function) {
            $this->assertFalse($function->accepts($field()), $function->value);
        }
    }

    /** @return array<string, array{Closure(): Field}> */
    public static function unorderableFields(): array
    {
        $byTitle = fn (Builder $query, string $direction) => $query->orderBy('title', $direction);

        return [
            'json' => [fn (): Field => Json::make('metadata')],
            'json with a custom sort' => [fn (): Field => Json::make('metadata')->sortUsing($byTitle)],
            'not sortable' => [fn (): Field => Integer::make('views')->sortable(false)],
            'not sortable with a custom sort' => [fn (): Field => Integer::make('views')->sortable(false)->sortUsing($byTitle)],
            'virtual' => [fn (): Field => Integer::make('popularity')->virtual()],
            'virtual with a custom sort' => [fn (): Field => Integer::make('popularity')->virtual()->sortUsing($byTitle)],
            'write only' => [fn (): Field => Integer::make('secret_score')->writeOnly()],
            'write only with a custom sort' => [fn (): Field => Integer::make('secret_score')->writeOnly()->sortUsing($byTitle)],
        ];
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('booleanFields')]
    public function it_refuses_a_boolean_field(Closure $field): void
    {
        foreach (AggregateFunction::cases() as $function) {
            $this->assertFalse($function->accepts($field()), $function->value);
        }
    }

    /** @return array<string, array{Closure(): Field}> */
    public static function booleanFields(): array
    {
        return [
            'boolean' => [fn (): Field => Boolean::make('featured')],
            'boolean marked sortable' => [fn (): Field => Boolean::make('featured')->sortable()],
            'app subclass of boolean' => [fn (): Field => Toggle::make('archived')],
        ];
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('numberFields')]
    public function it_takes_a_number_field_for_avg_and_sum(Closure $field): void
    {
        $this->assertTrue(AggregateFunction::Avg->accepts($field()));
        $this->assertTrue(AggregateFunction::Sum->accepts($field()));
    }

    /** @return array<string, array{Closure(): Field}> */
    public static function numberFields(): array
    {
        return [
            'number' => [fn (): Field => Number::make('rating')],
            'integer' => [fn (): Field => Integer::make('views')],
            'app subclass of number' => [fn (): Field => Percentage::make('completion')],
        ];
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('orderableFields')]
    public function it_refuses_a_field_that_is_not_a_number_for_avg_and_sum(Closure $field): void
    {
        $this->assertFalse(AggregateFunction::Avg->accepts($field()));
        $this->assertFalse(AggregateFunction::Sum->accepts($field()));
    }

    #[Test]
    public function it_lists_the_accepted_field_names_in_declared_order(): void
    {
        $fields = FieldSet::of([
            ID::make('id'),
            Text::make('title'),
            Boolean::make('featured'),
            Integer::make('views'),
            Json::make('metadata'),
            Number::make('rating'),
            Text::make('full_name')->virtual(),
        ]);

        $this->assertSame(['id', 'title', 'views', 'rating'], AggregateFunction::Min->acceptedFieldNames($fields));
        $this->assertSame(['views', 'rating'], AggregateFunction::Sum->acceptedFieldNames($fields));
        $this->assertSame([], AggregateFunction::Count->acceptedFieldNames($fields));
    }
}
