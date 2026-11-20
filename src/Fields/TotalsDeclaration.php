<?php

namespace Lomkit\Rest\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;

/** @internal */
final readonly class TotalsDeclaration
{
    public static function ensure(FieldSet $fields): void
    {
        foreach ($fields as $field) {
            if ($field->isGroupable()) {
                self::group($field);
            }

            if ($field->isMeasurable()) {
                self::measure($field);
            }
        }
    }

    private static function group(Field $field): void
    {
        if (! $field instanceof Enum && ! $field instanceof Boolean && ! $field instanceof Text && ! $field instanceof Integer && ! $field instanceof ID) {
            throw InvalidFieldException::ungroupableType($field->name, $field->type());
        }

        self::column($field, 'groupable');

        if ($field->filtersThroughCallback()) {
            throw InvalidFieldException::totalsConflict($field->name, 'groupable', 'filterUsing()', 'a group counts the values of its column, which the filter callback does not compare');
        }

        if (! $field->isFilterable()) {
            throw InvalidFieldException::totalsConflict($field->name, 'groupable', 'filterable(false)', 'a group shows the values that a filter on the field would show');
        }
    }

    private static function measure(Field $field): void
    {
        if (! $field instanceof Number) {
            throw InvalidFieldException::unmeasurableType($field->name, $field->type());
        }

        self::column($field, 'measurable');

        if (! $field->isSelectable()) {
            throw InvalidFieldException::totalsConflict($field->name, 'measurable', 'selectable(false)', 'a measure shows the values that the rows hold');
        }
    }

    private static function column(Field $field, string $method): void
    {
        if ($field->isInputOnly()) {
            throw InvalidFieldException::inputOnlyWith($field->name, $method);
        }

        $cause = match (true) {
            $field->isVirtual() => 'virtual',
            $field->resolvesThroughCallback() => 'resolved through resolveUsing()',
            $field->isWriteOnly() => 'write-only',
            default => null,
        };

        if ($cause !== null) {
            throw InvalidFieldException::totalsWithoutColumn($field->name, $method, $cause);
        }
    }
}
