<?php

namespace Lomkit\Rest\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;

/** @internal */
final readonly class DeclaredFieldRules
{
    public static function inputs(FieldSet $fields, string $owner): void
    {
        foreach ($fields as $field) {
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
}
