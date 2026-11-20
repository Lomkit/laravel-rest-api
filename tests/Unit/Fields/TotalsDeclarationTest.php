<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\TotalsDeclaration;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TotalsDeclarationTest extends TestCase
{
    #[Test]
    public function it_accepts_the_declared_groups_and_measures(): void
    {
        $fields = FieldSet::of([
            ID::make('id')->groupable(),
            Text::make('title')->groupable(),
            Integer::make('seats')
                ->groupable()
                ->measurable(),
            Number::make('price')->measurable(),
            Boolean::make('featured')->groupable(),
            Enum::make('status', PostStatus::class)->groupable(),
            Text::make('summary')->filterable(false),
        ]);

        TotalsDeclaration::ensure($fields);

        $this->assertSame(['id', 'title', 'seats', 'featured', 'status'], $fields->groupable()->names());
        $this->assertSame(['seats', 'price'], $fields->measurable()->names());
    }

    #[Test]
    public function it_lets_a_field_turn_its_totals_off_again(): void
    {
        $field = Integer::make('seats')
            ->groupable()
            ->measurable()
            ->groupable(false)
            ->measurable(false);

        $this->assertFalse($field->isGroupable());
        $this->assertFalse($field->isMeasurable());
    }

    #[Test]
    public function it_refuses_totals_on_pivot_and_input_fields(): void
    {
        $this->assertSame(
            [InvalidFieldException::totalsOutsideResource('pivotFields()', 'position')->getMessage(), InvalidFieldException::totalsOutsideResource('fields()', 'amount')->getMessage()],
            [self::refusal(static fn (): FieldSet => FieldSet::forPivot([Integer::make('position')->groupable()], 'pivotFields()')), self::refusal(static fn (): FieldSet => FieldSet::forInputs([Number::make('amount')->measurable()], 'fields()'))],
        );
    }

    /** @param \Closure(): FieldSet $declare */
    private static function refusal(\Closure $declare): string
    {
        try {
            $declare();
        } catch (InvalidFieldException $exception) {
            return $exception->getMessage();
        }

        return 'accepted';
    }

    #[Test]
    #[DataProvider('refusals')]
    public function it_refuses_a_field_whose_totals_would_not_read_its_column(Field $field, InvalidFieldException $expected): void
    {
        $this->expectExceptionObject($expected);

        TotalsDeclaration::ensure(FieldSet::of([$field]));
    }

    /** @return array<string, array{Field, InvalidFieldException}> */
    public static function refusals(): array
    {
        return [
            'a grouped date' => [Date::make('starts_on')->groupable(), InvalidFieldException::ungroupableType('starts_on', 'date')],
            'a grouped date and time' => [DateTime::make('starts_at')->groupable(), InvalidFieldException::ungroupableType('starts_at', 'datetime')],
            'a grouped number' => [Number::make('price')->groupable(), InvalidFieldException::ungroupableType('price', 'number')],
            'a grouped json' => [Json::make('settings')->groupable(), InvalidFieldException::ungroupableType('settings', 'json')],
            'a measured text' => [Text::make('title')->measurable(), InvalidFieldException::unmeasurableType('title', 'text')],
            'a measured id' => [ID::make('id')->measurable(), InvalidFieldException::unmeasurableType('id', 'id')],
            'a grouped input-only field' => [Text::make('note')->inputOnly()->groupable(), InvalidFieldException::inputOnlyWith('note', 'groupable')],
            'a measured input-only field' => [Integer::make('count')->inputOnly()->measurable(), InvalidFieldException::inputOnlyWith('count', 'measurable')],
            'a grouped virtual field' => [Text::make('headline')->virtual()->groupable(), InvalidFieldException::totalsWithoutColumn('headline', 'groupable', 'virtual')],
            'a measured resolved field' => [Integer::make('score')->resolveUsing(static fn (Model $model): int => 1)->measurable(), InvalidFieldException::totalsWithoutColumn('score', 'measurable', 'resolved through resolveUsing()')],
            'a grouped write-only field' => [Text::make('secret')->writeOnly()->groupable(), InvalidFieldException::totalsWithoutColumn('secret', 'groupable', 'write-only')],
            'an unselectable measure' => [Integer::make('seats')->selectable(false)->measurable(), InvalidFieldException::totalsConflict('seats', 'measurable', 'selectable(false)', 'a measure shows the values that the rows hold')],
            'an unfilterable group' => [Text::make('title')->groupable()->filterable(false), InvalidFieldException::totalsConflict('title', 'groupable', 'filterable(false)', 'a group shows the values that a filter on the field would show')],
            'a group filtered through a callback' => [Text::make('title')->filterUsing(static fn (Builder $query, string $operator, mixed $value): mixed => $query)->groupable(), InvalidFieldException::totalsConflict('title', 'groupable', 'filterUsing()', 'a group counts the values of its column, which the filter callback does not compare')],
        ];
    }
}
