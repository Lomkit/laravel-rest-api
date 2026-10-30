<?php

namespace Lomkit\Rest\Fields;

enum AggregateFunction: string
{
    case Count = 'count';
    case Min = 'min';
    case Max = 'max';
    case Avg = 'avg';
    case Sum = 'sum';
    case Exists = 'exists';

    /** @return list<self> */
    public static function needingField(): array
    {
        return array_values(array_filter(self::cases(), fn (self $function): bool => $function->needsField()));
    }

    /** @return list<self> */
    public static function takingNoField(): array
    {
        return array_values(array_filter(self::cases(), fn (self $function): bool => ! $function->needsField()));
    }

    public function needsField(): bool
    {
        return match ($this) {
            self::Count, self::Exists => false,
            self::Min, self::Max, self::Avg, self::Sum => true,
        };
    }

    public function accepts(Field $field): bool
    {
        if (! $field->isSortableByColumn()) {
            return false;
        }

        return match ($this) {
            self::Count, self::Exists => false,
            self::Min, self::Max => ! $field instanceof Boolean,
            self::Avg, self::Sum => $field instanceof Number,
        };
    }

    /** @return list<string> */
    public function acceptedFieldNames(FieldSet $fields): array
    {
        $names = [];

        foreach ($fields as $field) {
            if ($this->accepts($field)) {
                $names[] = $field->name;
            }
        }

        return $names;
    }
}
