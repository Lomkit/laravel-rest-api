<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InputOnlyFieldTest extends TestCase
{
    #[Test]
    #[DataProvider('writability')]
    public function it_writes_an_input_only_field_where_the_field_is_writable(Field $field, bool $create, bool $update): void
    {
        $this->assertTrue($field->isInputOnly());
        $this->assertSame($create, $field->isWritableOn(WriteOperation::Create));
        $this->assertSame($update, $field->isWritableOn(WriteOperation::Update));
    }

    /** @return array<string, array{Field, bool, bool}> */
    public static function writability(): array
    {
        return [
            'plain' => [Text::make('delivery_address')->inputOnly(), true, true],
            'not creatable' => [
                Text::make('delivery_address')
                    ->inputOnly()
                    ->creatable(false),
                false,
                true,
            ],
            'not updatable' => [
                Text::make('delivery_address')
                    ->inputOnly()
                    ->updatable(false),
                true,
                false,
            ],
            'readonly' => [
                Text::make('delivery_address')
                    ->inputOnly()
                    ->readonly(),
                false,
                false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('declaredReads')]
    public function it_never_selects_sorts_filters_or_aggregates_an_input_only_field(Field $field): void
    {
        $this->assertTrue($field->isVirtual());
        $this->assertTrue($field->isWriteOnly());
        $this->assertFalse($field->isSelectable());
        $this->assertFalse($field->isSortable());
        $this->assertFalse($field->isSortableByColumn());
        $this->assertFalse($field->isFilterable());
        $this->assertSame([], $field->filterOperators());
        $this->assertFalse($field->isSearchable());
        $this->assertFalse(AggregateFunction::Min->accepts($field));
        $this->assertFalse(AggregateFunction::Max->accepts($field));
    }

    /** @return array<string, array{Field}> */
    public static function declaredReads(): array
    {
        return [
            'plain' => [Text::make('delivery_address')->inputOnly()],
            'selectable, sortable and filterable' => [
                Text::make('delivery_address')
                    ->inputOnly()
                    ->selectable(true)
                    ->sortable(true)
                    ->filterable(true),
            ],
        ];
    }

    #[Test]
    public function it_fills_nothing_from_an_input_only_field(): void
    {
        $order = new Order;

        Text::make('delivery_address')
            ->inputOnly()
            ->fill($order, '12 Quay Street');

        $this->assertSame([], $order->getAttributes());
    }

    #[Test]
    #[DataProvider('conflicts')]
    public function it_refuses_a_declaration_that_conflicts_with_input_only(Closure $declare, string $method): void
    {
        $this->expectExceptionObject(InvalidFieldException::inputOnlyWith('delivery_address', $method));

        $declare(Text::make('delivery_address'));
    }

    /** @return iterable<string, array{Closure(Field): Field, string}> */
    public static function conflicts(): iterable
    {
        $declarations = [
            'fillUsing' => fn (Field $field): Field => $field->fillUsing(self::fillNothing(...)),
            'resolveUsing' => fn (Field $field): Field => $field->resolveUsing(fn (Model $model): null => null),
            'filterUsing' => fn (Field $field): Field => $field->filterUsing(fn (Builder $query, string $operator, mixed $value): Builder => $query),
            'sortUsing' => fn (Field $field): Field => $field->sortUsing(fn (Builder $query, string $direction): Builder => $query),
            'searchable' => fn (Field $field): Field => $field->searchable(),
        ];

        foreach ($declarations as $method => $declaration) {
            yield $method.' after inputOnly()' => [fn (Field $field): Field => $declaration($field->inputOnly()), $method];
            yield $method.' before inputOnly()' => [fn (Field $field): Field => $declaration($field)->inputOnly(), $method];
        }
    }

    #[Test]
    public function it_keeps_the_type_refusal_of_a_searchable_field_that_cannot_be_searched(): void
    {
        $this->expectExceptionObject(InvalidFieldException::notSearchableType('quantity', 'integer'));

        Integer::make('quantity')
            ->inputOnly()
            ->searchable();
    }

    #[Test]
    public function it_keeps_input_only_after_virtual_false_and_write_only_false(): void
    {
        $field = Text::make('delivery_address')
            ->inputOnly()
            ->virtual(false)
            ->writeOnly(false);

        $this->assertTrue($field->isInputOnly());
        $this->assertTrue($field->isVirtual());
        $this->assertTrue($field->isWriteOnly());
        $this->assertFalse($field->isSelectable());
    }

    #[Test]
    public function it_drops_input_only_with_false(): void
    {
        $order = new Order;
        $field = Text::make('delivery_address')
            ->inputOnly()
            ->inputOnly(false)
            ->sortUsing(fn (Builder $query, string $direction): Builder => $query);

        $field->fill($order, '12 Quay Street');

        $this->assertFalse($field->isInputOnly());
        $this->assertFalse($field->isVirtual());
        $this->assertFalse($field->isWriteOnly());
        $this->assertTrue($field->isSelectable());
        $this->assertSame(['delivery_address' => '12 Quay Street'], $order->getAttributes());
    }

    private static function fillNothing(Model $model, mixed $value): void {}
}
