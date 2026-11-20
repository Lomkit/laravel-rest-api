<?php

namespace Lomkit\Rest\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;

/** @internal */
final readonly class DeclaredFieldRules
{
    public static function inputs(FieldSet $fields, string $owner): void
    {
        foreach ($fields as $field) {
            self::totals($field, $owner);

            if ($field->getRules()->hasWriteRules()) {
                throw InvalidFieldException::writeRulesOnInput($owner, $field->name);
            }

            if (is_numeric($field->name)) {
                throw InvalidFieldException::numericInputName($owner, $field->name);
            }
        }
    }

    public static function pivot(FieldSet $fields, string $owner): void
    {
        foreach ($fields as $field) {
            self::totals($field, $owner);

            if ($field->isVirtual()) {
                throw $field->isInputOnly() ? InvalidFieldException::inputOnlyPivotField($owner, $field->name) : InvalidFieldException::virtualPivotField($owner, $field->name);
            }

            if ($field->fillsThroughCallback()) {
                throw InvalidFieldException::filledPivotField($owner, $field->name);
            }

            if ($field->resolvesThroughCallback()) {
                throw InvalidFieldException::resolvedPivotField($owner, $field->name);
            }
        }
    }

    private static function totals(Field $field, string $owner): void
    {
        if ($field->isGroupable() || $field->isMeasurable()) {
            throw InvalidFieldException::totalsOutsideResource($owner, $field->name);
        }
    }
}
